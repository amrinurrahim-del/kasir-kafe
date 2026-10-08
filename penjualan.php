<?php
declare(strict_types=1);

/* Data penjualan: daftar transaksi dengan filter dan rincian per pesanan. */

require_once __DIR__ . '/lib.php';

$user = require_owner();

$dari = tanggal_valid((string) ($_GET['dari'] ?? '')) ?: hari_ini();
$sampai = tanggal_valid((string) ($_GET['sampai'] ?? '')) ?: hari_ini();
if ($sampai < $dari) {
    $tmp = $dari;
    $dari = $sampai;
    $sampai = $tmp;
}
$statusBayar = (string) ($_GET['bayar'] ?? '');
$metode = (string) ($_GET['metode'] ?? '');
$statusPesanan = (string) ($_GET['status'] ?? '');
$cari = trim((string) ($_GET['cari'] ?? ''));

$rows = pesanan_daftar($dari, $sampai, [
    'bayar'  => $statusBayar,
    'metode' => $metode,
    'status' => $statusPesanan,
    'cari'   => $cari,
]);
$ringkas = laporan_ringkas($rows);
$belum = 0;
$nilaiBelum = 0.0;
foreach ($rows as $r) {
    if ((string) $r['status'] === PS_BATAL) {
        continue;
    }
    if ((string) $r['status_bayar'] !== BAYAR_LUNAS) {
        $belum++;
        $nilaiBelum += (float) $r['total'];
    }
}

page_head('Data Penjualan', 'penjualan.php');
flash();
?>
<section class="kartu" style="margin-bottom:22px">
    <div class="kartu-judul">
        <h3><?= icon('search') ?> Filter Data</h3>
        <div class="baris-tombol">
            <a class="tombol tombol-kecil" href="penjualan.php?dari=<?= h(urlencode(tgl_input(hari_ini()))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">Hari ini</a>
            <a class="tombol tombol-kecil" href="penjualan.php?dari=<?= h(urlencode(tgl_input(date('Y-m-d', strtotime('-6 days'))))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">7 hari</a>
            <a class="tombol tombol-kecil" href="penjualan.php?dari=<?= h(urlencode(date('01/m/Y'))) ?>&sampai=<?= h(urlencode(tgl_input(hari_ini()))) ?>">Bulan ini</a>
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
                <label for="bayar">Status pembayaran</label>
                <select id="bayar" name="bayar">
                    <option value="">Semua</option>
                    <option value="LUNAS" <?= $statusBayar === 'LUNAS' ? 'selected' : '' ?>>Sudah dibayar</option>
                    <option value="BELUM" <?= $statusBayar === 'BELUM' ? 'selected' : '' ?>>Belum dibayar</option>
                </select>
            </div>
        </div>
        <div class="form-baris form-3">
            <div class="kolom">
                <label for="metode">Metode bayar</label>
                <select id="metode" name="metode">
                    <option value="">Semua</option>
                    <?php foreach (LABEL_METODE as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= $metode === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="kolom">
                <label for="status">Status pesanan</label>
                <select id="status" name="status">
                    <option value="">Semua</option>
                    <?php foreach ([PS_BARU, PS_DITERIMA, PS_DISIAPKAN, PS_SIAP, PS_SELESAI, PS_BATAL] as $s): ?>
                        <option value="<?= h($s) ?>" <?= $statusPesanan === $s ? 'selected' : '' ?>><?= h(LABEL_STATUS[$s]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="kolom">
                <label for="cari">Cari</label>
                <input type="search" id="cari" name="cari" value="<?= h($cari) ?>" placeholder="kode / nama / no. HP">
            </div>
        </div>
        <div class="form-aksi">
            <button class="tombol tombol-utama" type="submit"><?= icon('search') ?> Tampilkan</button>
            <a class="tombol" href="laporan.php?dari=<?= h(urlencode(tgl_input($dari))) ?>&sampai=<?= h(urlencode(tgl_input($sampai))) ?>"><?= icon('chart') ?> Lihat Laporan</a>
        </div>
    </form>
</section>

<div class="stat-grid" style="margin-bottom:22px">
    <div class="stat is-gelap">
        <span>Total penjualan (lunas)</span>
        <strong><?= h(rupiah($ringkas['total'])) ?></strong>
        <small><?= (int) $ringkas['transaksi'] ?> transaksi</small>
    </div>
    <div class="stat">
        <span>Rata-rata per transaksi</span>
        <strong><?= h(rupiah($ringkas['rata'])) ?></strong>
        <small>periode <?= h(tgl_label($dari, false)) ?> – <?= h(tgl_label($sampai, false)) ?></small>
    </div>
    <div class="stat">
        <span>Belum dibayar</span>
        <strong><?= (int) $belum ?></strong>
        <small>senilai <?= h(rupiah($nilaiBelum)) ?></small>
    </div>
    <div class="stat">
        <span>Jumlah baris data</span>
        <strong><?= count($rows) ?></strong>
        <small>termasuk yang dibatalkan</small>
    </div>
</div>

<section class="kartu">
    <div class="kartu-judul">
        <h3><?= icon('receipt') ?> Daftar Transaksi</h3>
        <input type="search" data-saring="#tabel-jual" placeholder="Saring cepat di tabel..." style="max-width:260px">
    </div>
    <?php if (!$rows): ?>
        <p class="kosong-teks">Tidak ada transaksi pada filter ini.</p>
    <?php else: ?>
        <div class="tabel-bungkus">
            <table class="tabel" id="tabel-jual">
                <thead>
                    <tr>
                        <th>Waktu</th><th>Kode</th><th>Meja</th><th>Pelanggan</th><th>Item</th>
                        <th>Metode</th><th>Bayar</th><th>Pesanan</th><th class="angka">Total</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $items = pesanan_items((int) $r['id']);
                    $jmlItem = 0.0;
                    $namaItem = [];
                    foreach ($items as $it) {
                        $jmlItem += (float) $it['qty'];
                        $namaItem[] = angka_qty($it['qty']) . '× ' . $it['nama'];
                    }
                    ?>
                    <tr>
                        <td><?= h(tgl_label((string) $r['tanggal'], false)) ?><br><em style="font-style:normal;color:var(--muted);font-size:.78rem"><?= h(jam_teks((string) $r['created_at'], false)) ?></em></td>
                        <td><strong><?= h($r['kode']) ?></strong></td>
                        <td><?= h(pesanan_label_meja($r)) ?></td>
                        <td><?= h($r['nama_pelanggan'] !== '' ? (string) $r['nama_pelanggan'] : '—') ?><br><em style="font-style:normal;color:var(--muted);font-size:.78rem"><?= h($r['telepon']) ?></em></td>
                        <td style="max-width:260px"><span style="font-size:.82rem"><?= h(angka_qty($jmlItem)) ?> item</span><br><em style="font-style:normal;color:var(--muted);font-size:.76rem"><?= h(potong_teks(implode(', ', $namaItem), 70)) ?></em></td>
                        <td><?= h(LABEL_METODE[(string) $r['metode_bayar']] ?? '') ?></td>
                        <td><span class="pil <?= (string) $r['status_bayar'] === BAYAR_LUNAS ? 'pil-ok' : 'pil-err' ?>"><?= h((string) $r['status_bayar'] === BAYAR_LUNAS ? 'Lunas' : 'Belum') ?></span></td>
                        <td><span class="pil <?= (string) $r['status'] === PS_BATAL ? 'pil-err' : ((string) $r['status'] === PS_SELESAI ? 'pil-ok' : 'pil-info') ?>"><?= h(LABEL_STATUS_PENDEK[(string) $r['status']] ?? '') ?></span></td>
                        <td class="angka"><?= h(rupiah($r['total'])) ?></td>
                        <td>
                            <div class="baris-tombol">
                                <button class="tombol tombol-kecil" type="button" data-detail="<?= (int) $r['id'] ?>">Detail</button>
                                <a class="tombol tombol-kecil" href="struk.php?p=<?= h(urlencode((string) $r['kode'])) ?>&k=<?= h(urlencode((string) $r['token'])) ?>" target="_blank" rel="noopener">Struk</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8">Total penjualan lunas</td>
                        <td class="angka"><?= h(rupiah($ringkas['total'])) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="modal" id="modal-detail" hidden>
    <div class="modal-kotak">
        <h3 id="detail-judul">Rincian Pesanan</h3>
        <div id="detail-isi"></div>
        <div class="modal-aksi"><button class="tombol" type="button" id="detail-tutup">Tutup</button></div>
    </div>
</div>

<script>
document.querySelectorAll('[data-detail]').forEach(function (b) {
    b.addEventListener('click', function () {
        fetch('api.php?action=pesanan_detail&id=' + b.dataset.detail, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { window.kabar(d.error || 'Data tidak ditemukan.', 'err'); return; }
                document.getElementById('detail-judul').textContent = 'Pesanan ' + d.kode + ' — ' + d.meja;
                var html = '';
                html += '<div class="info-baris"><span>Waktu</span><strong>' + d.waktu + '</strong></div>';
                html += '<div class="info-baris"><span>Pelanggan</span><strong>' + (d.nama || '—') + (d.telepon ? ' · ' + d.telepon : '') + '</strong></div>';
                html += '<div class="info-baris"><span>Sumber</span><strong>' + d.sumber_label + '</strong></div>';
                html += '<div class="info-baris"><span>Kasir</span><strong>' + d.kasir + '</strong></div>';
                html += '<div class="info-baris"><span>Status</span><strong>' + d.status_label + ' · ' + d.metode_label + ' · ' + (d.status_bayar === 'LUNAS' ? 'Lunas' : 'Belum dibayar') + '</strong></div>';
                html += '<h4 style="margin-top:14px">Item</h4>';
                (d.items || []).forEach(function (it) {
                    html += '<div class="info-baris"><span>' + it.qty_teks + '× ' + it.nama + (it.catatan ? ' — ' + it.catatan : '') + '</span><strong>' + it.subtotal + '</strong></div>';
                });
                html += '<div class="info-baris"><span>Subtotal</span><strong>' + d.subtotal + '</strong></div>';
                html += '<div class="info-baris"><span>Diskon</span><strong>' + d.diskon + '</strong></div>';
                html += '<div class="info-baris"><span>Service</span><strong>' + d.service + '</strong></div>';
                html += '<div class="info-baris"><span>Pajak</span><strong>' + d.pajak + '</strong></div>';
                html += '<div class="info-baris"><span><strong>Total</strong></span><strong>' + d.total + '</strong></div>';
                if (d.log && d.log.length) {
                    html += '<h4 style="margin-top:14px">Riwayat status</h4>';
                    d.log.forEach(function (l) {
                        html += '<div class="info-baris"><span>' + l.label + '</span><strong>' + l.jam + ' · ' + l.oleh + '</strong></div>';
                    });
                }
                document.getElementById('detail-isi').innerHTML = html;
                document.getElementById('modal-detail').hidden = false;
            });
    });
});
document.getElementById('detail-tutup').addEventListener('click', function () {
    document.getElementById('modal-detail').hidden = true;
});
</script>
<?php page_end(); ?>
