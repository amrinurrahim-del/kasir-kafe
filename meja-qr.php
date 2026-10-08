<?php
declare(strict_types=1);

/* Kartu QR meja untuk dicetak dan diletakkan di meja (khusus owner). */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/qr.php';

$user = require_owner();

$id = (int) ($_GET['id'] ?? 0);
$meja = $id > 0 ? [meja_row($id)] : meja_semua(true);
$meja = array_values(array_filter($meja));
$ukuranQr = max(80, min(240, (int) ($_GET['ukuran'] ?? 150)));
$dasar = url_publik_dasar();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kartu QR Meja — <?= h(nama_kafe()) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
<style>
.meja-qr-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.meja-qr-kartu {
    border: 1.5px dashed rgba(122, 74, 42, .5); border-radius: 18px; padding: 18px; text-align: center;
    background: #fff; break-inside: avoid;
}
.meja-qr-kartu h3 { margin: 8px 0 2px; font-size: 1.5rem; letter-spacing: .04em; }
.meja-qr-kartu .nama-kafe { font-size: .84rem; letter-spacing: .16em; text-transform: uppercase; color: var(--coklat); font-weight: 800; }
.meja-qr-kartu .qr { margin: 10px auto; }
.meja-qr-kartu .qr svg { display: block; margin: 0 auto; }
.meja-qr-kartu p { margin: 6px 0 0; font-size: .82rem; color: var(--muted); }
@media print {
    .struk-aksi, .tab-bar, .topbar { display: none !important; }
    body { background: #fff; }
    .meja-qr-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .meja-qr-kartu { border-color: #bbb; }
}
</style>
</head>
<body class="app" style="background:var(--paper)">
<div class="utama" style="margin-left:0">
    <div class="wrap">
        <section class="kartu" style="margin-bottom:18px">
            <div class="kartu-judul" style="margin-bottom:6px">
                <h3><?= icon('qr') ?> Kartu QR Pemesanan Meja (<?= count($meja) ?>)</h3>
                <div class="baris-tombol">
                    <button class="tombol tombol-utama" type="button" onclick="window.print()"><?= icon('print') ?> Cetak Kartu</button>
                    <a class="tombol" href="pengaturan.php?tab=meja">Kembali ke Pengaturan</a>
                </div>
            </div>
            <p class="kartu-sub" style="margin:0">
                Cetak halaman ini (A4), potong, lalu letakkan di setiap meja. Pelanggan memindai QR →
                melihat menu kafe → memilih menu &amp; metode pembayaran → pesanan langsung masuk ke kasir.
            </p>
            <?php if ($dasar === ''): ?>
                <p class="flash flash-err" style="margin-top:12px">
                    Alamat publik aplikasi belum diketahui, sehingga QR berisi tautan yang tidak lengkap.
                    Isi kolom <strong>Alamat publik aplikasi</strong> di Pengaturan → Pembayaran &amp; QRIS.
                </p>
            <?php endif; ?>
        </section>

        <div class="meja-qr-grid">
            <?php foreach ($meja as $m): ?>
                <?php $tautan = url_meja($m); ?>
                <div class="meja-qr-kartu">
                    <?php if (logo_url() !== ''): ?>
                        <img src="<?= h(logo_url()) ?>" alt="" style="max-height:52px;max-width:150px;object-fit:contain">
                    <?php endif; ?>
                    <div class="nama-kafe"><?= h(nama_kafe()) ?></div>
                    <h3><?= h(meja_label($m)) ?></h3>
                    <div class="qr">
                        <?php if ($tautan !== ''): ?>
                            <?= qr_svg($tautan, $ukuranQr, 'M', 4, '#241610', '#ffffff') ?>
                        <?php else: ?>
                            <span class="pil pil-err">Alamat publik belum diisi</span>
                        <?php endif; ?>
                    </div>
                    <p><strong>Pindai QR ini untuk melihat menu &amp; memesan</strong></p>
                    <p>Tanpa perlu unduh aplikasi — cukup kamera HP Anda.</p>
                    <?php if ($tautan !== ''): ?>
                        <p style="word-break:break-all;font-size:.68rem"><?= h($tautan) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
