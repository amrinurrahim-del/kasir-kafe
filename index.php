<?php
declare(strict_types=1);

/* Dashboard owner: ringkasan hari ini + kartu akses menu. */

require_once __DIR__ . '/lib.php';

$user = require_login(['owner']);

$hari = hari_ini();
$rows = pesanan_daftar($hari, $hari);
$ringkas = laporan_ringkas($rows);

$belumBayar = 0;
$aktif = 0;
foreach ($rows as $r) {
    if ((string) $r['status'] === PS_BATAL) {
        continue;
    }
    if ((string) $r['status_bayar'] !== BAYAR_LUNAS) {
        $belumBayar++;
    }
    if ((string) $r['status'] !== PS_SELESAI) {
        $aktif++;
    }
}
$produkAktif = (int) db()->query('SELECT COUNT(*) FROM produk WHERE aktif = 1')->fetchColumn();
$jmlPelanggan = (int) db()->query('SELECT COUNT(*) FROM pelanggan WHERE member = 1')->fetchColumn();
$jmlMeja = (int) db()->query('SELECT COUNT(*) FROM meja WHERE aktif = 1')->fetchColumn();

$tile = [
    ['kasir.php', 'cash', 'Akses Kasir', 'Layani pesanan & pembayaran', true],
    ['produk.php', 'box', 'Data Produk', $produkAktif . ' produk aktif', true],
    ['penjualan.php', 'receipt', 'Data Penjualan', count($rows) . ' transaksi hari ini', true],
    ['laporan.php', 'chart', 'Laporan Penjualan', 'Unduh PDF & Excel', true],
    ['grafik.php', 'chart', 'Grafik', 'Omzet harian & terlaris', true],
    ['pelanggan.php', 'users', 'Data Pelanggan', $jmlPelanggan . ' member terdaftar', true],
    ['dapur.php', 'chef', 'Display Dapur', 'Pantau pesanan masuk', true],
    ['pengaturan.php', 'cog', 'Pengaturan', 'Identitas, meja & QR, pajak', true],
];

page_head('Dashboard', 'index.php');
flash();
?>
<div class="stat-grid">
    <div class="stat is-gelap">
        <span>Penjualan hari ini</span>
        <strong><?= h(rupiah($ringkas['total'])) ?></strong>
        <small><?= (int) $ringkas['transaksi'] ?> transaksi lunas</small>
    </div>
    <div class="stat">
        <span>Pesanan belum dibayar</span>
        <strong><?= (int) $belumBayar ?></strong>
        <small>Perlu ditagih di kasir</small>
    </div>
    <div class="stat">
        <span>Pesanan sedang diproses</span>
        <strong><?= (int) $aktif ?></strong>
        <small>Belum selesai (dapur)</small>
    </div>
    <div class="stat">
        <span>Member terdaftar</span>
        <strong><?= (int) $jmlPelanggan ?></strong>
        <small><?= (int) $jmlMeja ?> meja aktif</small>
    </div>
</div>

<h2 style="margin-top:28px">Akses Cepat</h2>
<div class="tile-grid">
    <?php foreach ($tile as $t): ?>
        <a class="tile" href="<?= h($t[0]) ?>">
            <span class="tile-ikon"><?= icon($t[1]) ?></span>
            <strong><?= h($t[2]) ?></strong>
            <em><?= h($t[3]) ?></em>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid-kartu grid-2" style="margin-top:26px">
    <section class="kartu">
        <div class="kartu-judul">
            <h3><?= icon('bell') ?> Pesanan Perlu Ditindak</h3>
            <a class="tombol tombol-kecil" href="kasir.php">Buka Kasir</a>
        </div>
        <?php
        $perlu = array_values(array_filter($rows, static fn($r) => (string) $r['status'] !== PS_BATAL && ((string) $r['status_bayar'] !== BAYAR_LUNAS || (string) $r['status'] === PS_BARU)));
        ?>
        <?php if (!$perlu): ?>
            <p class="kosong-teks">Tidak ada pesanan yang menunggu. Semua sudah tertangani.</p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>Kode</th><th>Meja</th><th>Status</th><th>Bayar</th><th class="angka">Total</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($perlu, 0, 8) as $r): ?>
                        <tr>
                            <td><strong><?= h($r['kode']) ?></strong></td>
                            <td><?= h(pesanan_label_meja($r)) ?></td>
                            <td><span class="pil <?= (string) $r['status'] === PS_BARU ? 'pil-warn' : 'pil-info' ?>"><?= h(LABEL_STATUS_PENDEK[(string) $r['status']] ?? '') ?></span></td>
                            <td><span class="pil <?= (string) $r['status_bayar'] === BAYAR_LUNAS ? 'pil-ok' : 'pil-err' ?>"><?= h((string) $r['status_bayar'] === BAYAR_LUNAS ? 'Lunas' : 'Belum') ?></span></td>
                            <td class="angka"><?= h(rupiah($r['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="kartu">
        <div class="kartu-judul">
            <h3><?= icon('chart') ?> Ringkasan Pembayaran Hari Ini</h3>
            <a class="tombol tombol-kecil" href="laporan.php">Laporan</a>
        </div>
        <?php if (!$ringkas['per_metode']): ?>
            <p class="kosong-teks">Belum ada transaksi lunas hari ini.</p>
        <?php else: ?>
            <?php foreach ($ringkas['per_metode'] as $m => $v): ?>
                <div class="info-baris">
                    <span><?= h(LABEL_METODE[$m] ?? $m) ?></span>
                    <strong><?= h(rupiah($v)) ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="info-baris">
                <span>Rata-rata per transaksi</span>
                <strong><?= h(rupiah($ringkas['rata'])) ?></strong>
            </div>
        <?php endif; ?>
        <p class="kartu-sub" style="margin-top:14px">
            Tanggal <?= h(tgl_label($hari, true)) ?> · jam server <?= h(date('H:i')) ?>
        </p>
    </section>
</div>
<?php page_end(); ?>
