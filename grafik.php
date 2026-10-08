<?php
declare(strict_types=1);

/* Grafik penjualan: omzet harian, jam sibuk, metode bayar, kategori, produk terlaris, meja. */

require_once __DIR__ . '/lib.php';

$user = require_owner();

$dari = tanggal_valid((string) ($_GET['dari'] ?? '')) ?: date('Y-m-d', strtotime('-13 days'));
$sampai = tanggal_valid((string) ($_GET['sampai'] ?? '')) ?: hari_ini();
if ($sampai < $dari) {
    $tmp = $dari;
    $dari = $sampai;
    $sampai = $tmp;
}
$data = grafik_data($dari, $sampai);

page_head('Grafik Penjualan', 'grafik.php');
flash();
?>
<section class="kartu" style="margin-bottom:22px">
    <div class="kartu-judul">
        <h3><?= icon('chart') ?> Periode Grafik</h3>
        <div class="baris-tombol">
            <a class="tombol tombol-kecil" href="grafik.php?dari=<?= h(urlencode(tgl_input(date('Y-m-d', strtotime('-6 days'))))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">7 hari</a>
            <a class="tombol tombol-kecil" href="grafik.php?dari=<?= h(urlencode(tgl_input(date('Y-m-d', strtotime('-29 days'))))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">30 hari</a>
            <a class="tombol tombol-kecil" href="grafik.php?dari=<?= h(urlencode(date('01/m/Y'))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">Bulan ini</a>
        </div>
    </div>
    <form method="get">
        <div class="form-baris form-2">
            <div class="kolom">
                <label for="dari">Dari tanggal</label>
                <input type="text" class="tgl" id="dari" name="dari" value="<?= h(tgl_input($dari)) ?>">
            </div>
            <div class="kolom">
                <label for="sampai">Sampai tanggal</label>
                <input type="text" class="tgl" id="sampai" name="sampai" value="<?= h(tgl_input($sampai)) ?>">
            </div>
        </div>
        <div class="form-aksi">
            <button class="tombol tombol-utama" type="submit"><?= icon('search') ?> Tampilkan</button>
        </div>
    </form>
</section>

<div class="stat-grid" style="margin-bottom:22px">
    <div class="stat is-gelap">
        <span>Omzet periode ini</span>
        <strong><?= h(rupiah($data['total'])) ?></strong>
        <small><?= (int) $data['transaksi'] ?> transaksi lunas</small>
    </div>
    <div class="stat">
        <span>Rata-rata harian</span>
        <strong><?= h(rupiah(count($data['harian']) > 0 ? $data['total'] / count($data['harian']) : 0)) ?></strong>
        <small><?= count($data['harian']) ?> hari dalam periode</small>
    </div>
    <div class="stat">
        <span>Rata-rata per transaksi</span>
        <strong><?= h(rupiah($data['transaksi'] > 0 ? $data['total'] / $data['transaksi'] : 0)) ?></strong>
        <small>semua metode bayar</small>
    </div>
    <div class="stat">
        <span>Jam tersibuk</span>
        <strong><?php
            $jamMaks = -1;
            $nilaiMaks = 0;
            foreach ($data['per_jam'] as $j => $v) {
                if ($v > $nilaiMaks) {
                    $nilaiMaks = $v;
                    $jamMaks = (int) $j;
                }
            }
            echo $jamMaks >= 0 ? h(sprintf('%02d:00', $jamMaks)) : '—';
        ?></strong>
        <small><?= $nilaiMaks > 0 ? h(rupiah($nilaiMaks)) : 'belum ada data' ?></small>
    </div>
</div>

<section class="kartu">
    <div class="kartu-judul"><h3><?= icon('chart') ?> Omzet Harian</h3></div>
    <div class="grafik-bungkus" id="grafik-harian"></div>
</section>

<div class="grid-kartu grid-2" style="margin-top:20px">
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('receipt') ?> Jumlah Transaksi per Hari</h3></div>
        <div class="grafik-bungkus" id="grafik-transaksi"></div>
    </section>
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('clock') ?> Jam Sibuk (omzet per jam)</h3></div>
        <div class="grafik-bungkus" id="grafik-jam"></div>
    </section>
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('cash') ?> Metode Pembayaran</h3></div>
        <div class="grafik-bungkus" id="grafik-metode"></div>
    </section>
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('table') ?> Omzet per Meja (8 teratas)</h3></div>
        <div class="grafik-bungkus" id="grafik-meja"></div>
    </section>
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('box') ?> Omzet per Kategori</h3></div>
        <div id="grafik-kategori"></div>
    </section>
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('coffee') ?> Produk Terlaris</h3></div>
        <div id="grafik-produk"></div>
    </section>
</div>

<script id="data-grafik" type="application/json"><?= json_encode($data, JSON_UNESCAPED_UNICODE) ?></script>
<?php page_end('assets/grafik.js'); ?>
