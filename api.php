<?php
declare(strict_types=1);

/*
 * Endpoint JSON aplikasi kasir kafe.
 *
 * Publik (display & pelanggan lewat QR meja):
 *   api.php?action=menu           katalog menu + jam
 *   api.php?action=menu_cek       sidik jari perubahan (sangat ringan)
 *   api.php?action=pesan_simpan   pelanggan mengirim pesanan (POST)
 *   api.php?action=pesan_status   status pesanan pelanggan (p&k)
 *
 * Butuh login:
 *   api.php?action=kasir_produk|kasir_pesanan|kasir_cek|pesanan_detail
 *   api.php?action=bayar          (POST)  kasir menandai pembayaran
 *   api.php?action=batal          (POST)  batalkan pesanan belum dibayar
 *   api.php?action=dapur          papan dapur
 *   api.php?action=dapur_status   (POST)  dapur memperbarui status
 */

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$action = (string) ($_GET['action'] ?? 'menu');

function butuh_login(): array
{
    $u = auth_user();
    if (!$u) {
        json_out(['ok' => false, 'error' => 'Perlu login untuk tindakan ini.'], 401);
    }
    return $u;
}

function butuh_post(): void
{
    if (!is_post()) {
        json_out(['ok' => false, 'error' => 'Gunakan metode POST untuk tindakan ini.'], 405);
    }
}

/** Badan permintaan: JSON atau form. */
function masukan(): array
{
    $tipe = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($tipe, 'application/json') !== false) {
        $mentah = file_get_contents('php://input');
        $data = json_decode((string) $mentah, true);
        if (is_array($data)) {
            return $data;
        }
        return [];
    }
    return array_merge($_GET, $_POST);
}

/** Meja dari id + token (dipakai halaman pelanggan). */
function meja_dari_token(int $id, string $token): ?array
{
    $m = meja_row($id);
    if (!$m || (int) $m['aktif'] !== 1) {
        return null;
    }
    if (!hash_equals((string) $m['token'], trim($token))) {
        return null;
    }
    return $m;
}

try {
    switch ($action) {
        /* ---------------- Display & pelanggan (publik) ---------------- */
        case 'menu':
        default: {
            $menu = payload_menu();
            $menu['ok'] = true;
            $menu['judul'] = setting('display_judul', 'MENU KAFE');
            $menu['footer'] = setting('display_footer');
            $menu['tampil_harga'] = setting_bool('display_tampil_harga', true) ? 1 : 0;
            $menu['kolom'] = (int) max(2, min(4, setting_num('display_kolom', 3)));
            $menu['kecepatan'] = (float) setting_num('display_kecepatan', 38);
            $menu['panel_siap'] = setting_bool('display_panel_siap', true) ? 1 : 0;
            $menu['siap'] = payload_siap();
            $menu['jam'] = date('H:i:s');
            $menu['tanggal'] = tgl_label(hari_ini(), true);
            $menu['asset_version'] = asset_versi();
            json_out($menu);
        }

        case 'menu_cek': {
            json_out([
                'ok'          => true,
                'stamp'       => menu_stamp(),
                'stamp_siap'  => pesanan_stamp(),
                'jam'         => date('H:i:s'),
                'asset_version' => asset_versi(),
            ]);
        }

        case 'pesan_simpan': {
            butuh_post();
            $d = masukan();
            $meja = meja_dari_token((int) ($d['meja_id'] ?? 0), (string) ($d['meja_token'] ?? ''));
            if (!$meja) {
                json_out(['ok' => false, 'error' => 'QR meja tidak dikenali. Silakan pindai ulang QR di meja Anda.'], 400);
            }
            $nama = trim((string) ($d['nama'] ?? ''));
            $telp = trim((string) ($d['telepon'] ?? ''));
            if ($nama === '') {
                json_out(['ok' => false, 'error' => 'Mohon isi nama Anda terlebih dahulu.'], 400);
            }
            if (!preg_match('/^[0-9+\-\s]{8,20}$/', $telp)) {
                json_out(['ok' => false, 'error' => 'Nomor HP belum benar (contoh: 081234567890).'], 400);
            }
            $metode = strtoupper((string) ($d['metode_bayar'] ?? ''));
            if (!array_key_exists($metode, LABEL_METODE)) {
                json_out(['ok' => false, 'error' => 'Pilih metode pembayaran terlebih dahulu (Tunai di Kasir atau QRIS).'], 400);
            }
            if (empty($d['items']) || !is_array($d['items'])) {
                json_out(['ok' => false, 'error' => 'Belum ada menu yang dipilih.'], 400);
            }
            $hasil = pesanan_buat([
                'meja_id'        => (int) $meja['id'],
                'nama_pelanggan' => $nama,
                'telepon'        => $telp,
                'sumber'         => SUMBER_PELANGGAN,
                'metode_bayar'   => $metode,
                'catatan'        => (string) ($d['catatan'] ?? ''),
                'daftar_member'  => !empty($d['member']),
                'items'          => $d['items'],
                'oleh'           => '',
            ]);
            if (empty($hasil['ok'])) {
                json_out($hasil, 400);
            }
            $p = pesanan_row((int) $hasil['id']);
            json_out([
                'ok'      => true,
                'kode'    => (string) $hasil['kode'],
                'token'   => (string) $hasil['token'],
                'pesanan' => pesanan_publik($p),
            ]);
        }

        case 'pesan_status': {
            $p = pesanan_by_kode_token((string) ($_GET['p'] ?? ''), (string) ($_GET['k'] ?? ''));
            if (!$p) {
                json_out(['ok' => false, 'error' => 'Pesanan tidak ditemukan.'], 404);
            }
            json_out(['ok' => true, 'pesanan' => pesanan_publik($p)]);
        }

        /* ---------------- Kasir ---------------- */
        case 'kasir_produk': {
            butuh_login();
            json_out([
                'ok'     => true,
                'produk' => array_map(static function (array $p): array {
                    return [
                        'id'         => (int) $p['id'],
                        'nama'       => (string) $p['nama'],
                        'harga'      => (float) $p['harga'],
                        'harga_teks' => rupiah($p['harga']),
                        'harga_member' => (float) $p['harga_member'],
                        'harga_member_teks' => (float) $p['harga_member'] > 0 ? rupiah($p['harga_member']) : '',
                        'kategori_id'=> (int) ($p['kategori_id'] ?? 0),
                        'kategori'   => (string) ($p['kategori'] ?? ''),
                        'foto'       => (string) $p['foto_url'],
                        'favorit'    => (int) $p['favorit'] === 1,
                    ];
                }, produk_semua(true)),
                'stamp'  => menu_stamp(),
            ]);
        }

        /*
         * Pesanan yang perlu ditindak kasir: belum dibayar ATAU belum direspons dapur.
         * Endpoint ini juga menjadi penanda "ada pesanan baru" (polling ringan).
         */
        case 'kasir_pesanan': {
            butuh_login();
            $hari = hari_ini();
            $st = db()->prepare('SELECT p.*, m.nomor AS meja_nomor, m.nama AS meja_nama
                                 FROM pesanan p LEFT JOIN meja m ON m.id = p.meja_id
                                 WHERE p.tanggal = ? AND p.status <> ?
                                   AND (p.status_bayar <> ? OR p.status = ?)
                                 ORDER BY p.created_at ASC');
            $st->execute([$hari, PS_BATAL, BAYAR_LUNAS, PS_BARU]);
            $out = [];
            foreach ($st->fetchAll() as $p) {
                $p['items'] = pesanan_items((int) $p['id']);
                $r = pesanan_ringkas($p);
                $r['catatan_bayar'] = (string) $p['status_bayar'] !== BAYAR_LUNAS;
                $out[] = $r;
            }
            json_out(['ok' => true, 'pesanan' => $out, 'stamp' => pesanan_stamp(), 'jam' => date('H:i:s')]);
        }

        case 'kasir_cek': {
            butuh_login();
            $hari = hari_ini();
            $st = db()->prepare('SELECT COUNT(*) FROM pesanan WHERE tanggal = ? AND status <> ? AND status_bayar <> ?');
            $st->execute([$hari, PS_BATAL, BAYAR_LUNAS]);
            $belumBayar = (int) $st->fetchColumn();
            $st2 = db()->prepare('SELECT COUNT(*) FROM pesanan WHERE tanggal = ? AND status = ?');
            $st2->execute([$hari, PS_BARU]);
            json_out([
                'ok'          => true,
                'stamp'       => pesanan_stamp(),
                'belum_bayar' => $belumBayar,
                'baru'        => (int) $st2->fetchColumn(),
                'jam'         => date('H:i:s'),
            ]);
        }

        case 'pesanan_detail': {
            butuh_login();
            $p = pesanan_row((int) ($_GET['id'] ?? 0));
            if (!$p) {
                json_out(['ok' => false, 'error' => 'Pesanan tidak ditemukan.'], 404);
            }
            $r = pesanan_publik($p);
            $r['ok'] = true;
            $r['log'] = pesanan_log_terakhir((int) $p['id'], 8);
            json_out($r);
        }

        /* Kasir membuat pesanan (dari keranjang di layar kasir). */
        case 'kasir_simpan': {
            $u = butuh_login(['owner', 'kasir']);
            butuh_post();
            $d = masukan();
            $metode = strtoupper((string) ($d['metode_bayar'] ?? METODE_TUNAI));
            if (!array_key_exists($metode, LABEL_METODE)) {
                $metode = METODE_TUNAI;
            }
            $lunas = !empty($d['lunas']);
            $hasil = pesanan_buat([
                'meja_id'        => (int) ($d['meja_id'] ?? 0),
                'pelanggan_id'   => (int) ($d['pelanggan_id'] ?? 0),
                'nama_pelanggan' => (string) ($d['nama'] ?? ''),
                'telepon'        => (string) ($d['telepon'] ?? ''),
                'sumber'         => SUMBER_KASIR,
                'metode_bayar'   => $metode,
                'catatan'        => (string) ($d['catatan'] ?? ''),
                'diskon_persen'  => array_key_exists('diskon_persen', $d) ? (float) $d['diskon_persen'] : null,
                'lunas'          => $lunas,
                'dibayar'        => (float) ($d['dibayar'] ?? 0),
                'items'          => is_array($d['items'] ?? null) ? $d['items'] : [],
                'oleh'           => (string) $u['username'],
            ]);
            if (empty($hasil['ok'])) {
                json_out($hasil, 400);
            }
            $p = pesanan_row((int) $hasil['id']);
            json_out([
                'ok'      => true,
                'kode'    => (string) $hasil['kode'],
                'token'   => (string) $hasil['token'],
                'pesanan' => pesanan_publik($p),
                'stamp'   => pesanan_stamp(),
            ]);
        }

        case 'bayar': {            $u = butuh_login();
            butuh_post();
            $d = masukan();
            $id = (int) ($d['id'] ?? 0);
            $metode = (string) ($d['metode'] ?? '');
            $dibayar = (float) ($d['dibayar'] ?? 0);
            $r = pesanan_bayar($id, $metode, $dibayar, (string) $u['username']);
            if (empty($r['ok'])) {
                json_out($r, 400);
            }
            $p = pesanan_row($id);
            json_out(['ok' => true, 'kembali' => (float) $r['kembali'], 'pesanan' => pesanan_publik($p)]);
        }

        case 'batal': {
            $u = butuh_login();
            butuh_post();
            $d = masukan();
            $r = pesanan_batal((int) ($d['id'] ?? 0), (string) $u['username'], (string) ($d['alasan'] ?? 'Dibatalkan kasir'));
            if (empty($r['ok'])) {
                json_out($r, 400);
            }
            json_out(['ok' => true]);
        }

        case 'pelanggan_cari': {
            butuh_login();
            $q = trim((string) ($_GET['q'] ?? ''));
            $hasil = [];
            foreach (pelanggan_semua($q) as $p) {
                $hasil[] = [
                    'id'        => (int) $p['id'],
                    'nama'      => (string) $p['nama'],
                    'telepon'   => (string) $p['telepon'],
                    'member'    => (int) $p['member'] === 1,
                    'poin'      => (float) $p['poin'],
                    'poin_teks' => angka_qty($p['poin']) . ' poin',
                ];
            }
            json_out(['ok' => true, 'pelanggan' => array_slice($hasil, 0, 12)]);
        }

        /* ---------------- Dapur ---------------- */
        case 'dapur': {
            butuh_login(['owner', 'dapur']);
            json_out(payload_dapur());
        }

        case 'dapur_status': {
            $u = butuh_login(['owner', 'kasir', 'dapur']);
            butuh_post();
            $d = masukan();
            $id = (int) ($d['id'] ?? 0);
            $arah = (string) ($d['arah'] ?? 'maju');
            $status = (string) ($d['status'] ?? '');
            $r = $status !== '' && $arah === '' ? pesanan_status_ubah($id, $status, (string) $u['username'], '') : pesanan_status_ubah($id, $status, (string) $u['username'], $arah);
            if (empty($r['ok'])) {
                json_out($r, 400);
            }
            json_out(['ok' => true, 'status' => (string) $r['status'], 'label' => (string) $r['status_label'], 'stamp' => pesanan_stamp()]);
        }
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Terjadi kesalahan di server: ' . $e->getMessage()], 500);
}
