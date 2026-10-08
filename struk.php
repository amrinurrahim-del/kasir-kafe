<?php
declare(strict_types=1);

/*
 * Struk digital — PUBLIK dengan token pesanan.
 *   struk.php?p=<kode>&k=<token>              tampilan struk (bisa dicetak)
 *   struk.php?p=<kode>&k=<token>&format=pdf   unduh PDF (kertas 80 mm)
 *   struk.php?p=<kode>&k=<token>&cetak=1      langsung membuka dialog cetak
 */

require_once __DIR__ . '/lib.php';

$kode = trim((string) ($_GET['p'] ?? ''));
$token = trim((string) ($_GET['k'] ?? ''));
$pesanan = ($kode !== '' && $token !== '') ? pesanan_by_kode_token($kode, $token) : null;

if (!$pesanan) {
    http_response_code(404);
    ?><!DOCTYPE html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk tidak ditemukan</title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
</head><body class="struk-halaman">
<div class="struk-kertas">
    <h1>STRUK TIDAK DITEMUKAN</h1>
    <p class="pusat tipis">Tautan struk tidak sah atau sudah kedaluwarsa. Mintalah bantuan petugas kami.</p>
</div>
</body></html><?php
    exit;
}

$d = pesanan_publik($pesanan);

if (($kode !== '' && ($_GET['format'] ?? '') === 'pdf')) {
    require_once __DIR__ . '/cetak.php';
    $isi = struk_pdf($d);
    kirim_file('struk-' . $d['kode'] . '.pdf', 'application/pdf', $isi);
}

?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk <?= h($d['kode']) ?> — <?= h($d['nama_kafe']) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
<style>
/* Struk thermal 80 mm: lebar kertas & teks padat (dipakai saat dicetak dari kasir). */
@media print {
    .struk-kertas { max-width: 78mm; font-size: 10.5px; }
    .struk-aksi { display: none !important; }
}
</style>
</head>
<body class="struk-halaman">
<div class="struk-kertas">
    <?php if ($d['logo'] !== '' && setting_bool('struk_tampil_logo', true)): ?>
        <div class="pusat"><img src="<?= h($d['logo']) ?>" alt="" style="max-width:110px;max-height:70px"></div>
    <?php endif; ?>
    <h1><?= h($d['nama_kafe']) ?></h1>
    <p class="pusat tipis"><?= h($d['alamat_kafe']) ?><?= $d['telepon_kafe'] !== '' ? ' · ' . h($d['telepon_kafe']) : '' ?></p>
    <div class="struk-garis"></div>
    <h1 style="font-size:.95rem;letter-spacing:.1em"><?= h($d['struk_judul']) ?></h1>

    <div class="struk-baris"><span>No. Pesanan</span><strong><?= h($d['kode']) ?></strong></div>
    <div class="struk-baris"><span>Waktu</span><span><?= h(tgl_label(hari_ini(), true)) ?> <?= h($d['waktu']) ?></span></div>
    <div class="struk-baris"><span>Meja</span><span><?= h($d['meja']) ?></span></div>
    <div class="struk-baris"><span>Tipe</span><span><?= h((string) $pesanan['sumber'] === SUMBER_PELANGGAN ? 'Pesan mandiri (QR)' : 'Kasir') ?></span></div>
    <?php if ($d['nama'] !== ''): ?>
        <div class="struk-baris"><span>Pelanggan</span><span><?= h($d['nama']) ?></span></div>
    <?php endif; ?>
    <div class="struk-baris">
        <span>Pembayaran</span>
        <span><?= h($d['metode_label']) ?> · <?= $d['status_bayar'] === BAYAR_LUNAS ? 'LUNAS' : 'BELUM DIBAYAR' ?></span>
    </div>
    <?php if ($d['kasir'] !== ''): ?><div class="struk-baris"><span>Kasir</span><span><?= h($d['kasir']) ?></span></div><?php endif; ?>

    <div class="struk-garis"></div>
    <?php foreach ($d['items'] as $it): ?>
        <div class="struk-item">
            <div><strong><?= h($it['nama']) ?></strong></div>
            <div class="sub"><span><?= h($it['qty_teks']) ?> × <?= h($it['harga']) ?></span><span><?= h($it['subtotal']) ?></span></div>
            <?php if ($it['catatan'] !== ''): ?><div class="sub"><span>* <?= h($it['catatan']) ?></span></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="struk-garis"></div>

    <div class="struk-baris"><span>Subtotal</span><span><?= h($d['subtotal']) ?></span></div>
    <?php if ((float) $pesanan['diskon'] > 0): ?><div class="struk-baris"><span>Diskon</span><span>- <?= h($d['diskon']) ?></span></div><?php endif; ?>
    <?php if ((float) $pesanan['service'] > 0): ?><div class="struk-baris"><span>Service</span><span><?= h($d['service']) ?></span></div><?php endif; ?>
    <?php if ((float) $pesanan['pajak'] > 0): ?><div class="struk-baris"><span>Pajak</span><span><?= h($d['pajak']) ?></span></div><?php endif; ?>
    <div class="struk-garis"></div>
    <div class="struk-total"><span>TOTAL</span><span><?= h($d['total']) ?></span></div>
    <?php if ((float) $pesanan['dibayar'] > 0): ?>
        <div class="struk-baris"><span>Diterima</span><span><?= h($d['dibayar']) ?></span></div>
        <div class="struk-baris"><span>Kembalian</span><span><?= h($d['kembali']) ?></span></div>
    <?php endif; ?>
    <?php if ((float) $pesanan['poin_dapat'] > 0): ?>
        <div class="struk-baris"><span>Poin didapat</span><span><?= h(angka_qty($pesanan['poin_dapat'])) ?> poin</span></div>
    <?php endif; ?>

    <?php if ($d['status_bayar'] !== BAYAR_LUNAS): ?>
        <div class="struk-garis"></div>
        <p class="pusat" style="font-weight:700;color:#9a6b12">BELUM DIBAYAR — silakan bayar di kasir</p>
        <?php if ($d['metode_bayar'] === METODE_QRIS && $d['qris_gambar'] !== ''): ?>
            <p class="pusat tipis">Bayar dengan QRIS:</p>
            <p class="pusat"><img src="<?= h($d['qris_gambar']) ?>" alt="QRIS" style="max-width:200px"></p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="struk-garis"></div>
    <p class="pusat tipis"><?= h($d['struk_catatan']) ?></p>
    <p class="pusat tipis">Dicetak <?= h(date('d/m/Y H:i')) ?></p>
</div>

<div class="struk-aksi">
    <button class="tombol tombol-utama" type="button" onclick="window.print()"><?= icon('print') ?> Cetak</button>
    <a class="tombol" href="struk.php?p=<?= h(urlencode($d['kode'])) ?>&k=<?= h(urlencode($d['token'])) ?>&format=pdf"><?= icon('download') ?> Unduh PDF</a>
    <?php if ((int) ($pesanan['meja_id'] ?? 0) > 0): ?>
        <?php $m = meja_row((int) $pesanan['meja_id']); ?>
        <?php if ($m): ?>
            <a class="tombol" href="pesan.php?m=<?= (int) $m['id'] ?>&t=<?= h((string) $m['token']) ?>">Pesan lagi</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
<div class="toast-wrap" id="toast-wrap"></div>
<script src="<?= h(asset('assets/app.js')) ?>"></script>
<?php if (($_GET['cetak'] ?? '') === '1'): ?>
<script>
/* Cetak otomatis: dijalankan setelah halaman selesai dimuat (dialog cetak tetap muncul
   kecuali browser kasir dijalankan dengan mode cetak senyap seperti --kiosk-printing). */
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
<?php endif; ?>
</body>
</html>
