<?php
declare(strict_types=1);

/*
 * Aplikasi Kasir Kafe — koneksi database + skema otomatis.
 *
 * SQLite via PDO. Urutan PRAGMA penting: busy_timeout -> WAL -> synchronous,
 * dan seluruh pembuatan skema + data awal dibungkus SATU transaksi BEGIN IMMEDIATE
 * supaya tidak ada fsync per statement (lihat catatan performa di AGENTS.md).
 */

date_default_timezone_set('Asia/Makassar');

/* ------------------------------------------------------------------ */
/* Alur pesanan (dapur)                                               */
/* ------------------------------------------------------------------ */

const PS_BARU      = 'BARU';        // pesanan masuk, belum direspons dapur
const PS_DITERIMA  = 'DITERIMA';    // pesanan diterima dapur
const PS_DISIAPKAN = 'DISIAPKAN';   // sedang disiapkan
const PS_SIAP      = 'SIAP';        // siap disajikan
const PS_SELESAI   = 'SELESAI';     // selesai (selamat menikmati)
const PS_BATAL     = 'BATAL';

const URUT_STATUS = [
    PS_BARU      => 1,
    PS_DITERIMA  => 2,
    PS_DISIAPKAN => 3,
    PS_SIAP      => 4,
    PS_SELESAI   => 5,
];

const LABEL_STATUS = [
    PS_BARU      => 'Pesanan Masuk',
    PS_DITERIMA  => 'Pesanan Diterima',
    PS_DISIAPKAN => 'Sedang Disiapkan',
    PS_SIAP      => 'Siap Disajikan',
    PS_SELESAI   => 'Selesai — Selamat Menikmati',
    PS_BATAL     => 'Dibatalkan',
];

const LABEL_STATUS_PENDEK = [
    PS_BARU      => 'Masuk',
    PS_DITERIMA  => 'Diterima',
    PS_DISIAPKAN => 'Disiapkan',
    PS_SIAP      => 'Siap',
    PS_SELESAI   => 'Selesai',
    PS_BATAL     => 'Batal',
];

/** Langkah yang ditampilkan ke pelanggan di halaman lacak / struk. */
const LANGKAH_PELANGGAN = [PS_BARU, PS_DITERIMA, PS_DISIAPKAN, PS_SIAP, PS_SELESAI];

/* ------------------------------------------------------------------ */
/* Pembayaran                                                         */
/* ------------------------------------------------------------------ */

const BAYAR_BELUM = 'BELUM';
const BAYAR_LUNAS = 'LUNAS';

const METODE_TUNAI = 'TUNAI';
const METODE_QRIS  = 'QRIS';

const LABEL_METODE = [
    METODE_TUNAI => 'Tunai di Kasir',
    METODE_QRIS  => 'QRIS',
];

const SUMBER_KASIR    = 'kasir';
const SUMBER_PELANGGAN = 'pelanggan';

const BULAN_ID = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                  7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
const HARI_PENDEK = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

/* ------------------------------------------------------------------ */
/* Koneksi                                                            */
/* ------------------------------------------------------------------ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!class_exists('PDO')) {
        http_response_code(500);
        exit('Ekstensi database (PDO SQLite) tidak tersedia di server ini.');
    }

    /* KASIR_DB hanya untuk pengujian lokal — produksi memakai data/kasir.sqlite. */
    $path = getenv('KASIR_DB') ?: (__DIR__ . '/data/kasir.sqlite');
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');

    schema_ensure($pdo);

    $tz = (string) $pdo->query('SELECT nilai FROM pengaturan WHERE kunci = "zona_waktu"')->fetchColumn();
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
    return $pdo;
}

/** Apakah kolom sudah ada (untuk migrasi ALTER TABLE yang aman diulang). */
function kolom_ada(PDO $pdo, string $tabel, string $kolom): bool
{
    foreach ($pdo->query('PRAGMA table_info(' . $tabel . ')') as $c) {
        if (strtolower((string) $c['name']) === strtolower($kolom)) {
            return true;
        }
    }
    return false;
}

function tambah_kolom(PDO $pdo, string $tabel, string $kolom, string $definisi): void
{
    if (!kolom_ada($pdo, $tabel, $kolom)) {
        $pdo->exec('ALTER TABLE ' . $tabel . ' ADD COLUMN ' . $kolom . ' ' . $definisi);
    }
}

/* ------------------------------------------------------------------ */
/* Skema                                                              */
/* ------------------------------------------------------------------ */

function schema_ensure(PDO $pdo): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS pengaturan (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT ""
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL DEFAULT "",
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "kasir",
            aktif INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT "",
            failed_count INTEGER NOT NULL DEFAULT 0,
            locked_until TEXT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS sesi (
            token_hash TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT "",
            expires_at TEXT NOT NULL DEFAULT "",
            last_seen TEXT NOT NULL DEFAULT ""
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS kategori (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS produk (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT NOT NULL DEFAULT "",
            nama TEXT NOT NULL,
            kategori_id INTEGER NULL,
            harga REAL NOT NULL DEFAULT 0,
            harga_member REAL NOT NULL DEFAULT 0,
            satuan TEXT NOT NULL DEFAULT "porsi",
            deskripsi TEXT NOT NULL DEFAULT "",
            foto_url TEXT NOT NULL DEFAULT "",
            favorit INTEGER NOT NULL DEFAULT 0,
            stok INTEGER NOT NULL DEFAULT -1,
            aktif INTEGER NOT NULL DEFAULT 1,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS meja (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nomor TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL DEFAULT "",
            token TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1,
            urutan INTEGER NOT NULL DEFAULT 0
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS pelanggan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            telepon TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL DEFAULT "",
            member INTEGER NOT NULL DEFAULT 1,
            poin REAL NOT NULL DEFAULT 0,
            total_belanja REAL NOT NULL DEFAULT 0,
            jumlah_kunjungan INTEGER NOT NULL DEFAULT 0,
            catatan TEXT NOT NULL DEFAULT "",
            terakhir TEXT NULL,
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS pesanan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT NOT NULL,
            nomor INTEGER NOT NULL DEFAULT 0,
            tanggal TEXT NOT NULL,
            meja_id INTEGER NULL,
            pelanggan_id INTEGER NULL,
            nama_pelanggan TEXT NOT NULL DEFAULT "",
            telepon TEXT NOT NULL DEFAULT "",
            sumber TEXT NOT NULL DEFAULT "kasir",
            metode_bayar TEXT NOT NULL DEFAULT "TUNAI",
            status_bayar TEXT NOT NULL DEFAULT "BELUM",
            status TEXT NOT NULL DEFAULT "BARU",
            subtotal REAL NOT NULL DEFAULT 0,
            diskon REAL NOT NULL DEFAULT 0,
            pajak REAL NOT NULL DEFAULT 0,
            service REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL DEFAULT 0,
            dibayar REAL NOT NULL DEFAULT 0,
            kembali REAL NOT NULL DEFAULT 0,
            poin_dapat REAL NOT NULL DEFAULT 0,
            catatan TEXT NOT NULL DEFAULT "",
            token TEXT NOT NULL DEFAULT "",
            kasir TEXT NOT NULL DEFAULT "",
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            bayar_oleh TEXT NOT NULL DEFAULT "",
            bayar_at TEXT NULL,
            selesai_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS pesanan_item (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pesanan_id INTEGER NOT NULL,
            produk_id INTEGER NULL,
            kategori_id INTEGER NULL,
            nama TEXT NOT NULL,
            harga REAL NOT NULL DEFAULT 0,
            qty REAL NOT NULL DEFAULT 1,
            catatan TEXT NOT NULL DEFAULT "",
            subtotal REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS pesanan_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pesanan_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "",
            catatan TEXT NOT NULL DEFAULT "",
            oleh TEXT NOT NULL DEFAULT "",
            waktu TEXT NOT NULL DEFAULT ""
        )');

        /* Penomoran pesanan per hari (per kode/prefiks). */
        $pdo->exec('CREATE TABLE IF NOT EXISTS nomor_counter (
            nama TEXT PRIMARY KEY,
            periode TEXT NOT NULL DEFAULT "",
            nilai INTEGER NOT NULL DEFAULT 0
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS log_admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            waktu TEXT NOT NULL DEFAULT "",
            oleh TEXT NOT NULL DEFAULT "",
            aksi TEXT NOT NULL DEFAULT "",
            rincian TEXT NOT NULL DEFAULT ""
        )');

        /* --- Migrasi kolom (aman diulang) --- */
        tambah_kolom($pdo, 'pesanan', 'poin_dapat', 'REAL NOT NULL DEFAULT 0');
        tambah_kolom($pdo, 'produk', 'harga_member', 'REAL NOT NULL DEFAULT 0');

        seed_awal($pdo);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

/* ------------------------------------------------------------------ */
/* Data awal                                                          */
/* ------------------------------------------------------------------ */

function schema_setting(PDO $pdo, string $kunci, string $nilai): void
{
    $st = $pdo->prepare('INSERT OR IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)');
    $st->execute([$kunci, $nilai]);
}

function nama_unik(PDO $pdo, string $tabel, string $kolom, string $dasar, int $abaikan = 0): string
{
    $i = 0;
    do {
        $coba = $i === 0 ? $dasar : $dasar . $i;
        $st = $pdo->prepare('SELECT COUNT(*) FROM ' . $tabel . ' WHERE lower(' . $kolom . ') = lower(?) AND id <> ?');
        $st->execute([$coba, $abaikan]);
        $i++;
    } while ((int) $st->fetchColumn() > 0);
    return $coba;
}

function seed_awal(PDO $pdo): void
{
    /* --- Setelan bawaan --- */
    $setelan = [
        'nama_kafe'        => 'KOPI SENJA',
        'tagline_kafe'     => 'Coffee & Kitchen',
        'alamat_kafe'      => 'Jl. Merdeka No. 10, Kota Anda',
        'telepon_kafe'     => '0812-0000-0000',
        'logo_url'         => '',
        'zona_waktu'       => 'Asia/Makassar',
        'mata_uang'        => 'Rp',
        'pajak_persen'     => '0',
        'pajak_aktif'      => '1',
        'service_persen'   => '0',
        'service_aktif'    => '0',
        'diskon_member_persen' => '0',
        'pembulatan'       => '0',
        'poin_aktif'       => '1',
        'poin_per_rupiah'  => '10000',
        'poin_nilai'       => '1',
        'kode_pesanan_prefix' => 'K',
        'alamat_publik'    => '',
        'qris_aktif'       => '1',
        'qris_gambar_url'  => '',
        'qris_catatan'     => 'Scan QRIS di meja/kasir, lalu kasir akan menandai pembayaran.',
        'tunai_aktif'      => '1',
        'printer_kertas'   => 'thermal80',
        'printer_otomatis' => '1',
        'struk_judul'      => 'STRUK PEMBAYARAN',
        'struk_catatan'    => 'Terima kasih atas kunjungan Anda. Selamat menikmati!',
        'struk_tampil_logo' => '1',
        'display_judul'    => 'MENU KAFE',
        'display_footer'   => 'Selamat menikmati sajian kami — terima kasih atas kunjungan Anda',
        'display_kecepatan' => '38',
        'display_kolom'    => '3',
        'display_panel_siap' => '1',
        'display_tampil_harga' => '1',
        'dapur_kolom_siap' => '1',
        'urut_pesanan'     => 'dapur',
        'tema_warna'       => '#B4763B',
        'demo_produk'      => '0',
    ];
    foreach ($setelan as $k => $v) {
        schema_setting($pdo, $k, $v);
    }

    /* --- Akun bawaan: owner + kasir + dapur --- */
    $jml = (int) $pdo->query('SELECT COUNT(*) FROM user')->fetchColumn();
    if ($jml === 0) {
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare('INSERT INTO user (username, nama, password_hash, role, aktif, created_at, updated_at)
                              VALUES (?, ?, ?, ?, 1, ?, ?)');
        $ins->execute(['owner', 'Pemilik Kafe', password_hash('owner', PASSWORD_DEFAULT), 'owner', $now, $now]);
        $ins->execute(['kasir', 'Kasir', password_hash('kasir', PASSWORD_DEFAULT), 'kasir', $now, $now]);
        $ins->execute(['dapur', 'Petugas Dapur', password_hash('dapur', PASSWORD_DEFAULT), 'dapur', $now, $now]);
    }

    /* --- Meja 1-10 beserta token QR --- */
    $jmlMeja = (int) $pdo->query('SELECT COUNT(*) FROM meja')->fetchColumn();
    if ($jmlMeja === 0) {
        $ins = $pdo->prepare('INSERT INTO meja (nomor, nama, token, aktif, urutan) VALUES (?, ?, ?, 1, ?)');
        for ($i = 1; $i <= 10; $i++) {
            $ins->execute([(string) $i, 'Meja ' . $i, bin2hex(random_bytes(10)), $i]);
        }
    }

    /* --- Kategori + produk contoh (sekali saja, bisa dihapus dari Pengaturan) --- */
    $jmlKategori = (int) $pdo->query('SELECT COUNT(*) FROM kategori')->fetchColumn();
    if ($jmlKategori === 0) {
        $kategori = [['Kopi', 1], ['Non-Kopi', 2], ['Makanan', 3], ['Snack & Dessert', 4]];
        $ins = $pdo->prepare('INSERT INTO kategori (nama, urutan, aktif) VALUES (?, ?, 1)');
        foreach ($kategori as $k) {
            $ins->execute([$k[0], $k[1]]);
        }
        $peta = [];
        foreach ($pdo->query('SELECT id, nama FROM kategori') as $r) {
            $peta[(string) $r['nama']] = (int) $r['id'];
        }
        $contoh = [
            ['Espresso', 'Kopi', 18000, 'Kopi hitam pekat, 30 ml'],
            ['Americano', 'Kopi', 22000, 'Espresso + air panas/es dingin'],
            ['Kopi Susu Gula Aren', 'Kopi', 25000, 'Signature kafe, manis legit'],
            ['Cappuccino', 'Kopi', 27000, 'Espresso + susu berbusa'],
            ['Kopi Tubruk', 'Kopi', 15000, 'Kopi tradisional'],
            ['Matcha Latte', 'Non-Kopi', 28000, 'Matcha premium + susu'],
            ['Chocolate', 'Non-Kopi', 24000, 'Cokelat panas/dingin'],
            ['Teh Lemon', 'Non-Kopi', 18000, 'Teh + perasan lemon'],
            ['Air Mineral', 'Non-Kopi', 8000, 'Botol 600 ml'],
            ['Nasi Goreng Kafe', 'Makanan', 32000, 'Nasi goreng spesial + telur'],
            ['Mie Goreng Spesial', 'Makanan', 30000, 'Mie goreng + telur & sayur'],
            ['Ayam Geprek Sambal Ijo', 'Makanan', 35000, 'Ayam crispy + sambal ijo'],
            ['Roti Bakar Keju', 'Makanan', 20000, 'Roti bakar + keju mozarella'],
            ['Kentang Goreng', 'Snack & Dessert', 22000, 'French fries + saus'],
            ['Pisang Goreng Madu', 'Snack & Dessert', 21000, 'Pisang goreng + madu'],
            ['Cheesecake Slice', 'Snack & Dessert', 30000, 'New York cheesecake'],
        ];
        $insP = $pdo->prepare('INSERT INTO produk (kode, nama, kategori_id, harga, satuan, deskripsi, urutan, dibuat_oleh, created_at, updated_at)
                               VALUES (?, ?, ?, ?, "porsi", ?, ?, "seed", ?, ?)');
        $now = date('Y-m-d H:i:s');
        $u = 1;
        foreach ($contoh as $c) {
            $insP->execute([sprintf('P%03d', $u), $c[0], $peta[$c[1]] ?? null, $c[2], $c[3], $u, $now, $now]);
            $u++;
        }
        $pdo->exec('UPDATE pengaturan SET nilai = "1" WHERE kunci = "demo_produk"');
    }
}
