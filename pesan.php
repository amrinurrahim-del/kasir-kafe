<?php
declare(strict_types=1);

/*
 * Halaman pelanggan (dibuka dari QR di meja) — PUBLIK tanpa login.
 *
 *   pesan.php?m=<id meja>&t=<token meja>            → pilih menu lalu kirim pesanan
 *   pesan.php?p=<kode pesanan>&k=<token pesanan>     → lacak status & lihat struk
 */

require_once __DIR__ . '/lib.php';

$mejaId = (int) ($_GET['m'] ?? 0);
$mejaToken = (string) ($_GET['t'] ?? '');
$meja = $mejaId > 0 ? meja_dari_token_aman($mejaId, $mejaToken) : null;

$kodePesanan = trim((string) ($_GET['p'] ?? ''));
$tokenPesanan = trim((string) ($_GET['k'] ?? ''));
$pesanan = ($kodePesanan !== '' && $tokenPesanan !== '') ? pesanan_by_kode_token($kodePesanan, $tokenPesanan) : null;

$menu = payload_menu();
$kategori = $menu['kategori'];
$tunai = setting_bool('tunai_aktif', true);
$qris = setting_bool('qris_aktif', true);

/** Meja dari id + token (tanpa mengubah data). */
function meja_dari_token_aman(int $id, string $token): ?array
{
    $m = meja_row($id);
    if (!$m || (int) $m['aktif'] !== 1) {
        return null;
    }
    return hash_equals((string) $m['token'], trim($token)) ? $m : null;
}

/** Keranjang dari localStorage tidak dipakai: pesanan dikirim langsung saat checkout. */

?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(nama_kafe()) ?> — Pesan Menu</title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
<style>:root{--accent:<?= h(setting('tema_warna', '#B4763B')) ?>;}</style>
</head>
<body class="pesan-halaman">

<header class="pesan-head">
    <?php if (logo_url() !== ''): ?>
        <img src="<?= h(logo_url()) ?>" alt="<?= h(nama_kafe()) ?>">
    <?php endif; ?>
    <div>
        <h1><?= h(nama_kafe()) ?></h1>
        <p>
            <?php if ($meja): ?>
                <?= h(meja_label($meja)) ?> · pesan langsung dari HP Anda
            <?php elseif ($pesanan): ?>
                Pesanan <?= h((string) $pesanan['kode']) ?> · <?= h(pesanan_label_meja($pesanan)) ?>
            <?php else: ?>
                <?= h(setting('tagline_kafe', 'Coffee & Kitchen')) ?>
            <?php endif; ?>
        </p>
    </div>
</header>

<?php if ($pesanan): ?>
    <?php
    $p = pesanan_publik($pesanan);
    $mejaPesanan = (int) ($pesanan['meja_id'] ?? 0) > 0 ? meja_row((int) $pesanan['meja_id']) : null;
    ?>
    <main class="pesan-isi" id="lacak" data-kode="<?= h($p['kode']) ?>" data-token="<?= h($p['token']) ?>" data-status="<?= h($p['status']) ?>">
        <section class="kartu">
            <div class="kartu-judul">
                <h3>Status pesanan Anda</h3>
                <?php if ($p['status_bayar'] === BAYAR_LUNAS): ?>
                    <span class="pil pil-ok">Sudah dibayar</span>
                <?php else: ?>
                    <span class="pil pil-err">Belum dibayar</span>
                <?php endif; ?>
            </div>
            <div class="langkah" id="langkah">
                <?php foreach ($p['langkah'] as $i => $l): ?>
                    <div class="langkah-item<?= $l['aktif'] ? ' is-aktif' : '' ?>" data-status="<?= h($l['status']) ?>">
                        <div class="langkah-titik"><i></i><?php if ($i < count($p['langkah']) - 1): ?><span class="garis"></span><?php endif; ?></div>
                        <div class="langkah-teks">
                            <strong><?= h($l['label']) ?></strong>
                            <?php if ($l['status'] === PS_SELESAI): ?><em>Selamat menikmati sajian kami</em><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="kartu-sub" id="status-teks">Mohon tunggu, pesanan Anda sedang kami proses.</p>
        </section>

        <section class="kartu">
            <div class="kartu-judul"><h3>Rincian pesanan</h3><span class="pil pil-gelap"><?= h($p['waktu']) ?></span></div>
            <?php foreach ($p['items'] as $it): ?>
                <div class="info-baris">
                    <span><?= h($it['qty_teks']) ?>× <?= h($it['nama']) ?><?= $it['catatan'] !== '' ? ' — ' . h($it['catatan']) : '' ?></span>
                    <strong><?= h($it['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="info-baris"><span>Subtotal</span><strong><?= h($p['subtotal']) ?></strong></div>
            <?php if ((float) $pesanan['diskon'] > 0): ?><div class="info-baris"><span>Diskon</span><strong>- <?= h($p['diskon']) ?></strong></div><?php endif; ?>
            <?php if ((float) $pesanan['service'] > 0): ?><div class="info-baris"><span>Service</span><strong><?= h($p['service']) ?></strong></div><?php endif; ?>
            <?php if ((float) $pesanan['pajak'] > 0): ?><div class="info-baris"><span>Pajak</span><strong><?= h($p['pajak']) ?></strong></div><?php endif; ?>
            <div class="info-baris"><span><strong>Total</strong></span><strong><?= h($p['total']) ?></strong></div>
            <div class="info-baris"><span>Metode pembayaran</span><strong><?= h($p['metode_label']) ?></strong></div>
            <?php if ($p['status_bayar'] === BAYAR_LUNAS): ?>
                <div class="info-baris"><span>Dibayar</span><strong><?= h($p['dibayar']) ?></strong></div>
                <?php if ((float) $pesanan['kembali'] > 0): ?><div class="info-baris"><span>Kembalian</span><strong><?= h($p['kembali']) ?></strong></div><?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($p['status_bayar'] !== BAYAR_LUNAS): ?>
            <section class="kartu" id="bagian-bayar">
                <?php if ($p['metode_bayar'] === METODE_QRIS && $p['qris_gambar'] !== ''): ?>
                    <div class="kartu-judul"><h3>Bayar dengan QRIS</h3></div>
                    <p class="kartu-sub">Pindai QRIS berikut, lalu tunjukkan bukti pembayaran ke kasir bila diminta.</p>
                    <img src="<?= h($p['qris_gambar']) ?>" alt="QRIS" style="width:100%;max-width:280px;border-radius:14px;border:1px solid var(--line)">
                <?php elseif ($p['metode_bayar'] === METODE_QRIS): ?>
                    <div class="kartu-judul"><h3>Bayar dengan QRIS</h3></div>
                    <p class="kartu-sub">Silakan pindai QRIS di kasir, lalu kasir akan menandai pembayaran Anda.</p>
                <?php else: ?>
                    <div class="kartu-judul"><h3>Bayar di Kasir</h3></div>
                    <p class="kartu-sub">Tunjukkan nomor pesanan <strong><?= h($p['kode']) ?></strong> ke kasir dan lakukan pembayaran tunai.</p>
                <?php endif; ?>
                <?php if ($p['qris_catatan'] !== ''): ?><p class="kartu-sub"><?= h($p['qris_catatan']) ?></p><?php endif; ?>
            </section>
        <?php else: ?>
            <section class="kartu">
                <div class="kartu-judul"><h3>Struk Anda</h3><span class="pil pil-ok">Lunas</span></div>
                <p class="kartu-sub">Pembayaran diterima. Struk digital Anda sudah siap.</p>
                <a class="tombol tombol-utama tombol-blok" href="struk.php?p=<?= h(urlencode($p['kode'])) ?>&k=<?= h(urlencode($p['token'])) ?>"><?= icon('receipt') ?> Buka Struk Digital</a>
            </section>
        <?php endif; ?>

        <section class="kartu">
            <div class="baris-tombol">
                <button class="tombol" type="button" id="btn-notif"><?= icon('bell') ?> Aktifkan Notifikasi</button>
                <a class="tombol" href="<?= $mejaPesanan ? h('pesan.php?m=' . (int) $mejaPesanan['id'] . '&t=' . $mejaPesanan['token']) : h('pesan.php') ?>">Pesan lagi</a>
            </div>
            <p class="kartu-sub" style="margin-top:10px">
                Notifikasi hanya muncul selama halaman ini terbuka di HP Anda. Tambahkan halaman ini ke layar utama
                agar mudah dibuka kembali.
            </p>
        </section>
    </main>

    <script id="data-pesanan" type="application/json"><?= json_encode($p, JSON_UNESCAPED_UNICODE) ?></script>
    <?php page_end_publik('assets/pesan.js'); ?>

<?php elseif ($meja): ?>
    <main class="pesan-isi" id="pesan-app" data-meja="<?= (int) $meja['id'] ?>" data-token="<?= h((string) $meja['token']) ?>">
        <div class="pesan-kategori" id="kategori-bar">
            <button class="kategori-chip is-aktif" type="button" data-kategori="0">Semua</button>
            <?php foreach ($kategori as $k): ?>
                <button class="kategori-chip" type="button" data-kategori="<?= (int) $k['id'] ?>"><?= h($k['nama']) ?></button>
            <?php endforeach; ?>
        </div>
        <div class="pesan-menu" id="menu-daftar"></div>
        <p class="kosong-teks" id="menu-kosong" hidden>Menu belum tersedia.</p>

        <section class="kartu" id="bagian-checkout" hidden style="margin-top:20px">
            <div class="kartu-judul"><h3>Konfirmasi Pesanan</h3><span class="pil" id="info-meja"><?= h(meja_label($meja)) ?></span></div>
            <div id="ringkasan-keranjang"></div>
            <div class="form-baris form-2">
                <div class="kolom">
                    <label for="nama">Nama Anda *</label>
                    <input type="text" id="nama" required placeholder="Nama untuk panggilan pesanan">
                </div>
                <div class="kolom">
                    <label for="telepon">Nomor HP *</label>
                    <input type="tel" id="telepon" required placeholder="0812xxxxxxx">
                </div>
            </div>
            <div class="kolom" style="margin-bottom:14px">
                <label for="catatan">Catatan (opsional)</label>
                <input type="text" id="catatan" placeholder="Contoh: tanpa gula, sambal dipisah">
            </div>
            <label class="cek" style="margin-bottom:14px"><input type="checkbox" id="member"> Daftar jadi member kafe (diskon &amp; poin)</label>

            <div class="kolom">
                <label>Metode pembayaran *</label>
                <div class="pembayaran-pilih">
                    <?php if ($tunai): ?>
                        <button class="pembayaran-opsi is-aktif" type="button" data-metode="TUNAI">Tunai di Kasir</button>
                    <?php endif; ?>
                    <?php if ($qris): ?>
                        <button class="pembayaran-opsi<?= $tunai ? '' : ' is-aktif' ?>" type="button" data-metode="QRIS">QRIS</button>
                    <?php endif; ?>
                </div>
            </div>
            <p class="kartu-sub" id="petunjuk-bayar" style="margin-top:10px">Bayar tunai ke kasir setelah pesanan tercatat.</p>

            <div class="form-aksi" style="margin-top:14px">
                <button class="tombol tombol-utama tombol-blok" type="button" id="btn-kirim"><?= icon('check') ?> Kirim Pesanan</button>
                <button class="tombol tombol-blok" type="button" id="btn-batal">Batal</button>
            </div>
        </section>

        <div class="pesan-bar" id="pesan-bar" hidden>
            <div class="pesan-bar-info">
                <span id="bar-item">0 item</span>
                <strong id="bar-total">Rp 0</strong>
            </div>
            <button class="tombol tombol-utama" type="button" id="btn-lanjut">Lanjut Pesan <?= icon('cart') ?></button>
        </div>
    </main>

    <script id="data-menu" type="application/json"><?= json_encode($menu, JSON_UNESCAPED_UNICODE) ?></script>
    <?php page_end_publik('assets/pesan.js'); ?>

<?php else: ?>
    <main class="pesan-isi">
        <section class="kartu">
            <div class="kartu-judul"><h3>Pindai QR di meja Anda</h3></div>
            <p class="kartu-sub">
                Halaman ini untuk memesan menu kafe langsung dari HP. Silakan pindai (scan) QR code yang
                tertera di meja Anda, lalu halaman menu akan terbuka otomatis.
            </p>
            <p class="kartu-sub">Bila QR tidak terbaca, minta bantuan petugas kami untuk memesan melalui kasir.</p>
            <div class="baris-tombol">
                <a class="tombol" href="display.php" target="_blank" rel="noopener"><?= icon('tv') ?> Lihat Menu Kafe</a>
            </div>
        </section>
    </main>
    <?php page_end_publik(); ?>
<?php endif; ?>
</body>
</html>
