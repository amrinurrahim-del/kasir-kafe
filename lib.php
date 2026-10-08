<?php
declare(strict_types=1);

/*
 * Helper Aplikasi Kasir Kafe: pengaturan, produk, pelanggan, pesanan, pembayaran,
 * laporan, unggah media, dan kerangka tampilan.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ------------------------------------------------------------------ */
/* Pengaturan                                                          */
/* ------------------------------------------------------------------ */

function all_settings(bool $reload = false): array
{
    static $s = null;
    if ($s === null || $reload) {
        $s = [];
        foreach (db()->query('SELECT kunci, nilai FROM pengaturan') as $r) {
            $s[$r['kunci']] = (string) $r['nilai'];
        }
    }
    return $s;
}

function setting(string $k, string $default = ''): string
{
    $s = all_settings();
    return array_key_exists($k, $s) ? $s[$k] : $default;
}

function setting_bool(string $k, bool $default = false): bool
{
    $s = all_settings();
    if (!array_key_exists($k, $s)) {
        return $default;
    }
    return in_array(strtolower($s[$k]), ['1', 'true', 'ya', 'on'], true);
}

function setting_num(string $k, float $default = 0): float
{
    $s = all_settings();
    if (!array_key_exists($k, $s) || trim($s[$k]) === '') {
        return $default;
    }
    return (float) $s[$k];
}

function set_setting(string $k, string $v): void
{
    db()->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
                   ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai')->execute([$k, $v]);
    all_settings(true);
}

/** Simpan banyak setelan dalam satu transaksi. */
function set_setting_banyak(array $pasangan): void
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $st = $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
                             ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai');
        foreach ($pasangan as $k => $v) {
            $st->execute([(string) $k, (string) $v]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
    all_settings(true);
}

/* ------------------------------------------------------------------ */
/* Waktu & format                                                      */
/* ------------------------------------------------------------------ */

function hari_ini(): string
{
    return date('Y-m-d');
}

function jam_teks(?string $sqlDatetime, bool $denganDetik = true): string
{
    if (!$sqlDatetime) {
        return '-';
    }
    $t = strtotime($sqlDatetime);
    return $t ? date($denganDetik ? 'H:i:s' : 'H:i', $t) : '-';
}

/** Terima dd/mm/yyyy maupun yyyy-mm-dd; kembalikan yyyy-mm-dd ('' bila tidak sah). */
function tanggal_valid(?string $t): string
{
    $t = trim((string) $t);
    if ($t === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m)) {
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $t : '';
    }
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $t, $m)) {
        return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]) : '';
    }
    return '';
}

function tgl_label(string $ymd, bool $denganHari = false): string
{
    $t = strtotime($ymd);
    if (!$t) {
        return $ymd;
    }
    $s = date('d', $t) . ' ' . BULAN_ID[(int) date('n', $t)] . ' ' . date('Y', $t);
    if ($denganHari) {
        $s = HARI_PENDEK[(int) date('N', $t)] . ', ' . $s;
    }
    return $s;
}

function bulan_label(string $ym): string
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
        return $ym;
    }
    return BULAN_ID[(int) $m[2]] . ' ' . $m[1];
}

/** Nilai untuk kolom tanggal pada formulir (dd/mm/yyyy). */
function tgl_input(string $ymd): string
{
    $t = strtotime($ymd);
    return $t ? date('d/m/Y', $t) : '';
}

function rupiah($n, bool $denganPrefix = true): string
{
    $s = number_format((float) $n, 0, ',', '.');
    return $denganPrefix ? (setting('mata_uang', 'Rp') . ' ' . $s) : $s;
}

function angka_qty($n): string
{
    $f = (float) $n;
    return $f == (int) $f ? (string) (int) $f : number_format($f, 2, ',', '.');
}

/** Potong teks tanpa memotong di tengah karakter UTF-8 (tanpa mbstring). */
function potong_teks(string $s, int $maks = 70): string
{
    $s = trim($s);
    if (strlen($s) <= $maks) {
        return $s;
    }
    $p = substr($s, 0, $maks);
    while ($p !== '' && (ord(substr($p, -1)) & 0xC0) === 0x80) {
        $p = substr($p, 0, -1);
    }
    return rtrim($p) . '...';
}

/* ------------------------------------------------------------------ */
/* Ikon                                                                */
/* ------------------------------------------------------------------ */

function icon(string $nama, string $kelas = ''): string
{
    static $p = null;
    if ($p === null) {
        $p = [
            'grid'    => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
            'cash'    => '<path d="M3 6h18v12H3zm9 2.5A3.5 3.5 0 1 0 12 15.5 3.5 3.5 0 0 0 12 8.5zM5 8v8h2V8zm12 0v8h2V8z"/>',
            'box'     => '<path d="M12 2 3 6.5V17l9 5 9-5V6.5zm0 2.2 6.1 3-6.1 3-6.1-3zM5 8.6l6 2.9V20l-6-3.3zm14 0v8.1l-6 3.3v-8.6z"/>',
            'chart'   => '<path d="M4 20h16v2H4zM6 9h3v9H6zm4.5-5h3v14h-3zM15 12h3v6h-3z"/>',
            'receipt' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2zM8 6h8v2H8zm0 4h8v2H8zm0 4h5v2H8z"/>',
            'users'   => '<path d="M9 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7zm7.5 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM2 20c0-2.8 3.1-5 7-5s7 2.2 7 5v1H2zm14.6-4.6c2 .6 3.4 1.9 3.4 3.6v1h-3.6v-1c0-1.2-.5-2.3-1.3-3.2z"/>',
            'cog'     => '<path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm9 4-.1 1.4 1.7 1.3-1.4 2.4-2-.7-1.2.7-.3 2.1h-2.8l-.3-2.1-1.2-.7-2 .7-1.4-2.4 1.7-1.3-.1-1.4.1-1.4L9.2 9.3l1.4-2.4 2 .7 1.2-.7.3-2.1h2.8l.3 2.1 1.2.7 2-.7 1.4 2.4-1.7 1.3z"/>',
            'out'     => '<path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5zm4.6 3.4L13.2 7.8l3.2 3.2H8v2h8.4l-3.2 3.2 1.4 1.4L20 12z"/>',
            'user'    => '<path d="M12 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm0 10c4.4 0 8 2.2 8 5v1H4v-1c0-2.8 3.6-5 8-5z"/>',
            'tv'      => '<path d="M3 5h18a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-7l2 3h-2.4L12 18.7 10.4 21H8l2-3H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/>',
            'chef'    => '<path d="M7 3a4 4 0 0 1 3.6 2.2A3.5 3.5 0 0 1 15 7.5V9H8.5A4.5 4.5 0 0 1 7 3zm-2 8h14v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1zm5 2v5h2v-5z"/>',
            'plus'    => '<path d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/>',
            'print'   => '<path d="M7 3h10v4H7zM5 8h14a2 2 0 0 1 2 2v6h-4v5H7v-5H3v-6a2 2 0 0 1 2-2zm4 6v5h6v-5z"/>',
            'search'  => '<path d="M10 3a7 7 0 1 1-4.3 12.5l-3.6 3.6-1.4-1.4 3.6-3.6A7 7 0 0 1 10 3zm0 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10z"/>',
            'qr'      => '<path d="M3 3h8v8H3zm2 2v4h4V5zm8-2h8v8h-8zm2 2v4h4V5zM3 13h8v8H3zm2 2v4h4v-4zm11-2h2v2h-2zm3 0h2v4h-2zm-3 3h2v3h-2zm3 3h2v2h-2zm-5 0h3v2h-3z"/>',
            'cart'    => '<path d="M4 3h3l2.6 11H19l2-8H8"/><path d="M9 20a1.6 1.6 0 1 1 0-3.2A1.6 1.6 0 0 1 9 20zm8 0a1.6 1.6 0 1 1 0-3.2A1.6 1.6 0 0 1 17 20z"/>',
            'check'   => '<path d="M9.5 16.2 5.3 12l-1.4 1.4 5.6 5.6L20.5 7.8 19.1 6.4z"/>',
            'clock'   => '<path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20zm1 5h-2v6l4.5 2.7 1-1.7-3.5-2.1z"/>',
            'trash'   => '<path d="M9 3h6l1 2h4v2H4V5h4zM6 8h12l-1 13H7z"/>',
            'edit'    => '<path d="M4 17.2 15.4 5.8l2.8 2.8L6.8 20H4zM17 4.2l1.4-1.4a1.4 1.4 0 0 1 2 0l1.4 1.4a1.4 1.4 0 0 1 0 2L20.4 7.6z"/>',
            'download'=> '<path d="M11 3h2v9.2l3.3-3.3 1.4 1.4L12 16 6.3 10.3l1.4-1.4L11 12.2zM5 18h14v2H5z"/>',
            'bell'    => '<path d="M12 2a6 6 0 0 0-6 6v4l-2 3v1h16v-1l-2-3V8a6 6 0 0 0-6-6zm0 20a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3z"/>',
            'coffee'  => '<path d="M4 5h14v3h2.5a2.5 2.5 0 0 1 0 5H18a5 5 0 0 1-5 4H9a5 5 0 0 1-5-5zm14 3v3h2.5a1 1 0 0 0 0-2zM3 19h18v2H3z"/>',
            'table'   => '<path d="M3 4h18v3H3zm2 4h14l1 12h-2l-.4-5H6.4L6 20H4z"/>',
            'monitor' => '<path d="M3 4h18v12H3zm-2 14h22v2H1zM8 18h8l1 2H7z"/>',
            'wifi'    => '<path d="M12 18.5a1.7 1.7 0 1 1 0 3.4 1.7 1.7 0 0 1 0-3.4zM12 13c1.9 0 3.7.7 5 2l-1.6 1.6A5.2 5.2 0 0 0 12 15.4c-1.3 0-2.5.5-3.4 1.2L7 15c1.4-1.3 3.2-2 5-2zm0-4.6c3.2 0 6.1 1.2 8.3 3.3l-1.6 1.6A9.4 9.4 0 0 0 12 10.6c-2.5 0-4.9.9-6.7 2.7L3.7 11.7c2.2-2.1 5.1-3.3 8.3-3.3zM12 3.6c4.4 0 8.4 1.7 11.4 4.5l-1.6 1.6A14.2 14.2 0 0 0 12 6.1c-3.6 0-6.9 1.3-9.4 3.6L1 8.1C4 5.3 8 3.6 12 3.6z"/>',
        ];
    }
    $d = $p[$nama] ?? $p['grid'];
    $k = $kelas !== '' ? ' class="' . h($kelas) . '"' : '';
    return '<svg viewBox="0 0 24 24" aria-hidden="true"' . $k . '>' . $d . '</svg>';
}

/* ------------------------------------------------------------------ */
/* Aset berversi (wajib — server tidak mengirim Cache-Control)          */
/* ------------------------------------------------------------------ */

function asset_versi(): int
{
    static $versi = null;
    if ($versi === null) {
        $versi = 1;
        foreach (['style.css', 'app.js', 'kasir.js', 'display.js', 'pesan.js', 'dapur.js', 'pengaturan.js', 'grafik.js', 'layar2.js'] as $f) {
            $p = __DIR__ . '/assets/' . $f;
            if (is_file($p)) {
                $versi = max($versi, (int) filemtime($p));
            }
        }
    }
    return $versi;
}

function asset(string $berkas): string
{
    return $berkas . '?v=' . asset_versi();
}

/* ------------------------------------------------------------------ */
/* Logo & identitas                                                    */
/* ------------------------------------------------------------------ */

function logo_url(): string
{
    return setting('logo_url');
}

function nama_kafe(): string
{
    return setting('nama_kafe', 'Kafe');
}

/* ------------------------------------------------------------------ */
/* Kategori & produk                                                   */
/* ------------------------------------------------------------------ */

function kategori_semua(bool $hanyaAktif = false): array
{
    $sql = 'SELECT * FROM kategori' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY urutan, nama';
    return db()->query($sql)->fetchAll();
}

function kategori_row(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM kategori WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function produk_semua(bool $hanyaAktif = false, string $cari = ''): array
{
    $sql = 'SELECT p.*, k.nama AS kategori
            FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id';
    $where = [];
    $par = [];
    if ($hanyaAktif) {
        $where[] = 'p.aktif = 1';
    }
    if (trim($cari) !== '') {
        $where[] = '(lower(p.nama) LIKE ? OR lower(p.kode) LIKE ?)';
        $q = '%' . strtolower(trim($cari)) . '%';
        $par[] = $q;
        $par[] = $q;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.urutan, p.nama';
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function produk_row(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, k.nama AS kategori FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id WHERE p.id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

/** Harga jual produk untuk seorang pelanggan (member mendapat harga member bila diisi). */
function produk_harga(array $p, bool $member = false): float
{
    if ($member && (float) $p['harga_member'] > 0) {
        return (float) $p['harga_member'];
    }
    return (float) $p['harga'];
}

/* ------------------------------------------------------------------ */
/* Meja + QR                                                           */
/* ------------------------------------------------------------------ */

function meja_semua(bool $hanyaAktif = false): array
{
    $sql = 'SELECT * FROM meja' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY urutan, CAST(nomor AS INTEGER), nomor';
    return db()->query($sql)->fetchAll();
}

function meja_row(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM meja WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function meja_label(?array $m): string
{
    if (!$m) {
        return 'Tanpa Meja';
    }
    return trim((string) $m['nama']) !== '' ? (string) $m['nama'] : ('Meja ' . $m['nomor']);
}

/* ------------------------------------------------------------------ */
/* Alamat publik (untuk QR meja)                                       */
/* ------------------------------------------------------------------ */

/**
 * Alamat dasar aplikasi untuk isi QR.
 *
 * Penting (lihat AGENTS.md): platform melepas prefiks subpath dari SCRIPT_NAME saat
 * aplikasi diakses lewat subdomain platform, sedangkan REQUEST_URI masih utuh.
 * Karena itu REQUEST_URI dipakai lebih dulu (dan hanya dipercaya bila basename-nya sama),
 * baru jatuh ke dirname(SCRIPT_NAME).
 */
function url_publik_dasar(): string
{
    $set = trim(setting('alamat_publik'));
    if ($set !== '') {
        return rtrim($set, '/');
    }
    $https  = request_https();
    $skema  = $https ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $uri    = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

    $path = '';
    if ($uri !== '') {
        $pathUri = (string) parse_url($uri, PHP_URL_PATH);
        if ($pathUri !== '' && basename($pathUri) === basename($script)) {
            $path = rtrim(str_replace('\\', '/', dirname($pathUri)), '/');
        }
    }
    if ($path === '' && $script !== '') {
        $path = rtrim(str_replace('\\', '/', dirname($script)), '/');
    }
    if ($host === '') {
        return '';
    }
    return $skema . '://' . $host . ($path === '/' ? '' : $path);
}

/** Tautan pemesanan mandiri untuk sebuah meja (dibaca dari QR di meja). */
function url_meja(array $m): string
{
    $dasar = url_publik_dasar();
    if ($dasar === '') {
        return '';
    }
    return $dasar . '/pesan.php?m=' . (int) $m['id'] . '&t=' . rawurlencode((string) $m['token']);
}

/* ------------------------------------------------------------------ */
/* Pelanggan                                                           */
/* ------------------------------------------------------------------ */

function pelanggan_semua(string $cari = '', bool $hanyaMember = false): array
{
    $sql = 'SELECT * FROM pelanggan';
    $where = [];
    $par = [];
    if ($hanyaMember) {
        $where[] = 'member = 1';
    }
    if (trim($cari) !== '') {
        $where[] = '(lower(nama) LIKE ? OR telepon LIKE ?)';
        $q = '%' . strtolower(trim($cari)) . '%';
        $par[] = $q;
        $par[] = $q;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY nama';
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function pelanggan_row(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM pelanggan WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function pelanggan_by_telepon(string $telepon): ?array
{
    $t = preg_replace('/[^0-9+]/', '', $telepon);
    if ($t === '') {
        return null;
    }
    $st = db()->prepare('SELECT * FROM pelanggan WHERE replace(replace(replace(telepon, "-", ""), " ", ""), "+", "") LIKE ? LIMIT 1');
    $st->execute(['%' . ltrim($t, '+')]);
    $r = $st->fetch();
    return $r ?: null;
}

/* ------------------------------------------------------------------ */
/* Hitung total pesanan                                                */
/* ------------------------------------------------------------------ */

/**
 * Menghitung seluruh angka pesanan dari daftar item + setelan pajak/service.
 * Semua perhitungan dilakukan di server (klien hanya mengirim produk & jumlah).
 */
function hitung_total(array $items, float $subtotal, float $diskonPersen = 0): array
{
    $diskon  = round($subtotal * max(0, $diskonPersen) / 100, 0);
    $dasar   = max(0, $subtotal - $diskon);
    $service = setting_bool('service_aktif', false) ? round($dasar * setting_num('service_persen', 0) / 100, 0) : 0;
    $pajak   = setting_bool('pajak_aktif', false) ? round(($dasar + $service) * setting_num('pajak_persen', 0) / 100, 0) : 0;
    $total   = $dasar + $service + $pajak;

    $bulat = (int) setting_num('pembulatan', 0);
    if ($bulat > 1) {
        $total = (float) ((int) ceil($total / $bulat) * $bulat);
    }
    return [
        'items'    => $items,
        'subtotal' => $subtotal,
        'diskon'   => $diskon,
        'service'  => $service,
        'pajak'    => $pajak,
        'total'    => $total,
    ];
}

/** Susun item pesanan dari masukan klien (produk divalidasi ke database). */
function pesanan_siapkan_item(array $masukan, bool $member = false): array
{
    $items = [];
    $subtotal = 0.0;
    foreach ($masukan as $m) {
        if (!is_array($m)) {
            continue;
        }
        $pid = (int) ($m['produk_id'] ?? 0);
        $qty = (float) ($m['qty'] ?? 0);
        if ($pid <= 0 || $qty <= 0) {
            continue;
        }
        $p = produk_row($pid);
        if (!$p) {
            continue;
        }
        $qty = min($qty, 999);
        $harga = produk_harga($p, $member);
        $sub = round($harga * $qty, 0);
        $items[] = [
            'produk_id'   => $pid,
            'kategori_id' => $p['kategori_id'] !== null ? (int) $p['kategori_id'] : null,
            'nama'        => (string) $p['nama'],
            'harga'       => $harga,
            'qty'         => $qty,
            'catatan'     => trim((string) ($m['catatan'] ?? '')),
            'subtotal'    => $sub,
        ];
        $subtotal += $sub;
    }
    return [$items, $subtotal];
}

/* ------------------------------------------------------------------ */
/* Pesanan                                                             */
/* ------------------------------------------------------------------ */

/** Nomor pesanan berikutnya (per hari). Dipanggil di dalam transaksi. */
function nomor_pesanan_berikutnya(PDO $pdo, string $periode): int
{
    $prefix = setting('kode_pesanan_prefix', 'K');
    $nama   = 'pesanan';
    $st = $pdo->prepare('SELECT periode, nilai FROM nomor_counter WHERE nama = ?');
    $st->execute([$nama]);
    $row = $st->fetch();
    $nilai = ($row && (string) $row['periode'] === $periode) ? (int) $row['nilai'] + 1 : 1;
    $pdo->prepare('INSERT INTO nomor_counter (nama, periode, nilai) VALUES (?, ?, ?)
                   ON CONFLICT(nama) DO UPDATE SET periode = excluded.periode, nilai = excluded.nilai')
        ->execute([$nama, $periode, $nilai]);
    return $nilai;
}

function kode_pesanan(int $nomor): string
{
    return setting('kode_pesanan_prefix', 'K') . sprintf('%03d', $nomor);
}

/**
 * Membuat pesanan baru (dari kasir maupun dari pelanggan lewat QR).
 * $masuk: meja_id, pelanggan_id, nama_pelanggan, telepon, sumber, metode_bayar,
 *         catatan, items[], diskon_persen, dibayar (opsional), oleh, daftar_member (bool)
 */
function pesanan_buat(array $masuk): array
{
    $items = $masuk['items'] ?? [];
    if (!$items) {
        return ['ok' => false, 'error' => 'Pesanan belum memiliki item.'];
    }

    $pdo = db();
    $pelangganId = (int) ($masuk['pelanggan_id'] ?? 0);
    $nama  = trim((string) ($masuk['nama_pelanggan'] ?? ''));
    $telp  = trim((string) ($masuk['telepon'] ?? ''));
    $daftarMember = !empty($masuk['daftar_member']);

    /* Pelanggan: pakai yang dipilih, atau cari berdasarkan nomor HP, atau daftarkan baru. */
    if ($pelangganId <= 0 && $telp !== '') {
        $ada = pelanggan_by_telepon($telp);
        if ($ada) {
            $pelangganId = (int) $ada['id'];
            if ($nama === '') {
                $nama = (string) $ada['nama'];
            }
        }
    }
    $member = false;
    if ($pelangganId > 0) {
        $pl = pelanggan_row($pelangganId);
        $member = $pl && (int) $pl['member'] === 1;
    }

    [$items, $subtotal] = pesanan_siapkan_item($items, $member);
    if (!$items) {
        return ['ok' => false, 'error' => 'Produk pada pesanan tidak ditemukan.'];
    }

    if ($pelangganId <= 0 && $daftarMember && $nama !== '' && $telp !== '') {
        $pelangganId = (int) pelanggan_tambah($nama, $telp, '', true, 'sistem');
        $member = true;
    }

    /* Diskon: bila kasir mengirim angka eksplisit dipakai apa adanya,
       kalau tidak otomatis memakai diskon member (bila ada). */
    $diskonPersen = array_key_exists('diskon_persen', $masuk) && $masuk['diskon_persen'] !== '' && $masuk['diskon_persen'] !== null
        ? (float) $masuk['diskon_persen']
        : ($member ? setting_num('diskon_member_persen', 0) : 0);
    $hitung = hitung_total($items, $subtotal, $diskonPersen);

    $metode = strtoupper((string) ($masuk['metode_bayar'] ?? METODE_TUNAI));
    if (!array_key_exists($metode, LABEL_METODE)) {
        $metode = METODE_TUNAI;
    }
    $sumber = ($masuk['sumber'] ?? SUMBER_KASIR) === SUMBER_PELANGGAN ? SUMBER_PELANGGAN : SUMBER_KASIR;

    $dibayar = (float) ($masuk['dibayar'] ?? 0);
    $lunas   = !empty($masuk['lunas']) && $dibayar >= $hitung['total'];
    if ($metode === METODE_QRIS && !empty($masuk['lunas'])) {
        $lunas = true;   /* QRIS: jumlah persis, tidak ada uang kembalian */
        $dibayar = $hitung['total'];
    }
    $kembali = $lunas ? max(0, $dibayar - $hitung['total']) : 0;

    $mejaId = (int) ($masuk['meja_id'] ?? 0);
    $tanggal = hari_ini();
    $token = bin2hex(random_bytes(10));

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $nomor = nomor_pesanan_berikutnya($pdo, $tanggal);
        $kode  = kode_pesanan($nomor);
        $ins = $pdo->prepare('INSERT INTO pesanan
            (kode, nomor, tanggal, meja_id, pelanggan_id, nama_pelanggan, telepon, sumber, metode_bayar,
             status_bayar, status, subtotal, diskon, pajak, service, total, dibayar, kembali, catatan,
             token, kasir, dibuat_oleh, bayar_oleh, bayar_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([
            $kode, $nomor, $tanggal,
            $mejaId > 0 ? $mejaId : null,
            $pelangganId > 0 ? $pelangganId : null,
            $nama, $telp, $sumber, $metode,
            $lunas ? BAYAR_LUNAS : BAYAR_BELUM,
            PS_BARU,
            $hitung['subtotal'], $hitung['diskon'], $hitung['pajak'], $hitung['service'], $hitung['total'],
            $dibayar, $kembali,
            trim((string) ($masuk['catatan'] ?? '')),
            $token,
            (string) ($masuk['oleh'] ?? ''),
            (string) ($masuk['oleh'] ?? ''),
            $lunas ? (string) ($masuk['oleh'] ?? '') : '',
            $lunas ? now() : null,
            now(), now(),
        ]);
        $id = (int) $pdo->lastInsertId();

        $insI = $pdo->prepare('INSERT INTO pesanan_item (pesanan_id, produk_id, kategori_id, nama, harga, qty, catatan, subtotal)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($hitung['items'] as $it) {
            $insI->execute([$id, $it['produk_id'], $it['kategori_id'], $it['nama'], $it['harga'], $it['qty'], $it['catatan'], $it['subtotal']]);
        }
        pesanan_log($id, PS_BARU, (string) ($masuk['oleh'] ?? ''), $sumber === SUMBER_PELANGGAN ? 'Pesanan dari pelanggan (QR meja)' : 'Pesanan dibuat di kasir', false);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        return ['ok' => false, 'error' => 'Gagal menyimpan pesanan: ' . $e->getMessage()];
    }

    if ($lunas) {
        pesanan_poin_berikan($id, (string) ($masuk['oleh'] ?? ''));
    }
    return ['ok' => true, 'id' => $id, 'kode' => $kode, 'token' => $token, 'nomor' => $nomor, 'total' => $hitung['total']];
}

function pesanan_log(int $pesananId, string $status, string $oleh, string $catatan = '', bool $transaksi = true): void
{
    $pdo = db();
    $pakaiTransaksi = $transaksi;
    if ($pakaiTransaksi) {
        $pdo->exec('BEGIN IMMEDIATE');
    }
    try {
        $pdo->prepare('INSERT INTO pesanan_log (pesanan_id, status, catatan, oleh, waktu) VALUES (?, ?, ?, ?, ?)')
            ->execute([$pesananId, $status, $catatan, $oleh, now()]);
        if ($pakaiTransaksi) {
            $pdo->exec('COMMIT');
        }
    } catch (Throwable $e) {
        if ($pakaiTransaksi) {
            $pdo->exec('ROLLBACK');
        }
        throw $e;
    }
}

function pesanan_row(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, m.nomor AS meja_nomor, m.nama AS meja_nama, pl.nama AS pelanggan_nama, pl.member AS pelanggan_member
                         FROM pesanan p
                         LEFT JOIN meja m ON m.id = p.meja_id
                         LEFT JOIN pelanggan pl ON pl.id = p.pelanggan_id
                         WHERE p.id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $r['items'] = pesanan_items($id);
    return $r;
}

function pesanan_by_kode_token(string $kode, string $token): ?array
{
    $st = db()->prepare('SELECT id FROM pesanan WHERE kode = ? AND token = ? LIMIT 1');
    $st->execute([trim($kode), trim($token)]);
    $id = (int) $st->fetchColumn();
    return $id > 0 ? pesanan_row($id) : null;
}

function pesanan_items(int $id): array
{
    $st = db()->prepare('SELECT * FROM pesanan_item WHERE pesanan_id = ? ORDER BY id');
    $st->execute([$id]);
    return $st->fetchAll();
}

function pesanan_label_meja(array $p): string
{
    if (empty($p['meja_id'])) {
        return 'Tanpa Meja';
    }
    $nama = trim((string) ($p['meja_nama'] ?? ''));
    if ($nama !== '') {
        return $nama;
    }
    return 'Meja ' . (string) ($p['meja_nomor'] ?? '');
}

function pesanan_ringkas(array $p): array
{
    return [
        'id'             => (int) $p['id'],
        'kode'           => (string) $p['kode'],
        'nomor'          => (int) $p['nomor'],
        'tanggal'        => (string) $p['tanggal'],
        'meja'           => pesanan_label_meja($p),
        'meja_id'        => (int) ($p['meja_id'] ?? 0),
        'nama_pelanggan' => (string) $p['nama_pelanggan'],
        'telepon'        => (string) $p['telepon'],
        'sumber'         => (string) $p['sumber'],
        'sumber_label'   => $p['sumber'] === SUMBER_PELANGGAN ? 'QR Pelanggan' : 'Kasir',
        'metode_bayar'   => (string) $p['metode_bayar'],
        'metode_label'   => LABEL_METODE[(string) $p['metode_bayar']] ?? (string) $p['metode_bayar'],
        'status_bayar'   => (string) $p['status_bayar'],
        'status'         => (string) $p['status'],
        'status_label'   => LABEL_STATUS[(string) $p['status']] ?? (string) $p['status'],
        'subtotal'       => (float) $p['subtotal'],
        'diskon'         => (float) $p['diskon'],
        'pajak'          => (float) $p['pajak'],
        'service'        => (float) $p['service'],
        'total'          => (float) $p['total'],
        'total_angka'    => (float) $p['total'],
        'total_teks'     => rupiah($p['total']),
        'dibayar'        => (float) $p['dibayar'],
        'kembali'        => (float) $p['kembali'],
        'catatan'        => (string) $p['catatan'],
        'jam'            => jam_teks((string) $p['created_at'], false),
        'waktu'          => jam_teks((string) $p['created_at']),
        'siap_at'        => $p['selesai_at'] ? jam_teks((string) $p['selesai_at'], false) : '',
        'items'          => array_map(static function (array $it): array {
            return [
                'nama'     => (string) $it['nama'],
                'qty'      => (float) $it['qty'],
                'qty_teks' => angka_qty($it['qty']),
                'harga'    => (float) $it['harga'],
                'catatan'  => (string) $it['catatan'],
                'subtotal' => (float) $it['subtotal'],
            ];
        }, $p['items'] ?? []),
    ];
}

/** Beri poin + perbarui statistik pelanggan (dipanggil sekali saat pesanan LUNAS). */
function pesanan_poin_berikan(int $pesananId, string $oleh): void
{
    if (!setting_bool('poin_aktif', true)) {
        return;
    }
    $st = db()->prepare('SELECT * FROM pesanan WHERE id = ?');
    $st->execute([$pesananId]);
    $p = $st->fetch();
    if (!$p || (int) ($p['pelanggan_id'] ?? 0) <= 0) {
        return;
    }
    $per = setting_num('poin_per_rupiah', 10000);
    if ($per <= 0) {
        return;
    }
    $poin = floor(((float) $p['total']) / $per) * setting_num('poin_nilai', 1);

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('UPDATE pelanggan SET poin = poin + ?, total_belanja = total_belanja + ?, jumlah_kunjungan = jumlah_kunjungan + 1, terakhir = ?, updated_at = ? WHERE id = ?')
            ->execute([$poin, (float) $p['total'], now(), now(), (int) $p['pelanggan_id']]);
        $pdo->prepare('UPDATE pesanan SET poin_dapat = ? WHERE id = ?')->execute([$poin, $pesananId]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
    }
}

/** Tandai pesanan sudah dibayar (tunai atau QRIS). */
function pesanan_bayar(int $id, string $metode, float $dibayar, string $oleh): array
{
    $p = pesanan_row($id);
    if (!$p) {
        return ['ok' => false, 'error' => 'Pesanan tidak ditemukan.'];
    }
    if ((string) $p['status_bayar'] === BAYAR_LUNAS) {
        return ['ok' => false, 'error' => 'Pesanan ini sudah dibayar.'];
    }
    if ((string) $p['status'] === PS_BATAL) {
        return ['ok' => false, 'error' => 'Pesanan sudah dibatalkan.'];
    }
    $metode = strtoupper($metode);
    if (!array_key_exists($metode, LABEL_METODE)) {
        $metode = (string) $p['metode_bayar'];
    }
    $total = (float) $p['total'];
    if ($metode === METODE_QRIS) {
        $dibayar = $total;
        $kembali = 0;
    } else {
        if ($dibayar <= 0) {
            $dibayar = $total;
        }
        if ($dibayar + 0.01 < $total) {
            return ['ok' => false, 'error' => 'Uang diterima kurang dari total tagihan (' . rupiah($total) . ').'];
        }
        $kembali = max(0, $dibayar - $total);
    }

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('UPDATE pesanan SET status_bayar = ?, metode_bayar = ?, dibayar = ?, kembali = ?, bayar_oleh = ?, bayar_at = ?, updated_at = ? WHERE id = ?')
            ->execute([BAYAR_LUNAS, $metode, $dibayar, $kembali, $oleh, now(), now(), $id]);
        $pdo->prepare('INSERT INTO pesanan_log (pesanan_id, status, catatan, oleh, waktu) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, (string) $p['status'], 'Pembayaran ' . (LABEL_METODE[$metode] ?? $metode) . ' diterima (' . rupiah($total) . ')', $oleh, now()]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        return ['ok' => false, 'error' => 'Gagal menyimpan pembayaran.'];
    }
    pesanan_poin_berikan($id, $oleh);
    return ['ok' => true, 'kembali' => $kembali];
}

/**
 * Ubah status pesanan (dapur). $arah = 'maju' | 'mundur' atau nama status langsung.
 */
function pesanan_status_ubah(int $id, string $status, string $oleh, string $arah = 'maju'): array
{
    $p = pesanan_row($id);
    if (!$p) {
        return ['ok' => false, 'error' => 'Pesanan tidak ditemukan.'];
    }
    $kini = (string) $p['status'];
    if ($kini === PS_BATAL) {
        return ['ok' => false, 'error' => 'Pesanan sudah dibatalkan.'];
    }
    $urutan = URUT_STATUS;
    if ($arah === 'maju' || $arah === 'mundur') {
        $pos = $urutan[$kini] ?? 1;
        $pos += $arah === 'maju' ? 1 : -1;
        $pos = max(1, min(5, $pos));
        $peta = array_flip($urutan);
        $status = (string) $peta[$pos];
    }
    if (!array_key_exists($status, $urutan)) {
        return ['ok' => false, 'error' => 'Status tidak dikenal.'];
    }
    $selesai = $status === PS_SELESAI ? now() : null;

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if ($selesai !== null) {
            $pdo->prepare('UPDATE pesanan SET status = ?, selesai_at = COALESCE(selesai_at, ?), updated_at = ? WHERE id = ?')
                ->execute([$status, $selesai, now(), $id]);
        } else {
            $pdo->prepare('UPDATE pesanan SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, now(), $id]);
        }
        $pdo->prepare('INSERT INTO pesanan_log (pesanan_id, status, catatan, oleh, waktu) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $status, '', $oleh, now()]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        return ['ok' => false, 'error' => 'Gagal mengubah status pesanan.'];
    }
    return ['ok' => true, 'status' => $status, 'status_label' => LABEL_STATUS[$status]];
}

function pesanan_batal(int $id, string $oleh, string $alasan = ''): array
{
    $p = pesanan_row($id);
    if (!$p) {
        return ['ok' => false, 'error' => 'Pesanan tidak ditemukan.'];
    }
    if ((string) $p['status_bayar'] === BAYAR_LUNAS) {
        return ['ok' => false, 'error' => 'Pesanan yang sudah dibayar tidak dapat dibatalkan. Gunakan fitur hapus data bila memang salah input.'];
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('UPDATE pesanan SET status = ?, updated_at = ? WHERE id = ?')->execute([PS_BATAL, now(), $id]);
        $pdo->prepare('INSERT INTO pesanan_log (pesanan_id, status, catatan, oleh, waktu) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, PS_BATAL, $alasan, $oleh, now()]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        return ['ok' => false, 'error' => 'Gagal membatalkan pesanan.'];
    }
    return ['ok' => true];
}

/** Daftar pesanan pada rentang tanggal dengan filter opsional. */
function pesanan_daftar(string $dari, string $sampai, array $opt = []): array
{
    $sql = 'SELECT p.*, m.nomor AS meja_nomor, m.nama AS meja_nama
            FROM pesanan p LEFT JOIN meja m ON m.id = p.meja_id
            WHERE p.tanggal >= ? AND p.tanggal <= ?';
    $par = [$dari, $sampai];
    if (!empty($opt['status'])) {
        $sql .= ' AND p.status = ?';
        $par[] = (string) $opt['status'];
    }
    if (isset($opt['bayar']) && $opt['bayar'] !== '') {
        $sql .= ' AND p.status_bayar = ?';
        $par[] = (string) $opt['bayar'];
    }
    if (!empty($opt['metode'])) {
        $sql .= ' AND p.metode_bayar = ?';
        $par[] = (string) $opt['metode'];
    }
    if (!empty($opt['cari'])) {
        $sql .= ' AND (lower(p.kode) LIKE ? OR lower(p.nama_pelanggan) LIKE ? OR p.telepon LIKE ?)';
        $q = '%' . strtolower(trim((string) $opt['cari'])) . '%';
        $par[] = $q;
        $par[] = $q;
        $par[] = $q;
    }
    if (!empty($opt['batas'])) {
        $sql .= ' ORDER BY p.created_at DESC LIMIT ' . (int) $opt['batas'];
    } else {
        $sql .= ' ORDER BY p.created_at DESC';
    }
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function laporan_ringkas(array $rows, bool $hanyaLunas = true): array
{
    $total = 0.0;
    $jumlah = 0;
    $perMetode = [];
    $perHari = [];
    $perKasir = [];
    foreach ($rows as $r) {
        if ($hanyaLunas && (string) $r['status_bayar'] !== BAYAR_LUNAS) {
            continue;
        }
        if ((string) $r['status'] === PS_BATAL) {
            continue;
        }
        $jumlah++;
        $total += (float) $r['total'];
        $m = (string) $r['metode_bayar'];
        $perMetode[$m] = ($perMetode[$m] ?? 0) + (float) $r['total'];
        $h = (string) $r['tanggal'];
        $perHari[$h] = ($perHari[$h] ?? 0) + (float) $r['total'];
        $k = (string) $r['bayar_oleh'] !== '' ? (string) $r['bayar_oleh'] : (string) $r['dibuat_oleh'];
        $perKasir[$k] = ($perKasir[$k] ?? 0) + (float) $r['total'];
    }
    ksort($perHari);
    return [
        'transaksi'   => $jumlah,
        'total'       => $total,
        'rata'        => $jumlah > 0 ? $total / $jumlah : 0,
        'per_metode'  => $perMetode,
        'per_hari'    => $perHari,
        'per_kasir'   => $perKasir,
    ];
}

/** Ringkasan penjualan per produk + per kategori pada rentang tanggal. */
function laporan_produk(string $dari, string $sampai, array $opt = []): array
{
    $sql = 'SELECT i.nama, i.qty, i.subtotal, i.kategori_id, p.status_bayar
            FROM pesanan_item i JOIN pesanan p ON p.id = i.pesanan_id
            WHERE p.tanggal >= ? AND p.tanggal <= ? AND p.status <> ?';
    $par = [$dari, $sampai, PS_BATAL];
    if (!empty($opt['hanya_lunas'])) {
        $sql .= ' AND p.status_bayar = ?';
        $par[] = BAYAR_LUNAS;
    }
    $st = db()->prepare($sql);
    $st->execute($par);
    $perProduk = [];
    $perKategori = [];
    $petaKat = [];
    foreach (kategori_semua() as $k) {
        $petaKat[(int) $k['id']] = (string) $k['nama'];
    }
    foreach ($st->fetchAll() as $r) {
        $nama = (string) $r['nama'];
        if (!isset($perProduk[$nama])) {
            $perProduk[$nama] = ['nama' => $nama, 'qty' => 0.0, 'total' => 0.0];
        }
        $perProduk[$nama]['qty'] += (float) $r['qty'];
        $perProduk[$nama]['total'] += (float) $r['subtotal'];

        $kat = (int) ($r['kategori_id'] ?? 0);
        $namaKat = $petaKat[$kat] ?? 'Lain-lain';
        if (!isset($perKategori[$namaKat])) {
            $perKategori[$namaKat] = ['nama' => $namaKat, 'qty' => 0.0, 'total' => 0.0];
        }
        $perKategori[$namaKat]['qty'] += (float) $r['qty'];
        $perKategori[$namaKat]['total'] += (float) $r['subtotal'];
    }
    usort($perProduk, static fn($a, $b) => $b['total'] <=> $a['total']);
    usort($perKategori, static fn($a, $b) => $b['total'] <=> $a['total']);
    return ['produk' => $perProduk, 'kategori' => $perKategori];
}

/** Data grafik: omzet harian, per jam, per kategori, per metode. */
function grafik_data(string $dari, string $sampai): array
{
    $st = db()->prepare('SELECT * FROM pesanan WHERE tanggal >= ? AND tanggal <= ? AND status <> ?');
    $st->execute([$dari, $sampai, PS_BATAL]);
    $rows = $st->fetchAll();

    $harian = [];
    $perJam = array_fill(0, 24, 0.0);
    $perMetode = [];
    $perMeja = [];
    $jumlahHarian = [];
    $t = strtotime($dari);
    $akhir = strtotime($sampai);
    while ($t !== false && $t <= $akhir) {
        $harian[date('Y-m-d', $t)] = 0.0;
        $jumlahHarian[date('Y-m-d', $t)] = 0;
        $t = strtotime('+1 day', $t);
    }
    $totalLunas = 0.0;
    $jumlahLunas = 0;
    foreach ($rows as $r) {
        if ((string) $r['status_bayar'] !== BAYAR_LUNAS) {
            continue;
        }
        $h = (string) $r['tanggal'];
        $harian[$h] = ($harian[$h] ?? 0) + (float) $r['total'];
        $jumlahHarian[$h] = ($jumlahHarian[$h] ?? 0) + 1;
        $jam = (int) date('G', strtotime((string) $r['created_at']));
        $perJam[$jam] += (float) $r['total'];
        $m = (string) $r['metode_bayar'];
        $perMetode[$m] = ($perMetode[$m] ?? 0) + (float) $r['total'];
        $meja = pesanan_label_meja($r);
        $perMeja[$meja] = ($perMeja[$meja] ?? 0) + (float) $r['total'];
        $totalLunas += (float) $r['total'];
        $jumlahLunas++;
    }
    arsort($perMeja);
    $produk = laporan_produk($dari, $sampai, ['hanya_lunas' => true]);
    return [
        'harian'        => $harian,
        'jumlah_harian' => $jumlahHarian,
        'per_jam'       => $perJam,
        'per_metode'    => $perMetode,
        'per_meja'      => array_slice($perMeja, 0, 8, true),
        'per_kategori'  => $produk['kategori'],
        'top_produk'    => array_slice($produk['produk'], 0, 8),
        'total'         => $totalLunas,
        'transaksi'     => $jumlahLunas,
    ];
}

/* ------------------------------------------------------------------ */
/* Data untuk display & dapur                                          */
/* ------------------------------------------------------------------ */

/** Sidik jari pesanan hari ini — dipakai halaman dapur/kasir untuk tahu kapan perlu memuat ulang. */
function pesanan_stamp(): string
{
    $st = db()->query('SELECT id, status, status_bayar, updated_at FROM pesanan WHERE tanggal = "' . hari_ini() . '" ORDER BY id');
    $bagian = [];
    foreach ($st as $r) {
        $bagian[] = $r['id'] . ':' . $r['status'] . ':' . $r['status_bayar'];
    }
    return md5(implode('|', $bagian));
}

/** Data katalog menu untuk display/monitor 2 dan halaman pesan (publik). */
function payload_menu(): array
{
    $kategori = [];
    foreach (kategori_semua(true) as $k) {
        $kategori[] = ['id' => (int) $k['id'], 'nama' => (string) $k['nama']];
    }
    $produk = [];
    foreach (produk_semua(true) as $p) {
        $produk[] = [
            'id'         => (int) $p['id'],
            'nama'       => (string) $p['nama'],
            'kategori'   => (string) ($p['kategori'] ?? 'Lain-lain'),
            'kategori_id'=> (int) ($p['kategori_id'] ?? 0),
            'harga'      => (float) $p['harga'],
            'harga_teks' => rupiah($p['harga']),
            'harga_member' => (float) $p['harga_member'],
            'harga_member_teks' => (float) $p['harga_member'] > 0 ? rupiah($p['harga_member']) : '',
            'deskripsi'  => (string) $p['deskripsi'],
            'foto'       => (string) $p['foto_url'],
            'favorit'    => (int) $p['favorit'] === 1,
        ];
    }
    return [
        'nama_kafe' => nama_kafe(),
        'tagline'   => setting('tagline_kafe'),
        'logo'      => logo_url(),
        'kategori'  => $kategori,
        'produk'    => $produk,
        'stamp'     => menu_stamp(),
    ];
}

function menu_stamp(): string
{
    $bagian = [];
    $st = db()->query('SELECT id, nama, harga, harga_member, foto_url, aktif, kategori_id, favorit FROM produk ORDER BY id');
    foreach ($st as $r) {
        $bagian[] = implode(':', array_map('strval', array_values($r)));
    }
    foreach (kategori_semua() as $k) {
        $bagian[] = 'kat:' . $k['id'] . ':' . $k['nama'] . ':' . $k['aktif'];
    }
    return md5(implode('|', $bagian));
}

/** Pesanan yang siap disajikan / selesai (untuk papan display). */
function payload_siap(): array
{
    $out = [];
    $st = db()->prepare('SELECT p.*, m.nomor AS meja_nomor, m.nama AS meja_nama
                         FROM pesanan p LEFT JOIN meja m ON m.id = p.meja_id
                         WHERE p.tanggal = ? AND p.status IN (?, ?)
                         ORDER BY p.updated_at DESC LIMIT 24');
    $st->execute([hari_ini(), PS_SIAP, PS_SELESAI]);
    foreach ($st->fetchAll() as $p) {
        $out[] = ['kode' => (string) $p['kode'], 'meja' => pesanan_label_meja($p), 'status' => (string) $p['status']];
    }
    return $out;
}

/** Data papan dapur: pesanan hari ini dikelompokkan per status. */
function payload_dapur(): array
{
    $st = db()->prepare('SELECT p.*, m.nomor AS meja_nomor, m.nama AS meja_nama
                         FROM pesanan p LEFT JOIN meja m ON m.id = p.meja_id
                         WHERE p.tanggal = ? AND p.status <> ?
                         ORDER BY p.created_at ASC');
    $st->execute([hari_ini(), PS_BATAL]);
    $rows = $st->fetchAll();

    $kolom = [PS_BARU => [], PS_DITERIMA => [], PS_DISIAPKAN => [], PS_SIAP => []];
    $selesai = [];
    foreach ($rows as $p) {
        $p['items'] = pesanan_items((int) $p['id']);
        $r = pesanan_ringkas($p);
        $r['log'] = pesanan_log_terakhir((int) $p['id'], 4);
        $stt = (string) $p['status'];
        if ($stt === PS_SELESAI) {
            $selesai[] = $r;
        } elseif (isset($kolom[$stt])) {
            $kolom[$stt][] = $r;
        }
    }
    usort($selesai, static fn($a, $b) => strcmp((string) $b['siap_at'], (string) $a['siap_at']));
    return [
        'ok'      => true,
        'stamp'   => pesanan_stamp(),
        'jumlah'  => array_map('count', $kolom),
        'kolom'   => $kolom,
        'selesai' => array_slice($selesai, 0, 20),
        'jam'     => date('H:i:s'),
    ];
}

function pesanan_log_terakhir(int $id, int $batas = 5): array
{
    $st = db()->prepare('SELECT status, catatan, oleh, waktu FROM pesanan_log WHERE pesanan_id = ? ORDER BY id DESC LIMIT ' . (int) $batas);
    $st->execute([$id]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[] = [
            'status' => (string) $r['status'],
            'label'  => LABEL_STATUS_PENDEK[(string) $r['status']] ?? (string) $r['status'],
            'oleh'   => nama_pengguna((string) $r['oleh']),
            'jam'    => jam_teks((string) $r['waktu'], false),
        ];
    }
    return $out;
}

/** Data lengkap untuk halaman pelanggan (lacak pesanan + struk). */
function pesanan_publik(array $p): array
{
    $total = (float) $p['total'];
    $items = [];
    foreach ($p['items'] as $it) {
        $items[] = [
            'nama'     => (string) $it['nama'],
            'qty'      => (float) $it['qty'],
            'qty_teks' => angka_qty($it['qty']),
            'harga'    => rupiah($it['harga']),
            'catatan'  => (string) $it['catatan'],
            'subtotal' => rupiah($it['subtotal']),
            'subtotal_angka' => (float) $it['subtotal'],
        ];
    }
    $langkah = [];
    $kini = (string) $p['status'];
    $posKini = URUT_STATUS[$kini] ?? 1;
    foreach (LANGKAH_PELANGGAN as $s) {
        $pos = URUT_STATUS[$s];
        $langkah[] = [
            'status' => $s,
            'label'  => LABEL_STATUS[$s],
            'aktif'  => $pos <= $posKini,
        ];
    }
    return [
        'kode'           => (string) $p['kode'],
        'token'          => (string) $p['token'],
        'meja'           => pesanan_label_meja($p),
        'nama'           => (string) $p['nama_pelanggan'],
        'telepon'        => (string) $p['telepon'],
        'sumber'         => (string) $p['sumber'],
        'sumber_label'   => (string) $p['sumber'] === SUMBER_PELANGGAN ? 'Pesan mandiri (QR meja)' : 'Kasir',
        'status'         => $kini,
        'status_label'   => LABEL_STATUS[$kini] ?? $kini,
        'status_bayar'   => (string) $p['status_bayar'],
        'metode_bayar'   => (string) $p['metode_bayar'],
        'metode_label'   => LABEL_METODE[(string) $p['metode_bayar']] ?? (string) $p['metode_bayar'],
        'langkah'        => $langkah,
        'items'          => $items,
        'subtotal'       => rupiah($p['subtotal']),
        'diskon'         => rupiah($p['diskon']),
        'service'        => rupiah($p['service']),
        'pajak'          => rupiah($p['pajak']),
        'total'          => rupiah($total),
        'total_angka'    => $total,
        'dibayar'        => rupiah($p['dibayar']),
        'kembali'        => rupiah($p['kembali']),
        'jam'            => jam_teks((string) $p['created_at'], false),
        'waktu'          => jam_teks((string) $p['created_at']),
        'bayar_at'       => $p['bayar_at'] ? jam_teks((string) $p['bayar_at'], false) : '',
        'catatan'        => (string) $p['catatan'],
        'poin'           => (float) $p['poin_dapat'],
        'nama_kafe'      => nama_kafe(),
        'alamat_kafe'    => setting('alamat_kafe'),
        'telepon_kafe'   => setting('telepon_kafe'),
        'logo'           => logo_url(),
        'qris_gambar'    => setting('qris_gambar_url'),
        'qris_catatan'   => setting('qris_catatan'),
        'struk_judul'    => setting('struk_judul', 'STRUK PEMBAYARAN'),
        'struk_catatan'  => setting('struk_catatan'),
        'kasir'          => nama_pengguna((string) $p['bayar_oleh'] !== '' ? (string) $p['bayar_oleh'] : (string) $p['kasir']),
    ];
}

/* ------------------------------------------------------------------ */
/* Pelanggan — ubah data                                               */
/* ------------------------------------------------------------------ */

function pelanggan_tambah(string $nama, string $telepon, string $email, bool $member, string $oleh, string $catatan = ''): int
{
    $ins = db()->prepare('INSERT INTO pelanggan (nama, telepon, email, member, catatan, dibuat_oleh, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([trim($nama), trim($telepon), trim($email), $member ? 1 : 0, $catatan, $oleh, now(), now()]);
    return (int) db()->lastInsertId();
}

/* ------------------------------------------------------------------ */
/* Jejak audit                                                         */
/* ------------------------------------------------------------------ */

function log_admin(string $aksi, string $rincian = ''): void
{
    $u = auth_user();
    db()->prepare('INSERT INTO log_admin (waktu, oleh, aksi, rincian) VALUES (?, ?, ?, ?)')
        ->execute([now(), $u ? (string) $u['username'] : 'sistem', $aksi, $rincian]);
    /* Simpan paling banyak 500 catatan terakhir. */
    db()->exec('DELETE FROM log_admin WHERE id NOT IN (SELECT id FROM log_admin ORDER BY id DESC LIMIT 500)');
}

function log_admin_terakhir(int $batas = 20): array
{
    $st = db()->query('SELECT * FROM log_admin ORDER BY id DESC LIMIT ' . (int) $batas);
    return $st->fetchAll();
}

/* ------------------------------------------------------------------ */
/* Unggah media (foto produk / logo / QRIS) via proxy platform          */
/* ------------------------------------------------------------------ */

function media_token(): string
{
    $f = __DIR__ . '/.vibecoder-media-token';
    return is_readable($f) ? trim((string) file_get_contents($f)) : '';
}

function media_upload(string $filePath, string $filename): array
{
    $token = media_token();
    if ($token === '') {
        return ['ok' => false, 'error' => 'Penyimpanan media belum aktif (aplikasi belum dipublikasikan).'];
    }
    if (!is_file($filePath) || filesize($filePath) < 1) {
        return ['ok' => false, 'error' => 'File tidak dapat dibaca.'];
    }
    $url = 'http://127.0.0.1:4310/api/app-media/upload?filename=' . rawurlencode($filename);

    if (function_exists('curl_init')) {
        $fp = @fopen($filePath, 'rb');
        if ($fp !== false) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_UPLOAD         => true,
                CURLOPT_INFILE         => $fp,
                CURLOPT_INFILESIZE     => (int) filesize($filePath),
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_HTTPHEADER     => ['X-App-Media-Token: ' . $token, 'Content-Type: application/octet-stream'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 300,
            ]);
            $out  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            if ($out === false) {
                return ['ok' => false, 'error' => 'Gagal menghubungi penyimpanan media: ' . $err];
            }
            return media_baca_hasil((string) $out, $code);
        }
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "X-App-Media-Token: $token\r\nContent-Type: application/octet-stream\r\n",
        'content'       => (string) file_get_contents($filePath),
        'timeout'       => 120,
        'ignore_errors' => true,
    ]]);
    $out  = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    if ($out === false) {
        return ['ok' => false, 'error' => 'Gagal menghubungi penyimpanan media.'];
    }
    return media_baca_hasil((string) $out, $code);
}

function media_baca_hasil(string $out, int $code): array
{
    $json = json_decode($out, true);
    if (is_array($json) && !empty($json['ok']) && !empty($json['url'])) {
        return ['ok' => true, 'url' => (string) $json['url']];
    }
    $pesan = 'Penyimpanan media menolak berkas ini.';
    if (is_array($json)) {
        $pesan = (string) ($json['error'] ?? $json['message'] ?? $pesan);
    }
    if ($code === 403) {
        $pesan = 'Kuota penyimpanan media penuh. ' . $pesan;
    }
    return ['ok' => false, 'error' => $pesan];
}

/** Batas unggah PHP dibaca saat berjalan (dikunci platform: 20 MB). */
function batas_unggah_byte(): int
{
    $parse = static function (string $v): int {
        $v = trim($v);
        if ($v === '') {
            return 0;
        }
        $satuan = strtolower(substr($v, -1));
        $n = (float) $v;
        if ($satuan === 'g') {
            $n *= 1024 * 1024 * 1024;
        } elseif ($satuan === 'm') {
            $n *= 1024 * 1024;
        } elseif ($satuan === 'k') {
            $n *= 1024;
        }
        return (int) $n;
    };
    $a = $parse((string) ini_get('upload_max_filesize'));
    $b = $parse((string) ini_get('post_max_size'));
    $batas = min($a > 0 ? $a : PHP_INT_MAX, $b > 0 ? $b : PHP_INT_MAX);
    return $batas === PHP_INT_MAX ? 20 * 1024 * 1024 : $batas;
}

function ukuran_teks(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

/* ------------------------------------------------------------------ */
/* Tampilan halaman                                                    */
/* ------------------------------------------------------------------ */

function flash(): void
{
    $ok  = (string) ($_GET['ok'] ?? '');
    $err = (string) ($_GET['err'] ?? '');
    if ($ok !== '') {
        echo '<div class="flash flash-ok">' . h($ok) . '</div>';
    }
    if ($err !== '') {
        echo '<div class="flash flash-err">' . h($err) . '</div>';
    }
}

function menu_akses(): array
{
    return [
        'index.php'      => ['Dashboard', 'grid'],
        'kasir.php'      => ['Kasir', 'cash'],
        'produk.php'     => ['Data Produk', 'box'],
        'penjualan.php'  => ['Data Penjualan', 'receipt'],
        'laporan.php'    => ['Laporan Penjualan', 'chart'],
        'grafik.php'     => ['Grafik', 'chart'],
        'pelanggan.php'  => ['Data Pelanggan', 'users'],
        'dapur.php'      => ['Display Dapur', 'chef'],
        'pengaturan.php' => ['Pengaturan', 'cog'],
    ];
}

function nama_pengguna(string $username): string
{
    static $peta = null;
    if ($peta === null) {
        $peta = [];
        try {
            foreach (db()->query('SELECT username, nama FROM user') as $r) {
                $peta[strtolower((string) $r['username'])] = trim((string) $r['nama']);
            }
        } catch (Throwable $e) {
            /* jangan gagalkan tampilan */
        }
    }
    $kunci = strtolower(trim($username));
    $nama = $peta[$kunci] ?? '';
    return $nama !== '' ? $nama : trim($username);
}

/** Kepala halaman aplikasi (sidebar cokelat gelap + konten cream). */
function page_head(string $title, string $active = '', array $opt = []): void
{
    $user  = auth_user();
    $menu  = menu_akses();
    $boleh = $user ? menu_peran((string) $user['role']) : [];
    $tema  = setting('tema_warna', '#B4763B');
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — <?= h(nama_kafe()) ?></title>
<?php if (!empty($opt['refresh'])): ?>
<meta http-equiv="refresh" content="<?= (int) $opt['refresh'] ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
<style>:root{--accent:<?= h($tema) ?>;}</style>
</head>
<body class="app<?= !empty($opt['kelas']) ? ' ' . h((string) $opt['kelas']) : '' ?>">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if (logo_url() !== ''): ?>
            <img src="<?= h(logo_url()) ?>" alt="<?= h(nama_kafe()) ?>">
        <?php else: ?>
            <span class="brand-ikon"><?= icon('coffee') ?></span>
        <?php endif; ?>
        <span class="brand-teks">
            <strong><?= h(nama_kafe()) ?></strong>
            <em><?= h(setting('tagline_kafe', 'Kasir')) ?></em>
        </span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $file => $info): ?>
            <?php if ($user && in_array($file, $boleh, true)): ?>
                <a class="nav-item<?= $active === $file ? ' is-active' : '' ?>" href="<?= h($file) ?>">
                    <?= icon($info[1]) ?><span><?= h($info[0]) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <a class="nav-item" href="display.php" target="_blank" rel="noopener"><?= icon('tv') ?><span>Display Menu</span></a>
    </nav>
    <div class="sidebar-kaki">
        <?php if ($user): ?>
            <div class="pengguna">
                <?= icon('user') ?>
                <span><strong><?= h($user['nama'] !== '' ? $user['nama'] : $user['username']) ?></strong><em><?= h(label_role((string) $user['role'])) ?></em></span>
            </div>
            <a class="nav-item nav-keluar" href="logout.php"><?= icon('out') ?><span>Keluar</span></a>
        <?php else: ?>
            <a class="nav-item" href="login.php"><?= icon('user') ?><span>Masuk</span></a>
        <?php endif; ?>
    </div>
</aside>
<div class="utama">
    <header class="topbar">
        <button class="tombol-sidebar" type="button" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">
            <span></span><span></span><span></span>
        </button>
        <h1 class="topbar-judul"><?= h($title) ?></h1>
        <div class="topbar-kanan">
            <?php if (!empty($opt['kanan'])): ?>
                <?= $opt['kanan'] ?>
            <?php endif; ?>
            <span class="jam" id="jam-app"><?= h(date('d/m/Y H:i')) ?></span>
        </div>
    </header>
    <main class="wrap">
<?php
}

/**
 * Tombol layar penuh mengambang (pojok kanan bawah) — sama seperti pada halaman display menu.
 *
 * Ditaruh di SATU tempat supaya seluruh halaman menu/form memakai tombol yang sama.
 * Perilakunya dipasang oleh assets/app.js (`pasangLayarPenuh`). Dua ikon disiapkan sekaligus
 * dan dipertukarkan lewat CSS (bukan menulis ulang innerHTML) agar tidak ada markup di JS.
 */
function tombol_layar_penuh(): void
{
    ?>
<button class="tombol-penuh" id="btn-penuh" type="button" title="Layar penuh" aria-label="Layar penuh">
    <svg class="ikon-penuh" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 14H5v5h5v-2H7zm-2-4h2V7h3V5H5zm12 7h-3v2h5v-5h-2zM14 5v2h3v3h2V5z"/></svg>
    <svg class="ikon-keluar" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 16h3v3h2v-5H5zm3-8H5v2h5V5H8zm6 11h2v-3h3v-2h-5zm2-11V5h-2v5h5V8z"/></svg>
</button>
<?php
}

function page_end(string|array $script = ''): void
{
    $daftar = is_array($script) ? $script : ($script !== '' ? [$script] : []);
    ?>
    </main>
    <footer class="footer-app">
        <span><?= h(nama_kafe()) ?> · Aplikasi Kasir Kafe</span>
        <span>Jam server <?= h(date('d/m/Y H:i')) ?> · <a href="display.php" target="_blank" rel="noopener">Display menu</a></span>
    </footer>
</div>
<?php tombol_layar_penuh(); ?>
<div class="toast-wrap" id="toast-wrap"></div>
<script src="<?= h(asset('assets/app.js')) ?>"></script>
<?php foreach ($daftar as $f): ?>
<script src="<?= h($f) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/**
 * Penutup halaman PUBLIK (pesan.php, struk.php): hanya menyisipkan skrip,
 * tanpa kerangka sidebar/topbar. Penutup </body></html> ditulis halaman itu sendiri.
 */
function page_end_publik(string|array $script = ''): void
{
    $daftar = is_array($script) ? $script : ($script !== '' ? [$script] : []);
    ?>
<div class="toast-wrap" id="toast-wrap"></div>
<script src="<?= h(asset('assets/app.js')) ?>"></script>
<?php foreach ($daftar as $f): ?>
<script src="<?= h($f) ?>"></script>
<?php endforeach; ?>
<?php
}
