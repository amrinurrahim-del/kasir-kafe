<?php
declare(strict_types=1);

/* Laporan penjualan: ringkasan periode + unduhan PDF / Excel / CSV. */

require_once __DIR__ . '/lib.php';

$user = require_owner();

$dari = tanggal_valid((string) ($_GET['dari'] ?? '')) ?: date('Y-m-01');
$sampai = tanggal_valid((string) ($_GET['sampai'] ?? '')) ?: hari_ini();
if ($sampai < $dari) {
    $tmp = $dari;
    $dari = $sampai;
    $sampai = $tmp;
}
$metode = (string) ($_GET['metode'] ?? '');
$hanyaLunas = (string) ($_GET['lunas'] ?? '1') === '1';

$rows = pesanan_daftar($dari, $sampai, ['metode' => $metode]);
$lunas = $hanyaLunas ? array_values(array_filter($rows, static fn($r) => (string) $r['status_bayar'] === BAYAR_LUNAS)) : $rows;
$ringkas = laporan_ringkas($rows, $hanyaLunas);
$produk = laporan_produk($dari, $sampai, ['hanya_lunas' => $hanyaLunas]);

/* ---------------- Unduhan ---------------- */
$unduh = (string) ($_GET['unduh'] ?? '');
if ($unduh !== '') {
    require_once __DIR__ . '/cetak.php';
    $namaSingkat = 'laporan-penjualan-' . $dari . '-sd-' . $sampai;
    if ($unduh === 'pdf') {
        $isi = laporan_pdf($dari, $sampai, $lunas, $ringkas, $produk);
        kirim_file($namaSingkat . '.pdf', 'application/pdf', $isi);
    }
    $header = ['Tanggal', 'Kode', 'Meja', 'Pelanggan', 'Telepon', 'Metode', 'Status bayar', 'Status pesanan', 'Kasir', 'Jumlah item', 'Subtotal', 'Diskon', 'Service', 'Pajak', 'Total'];
    $baris = [];
    foreach ($lunas as $r) {
        $jml = 0.0;
        foreach (pesanan_items((int) $r['id']) as $it) {
            $jml += (float) $it['qty'];
        }
        $baris[] = [
            (string) $r['tanggal'],
            (string) $r['kode'],
            pesanan_label_meja($r),
            (string) $r['nama_pelanggan'],
            (string) $r['telepon'],
            LABEL_METODE[(string) $r['metode_bayar']] ?? '',
            (string) $r['status_bayar'],
            LABEL_STATUS[(string) $r['status']] ?? '',
            nama_pengguna((string) ($r['bayar_oleh'] !== '' ? $r['bayar_oleh'] : $r['dibuat_oleh'])),
            angka_qty($jml),
            (float) $r['subtotal'],
            (float) $r['diskon'],
            (float) $r['service'],
            (float) $r['pajak'],
            (float) $r['total'],
        ];
    }
    $baris[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', 'TOTAL LUNAS', (float) $ringkas['total']];
    if ($unduh === 'xlsx') {
        $x = xlsx_bytes('Laporan Penjualan', $header, $baris, [10 => true, 11 => true, 12 => true, 13 => true, 14 => true]);
        if ($x === null) {
            kirim_csv($namaSingkat . '.csv', $header, $baris);
        }
        kirim_file($namaSingkat . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $x);
    }
    kirim_csv($namaSingkat . '.csv', $header, $baris);
}

$perHari = [];
foreach ($rows as $r) {
    if ($hanyaLunas && (string) $r['status_bayar'] !== BAYAR_LUNAS) {
        continue;
    }
    if ((string) $r['status'] === PS_BATAL) {
        continue;
    }
    $t = (string) $r['tanggal'];
    if (!isset($perHari[$t])) {
        $perHari[$t] = ['tanggal' => $t, 'jumlah' => 0, 'total' => 0.0, 'item' => 0.0];
    }
    $perHari[$t]['jumlah']++;
    $perHari[$t]['total'] += (float) $r['total'];
    foreach (pesanan_items((int) $r['id']) as $it) {
        $perHari[$t]['item'] += (float) $it['qty'];
    }
}
ksort($perHari);

$hariTersibuk = null;
foreach ($perHari as $h) {
    if ($hariTersibuk === null || $h['total'] > $hariTersibuk['total']) {
        $hariTersibuk = $h;
    }
}

page_head('Laporan Penjualan', 'laporan.php');
flash();
?>
<section class="kartu" style="margin-bottom:22px">
    <div class="kartu-judul">
        <h3><?= icon('chart') ?> Periode Laporan</h3>
        <div class="baris-tombol">
            <a class="tombol tombol-kecil" href="laporan.php?dari=<?= h(urlencode(tgl_input(hari_ini()))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">Hari ini</a>
            <a class="tombol tombol-kecil" href="laporan.php?dari=<?= h(urlencode(tgl_input(date('Y-m-d', strtotime('-6 days'))))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">7 hari</a>
            <a class="tombol tombol-kecil" href="laporan.php?dari=<?= h(urlencode(date('01/m/Y'))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">Bulan ini</a>
            <a class="tombol tombol-kecil" href="laporan.php?dari=<?= h(urlencode(tgl_input(date('Y-m-01', strtotime('-1 month'))))) ?>&sampai=<?= h(urlencode(tgl_input(date('Y-m-t', strtotime('-1 month'))))) ?>">Bulan lalu</a>
        </div>
    </div>
    <form method="get">
        <div class="form-baris form-3">
            <div class="kolom">
                <label for="dari">Dari tanggal</label>
                <input type="text" class="tgl" id="dari" name="dari" value="<?= h(tgl_input($dari)) ?>">
            </div>
            <div class="kolom">
                <label for="sampai">Sampai tanggal</label>
                <input type="text" class="tgl" id="sampai" name="sampai" value="<?= h(tgl_input($sampai)) ?>">
            </div>
            <div class="kolom">
                <label for="metode">Metode bayar</label>
                <select id="metode" name="metode">
                    <option value="">Semua metode</option>
                    <?php foreach (LABEL_METODE as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= $metode === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-aksi">
            <label class="cek" style="margin-right:12px"><input type="checkbox" name="lunas" value="1" <?= $hanyaLunas ? 'checked' : '' ?>> Hitung hanya transaksi yang sudah dibayar</label>
            <button class="tombol tombol-utama" type="submit"><?= icon('search') ?> Tampilkan</button>
        </div>
    </form>
    <div class="baris-tombol" style="margin-top:14px">
        <a class="tombol" href="laporan.php?<?= h(http_build_query(['dari' => tgl_input($dari), 'sampai' => tgl_input($sampai), 'metode' => $metode, 'lunas' => $hanyaLunas ? '1' : '0', 'unduh' => 'pdf'])) ?>"><?= icon('download') ?> Unduh PDF</a>
        <a class="tombol" href="laporan.php?<?= h(http_build_query(['dari' => tgl_input($dari), 'sampai' => tgl_input($sampai), 'metode' => $metode, 'lunas' => $hanyaLunas ? '1' : '0', 'unduh' => 'xlsx'])) ?>"><?= icon('download') ?> Unduh Excel</a>
        <a class="tombol" href="laporan.php?<?= h(http_build_query(['dari' => tgl_input($dari), 'sampai' => tgl_input($sampai), 'metode' => $metode, 'lunas' => $hanyaLunas ? '1' : '0', 'unduh' => 'csv'])) ?>"><?= icon('download') ?> Unduh CSV</a>
        <button class="tombol" type="button" onclick="window.print()"><?= icon('print') ?> Cetak</button>
    </div>
</section>

<div class="stat-grid" style="margin-bottom:22px">
    <div class="stat is-gelap">
        <span>Total penjualan</span>
        <strong><?= h(rupiah($ringkas['total'])) ?></strong>
        <small><?= h(tgl_label($dari, false)) ?> – <?= h(tgl_label($sampai, false)) ?></small>
    </div>
    <div class="stat">
        <span>Jumlah transaksi</span>
        <strong><?= (int) $ringkas['transaksi'] ?></strong>
        <small>rata-rata <?= h(rupiah($ringkas['rata'])) ?></small>
    </div>
    <div class="stat">
        <span>Hari terbaik</span>
        <strong style="font-size:1.1rem"><?= $hariTersibuk ? h(tgl_label($hariTersibuk['tanggal'], false)) : '—' ?></strong>
        <small><?= $hariTersibuk ? h(rupiah($hariTersibuk['total'])) : 'belum ada data' ?></small>
    </div>
    <div class="stat">
        <span>Item terjual</span>
        <strong><?php
            $totItem = 0.0;
            foreach ($perHari as $h) { $totItem += $h['item']; }
            echo h(angka_qty($totItem));
        ?></strong>
        <small>porsi / gelas</small>
    </div>
</div>

<div class="grid-kartu grid-2">
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('chart') ?> Penjualan per Hari</h3></div>
        <?php if (!$perHari): ?>
            <p class="kosong-teks">Belum ada data pada periode ini.</p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>Tanggal</th><th class="angka">Transaksi</th><th class="angka">Item</th><th class="angka">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($perHari as $h): ?>
                        <tr>
                            <td><?= h(tgl_label($h['tanggal'], true)) ?></td>
                            <td class="angka"><?= (int) $h['jumlah'] ?></td>
                            <td class="angka"><?= h(angka_qty($h['item'])) ?></td>
                            <td class="angka"><?= h(rupiah($h['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td>Total</td><td class="angka"><?= (int) $ringkas['transaksi'] ?></td><td class="angka"><?= h(angka_qty($totItem)) ?></td><td class="angka"><?= h(rupiah($ringkas['total'])) ?></td></tr></tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('cash') ?> Per Metode &amp; Kasir</h3></div>
        <?php if (!$ringkas['per_metode']): ?>
            <p class="kosong-teks">Belum ada data.</p>
        <?php else: ?>
            <?php foreach ($ringkas['per_metode'] as $m => $v): ?>
                <div class="info-baris"><span><?= h(LABEL_METODE[$m] ?? $m) ?></span><strong><?= h(rupiah($v)) ?></strong></div>
            <?php endforeach; ?>
            <h4 style="margin-top:16px">Per kasir</h4>
            <?php foreach ($ringkas['per_kasir'] as $k => $v): ?>
                <div class="info-baris"><span><?= h(nama_pengguna((string) $k)) ?></span><strong><?= h(rupiah($v)) ?></strong></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('box') ?> Per Kategori</h3></div>
        <?php if (!$produk['kategori']): ?>
            <p class="kosong-teks">Belum ada data.</p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>Kategori</th><th class="angka">Item</th><th class="angka">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($produk['kategori'] as $k): ?>
                        <tr><td><?= h($k['nama']) ?></td><td class="angka"><?= h(angka_qty($k['qty'])) ?></td><td class="angka"><?= h(rupiah($k['total'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('coffee') ?> Produk Terlaris</h3></div>
        <?php if (!$produk['produk']): ?>
            <p class="kosong-teks">Belum ada data.</p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>#</th><th>Produk</th><th class="angka">Terjual</th><th class="angka">Total</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($produk['produk'], 0, 15) as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($p['nama']) ?></td>
                            <td class="angka"><?= h(angka_qty($p['qty'])) ?></td>
                            <td class="angka"><?= h(rupiah($p['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php page_end(); ?>
