<?php
declare(strict_types=1);

/* Akses kasir: katalog bergambar, keranjang, pembayaran, pesanan masuk dari pelanggan. */

require_once __DIR__ . '/lib.php';

$user = require_login(['owner', 'kasir']);

$produk = produk_semua(true);
$kategori = kategori_semua(true);
$meja = meja_semua(true);
$hari = hari_ini();

$dataProduk = [];
foreach ($produk as $p) {
    $dataProduk[] = [
        'id'          => (int) $p['id'],
        'nama'        => (string) $p['nama'],
        'kategori_id' => (int) ($p['kategori_id'] ?? 0),
        'kategori'    => (string) ($p['kategori'] ?? ''),
        'harga'       => (float) $p['harga'],
        'harga_teks'  => rupiah($p['harga']),
        'harga_member'=> (float) $p['harga_member'],
        'harga_member_teks' => (float) $p['harga_member'] > 0 ? rupiah($p['harga_member']) : '',
        'foto'        => (string) $p['foto_url'],
        'deskripsi'   => (string) $p['deskripsi'],
        'favorit'     => (int) $p['favorit'] === 1,
    ];
}

/* Pesanan yang perlu ditindak kasir (belum dibayar atau belum diproses dapur). */
$st = db()->prepare('SELECT p.*, m.nomor AS meja_nomor, m.nama AS meja_nama
                     FROM pesanan p LEFT JOIN meja m ON m.id = p.meja_id
                     WHERE p.tanggal = ? AND p.status <> ? AND (p.status_bayar <> ? OR p.status = ?)
                     ORDER BY p.created_at ASC');
$st->execute([$hari, PS_BATAL, BAYAR_LUNAS, PS_BARU]);
$masuk = [];
foreach ($st->fetchAll() as $p) {
    $p['items'] = pesanan_items((int) $p['id']);
    $masuk[] = pesanan_ringkas($p);
}

$kanan = '<a class="tombol tombol-kecil" href="dapur.php" target="_blank" rel="noopener">' . icon('chef') . ' Display Dapur</a>'
       . '<a class="tombol tombol-kecil" href="display.php" target="_blank" rel="noopener">' . icon('tv') . ' Display Menu</a>'
       . '<button class="tombol tombol-kecil tombol-gelap" type="button" id="btn-layar2" title="Tampilkan display menu di monitor kedua">' . icon('monitor') . ' Monitor 2</button>';

page_head('Akses Kasir', 'kasir.php', ['kelas' => 'kasir-halaman', 'kanan' => $kanan]);
flash();
?>
<div id="kasir-app"
     data-qris="<?= setting_bool('qris_aktif', true) ? 1 : 0 ?>"
     data-tunai="<?= setting_bool('tunai_aktif', true) ? 1 : 0 ?>"
     data-pajak="<?= h((string) setting_num('pajak_persen', 0)) ?>"
     data-service="<?= h((string) setting_num('service_persen', 0)) ?>"
     data-diskon-member="<?= h((string) setting_num('diskon_member_persen', 0)) ?>"
     data-pembulatan="<?= h((string) (int) setting_num('pembulatan', 0)) ?>"
     data-qris-gambar="<?= h(setting('qris_gambar_url')) ?>"
     data-qris-catatan="<?= h(setting('qris_catatan')) ?>">

    <section class="kartu" style="margin-bottom:22px">
        <div class="kartu-judul">
            <h3><?= icon('bell') ?> Pesanan Masuk <span class="pil pil-warn" id="jumlah-masuk"><?= count($masuk) ?></span></h3>
            <span class="kartu-sub" style="margin:0">Pesanan dari pelanggan (QR meja) &amp; yang belum dibayar</span>
        </div>
        <div class="masuk-strip" id="masuk-strip">
            <p class="kosong-teks" style="margin:0">Belum ada pesanan yang menunggu.</p>
        </div>
    </section>

    <div class="kasir-layout">
        <div class="pos-kiri">
            <section class="kartu">
                <div class="kartu-judul">
                    <h3><?= icon('coffee') ?> Menu Kafe</h3>
                    <input type="search" id="cari-produk" placeholder="Cari menu..." style="max-width:240px">
                </div>
                <div class="kategori-bar" id="kategori-bar">
                    <button class="kategori-chip is-aktif" type="button" data-kategori="0">Semua Menu</button>
                    <?php foreach ($kategori as $k): ?>
                        <button class="kategori-chip" type="button" data-kategori="<?= (int) $k['id'] ?>"><?= h($k['nama']) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="produk-grid" id="produk-grid" style="margin-top:16px"></div>
                <p class="kosong-teks" id="produk-kosong" hidden>Tidak ada menu yang cocok.</p>
            </section>
        </div>

        <div class="pos-kanan">
            <section class="kartu">
                <div class="kartu-judul"><h3><?= icon('cart') ?> Pesanan</h3><span class="pil" id="jumlah-item">0 item</span></div>
                <div class="keranjang-list" id="keranjang">
                    <p class="keranjang-kosong">Keranjang masih kosong.<br>Pilih menu di sebelah kiri.</p>
                </div>
                <div class="form-baris form-2" style="margin-top:14px">
                    <div class="kolom">
                        <label for="meja-id">Meja</label>
                        <select id="meja-id">
                            <option value="0">— tanpa meja (bawa pulang) —</option>
                            <?php foreach ($meja as $m): ?>
                                <option value="<?= (int) $m['id'] ?>"><?= h(meja_label($m)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kolom">
                        <label for="diskon-persen">Diskon (%)</label>
                        <input type="number" id="diskon-persen" min="0" max="100" step="1" value="0">
                    </div>
                </div>
                <div class="kolom" style="margin-bottom:10px">
                    <label for="cari-pelanggan">Pelanggan (member)</label>
                    <input type="search" id="cari-pelanggan" placeholder="Cari nama / nomor HP member" autocomplete="off">
                    <div id="hasil-pelanggan"></div>
                    <span class="bantuan" id="info-pelanggan">Belum ada pelanggan dipilih (tanpa member harga tetap normal).</span>
                </div>
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="nama-pelanggan">Nama (opsional)</label>
                        <input type="text" id="nama-pelanggan">
                    </div>
                    <div class="kolom">
                        <label for="telepon">No. HP (opsional)</label>
                        <input type="tel" id="telepon">
                    </div>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="catatan-pesanan">Catatan pesanan</label>
                    <input type="text" id="catatan-pesanan" placeholder="Contoh: tanpa gula, pisahkan sambal">
                </div>

                <div class="total-baris"><span>Subtotal</span><span id="t-subtotal">Rp 0</span></div>
                <div class="total-baris" id="baris-diskon" hidden><span>Diskon</span><span id="t-diskon">Rp 0</span></div>
                <div class="total-baris" id="baris-service" hidden><span>Service</span><span id="t-service">Rp 0</span></div>
                <div class="total-baris" id="baris-pajak" hidden><span>Pajak</span><span id="t-pajak">Rp 0</span></div>
                <div class="total-baris besar"><span>Total</span><span id="t-total">Rp 0</span></div>

                <div class="kolom" style="margin:14px 0 10px">
                    <label>Metode pembayaran</label>
                    <div class="pembayaran-pilih" id="metode-pilih">
                        <button class="pembayaran-opsi is-aktif" type="button" data-metode="TUNAI">Tunai di Kasir</button>
                        <button class="pembayaran-opsi" type="button" data-metode="QRIS">QRIS</button>
                    </div>
                </div>
                <div class="kolom" id="bungkus-uang">
                    <label for="uang-diterima">Uang diterima (Rp)</label>
                    <input type="number" id="uang-diterima" min="0" step="1000" placeholder="0">
                    <div class="uang-kembalian">Kembalian: <strong id="t-kembali">Rp 0</strong></div>
                </div>
                <div class="form-aksi" style="margin-top:14px">
                    <button class="tombol tombol-utama tombol-blok" type="button" id="btn-bayar"><?= icon('cash') ?> Simpan &amp; Bayar</button>
                    <button class="tombol tombol-blok" type="button" id="btn-simpan"><?= icon('receipt') ?> Simpan (bayar nanti)</button>
                    <button class="tombol tombol-blok tombol-bahaya" type="button" id="btn-kosongkan">Kosongkan keranjang</button>
                </div>
            </section>
        </div>
    </div>
</div>

<script id="data-produk" type="application/json"><?= json_encode($dataProduk, JSON_UNESCAPED_UNICODE) ?></script>
<script id="data-masuk" type="application/json"><?= json_encode($masuk, JSON_UNESCAPED_UNICODE) ?></script>

<div class="modal" id="modal-bayar" hidden>
    <div class="modal-kotak">
        <h3 id="modal-judul">Pembayaran</h3>
        <div id="modal-isi"></div>
        <div class="modal-aksi">
            <button class="tombol" type="button" id="modal-tutup">Tutup</button>
            <button class="tombol tombol-utama" type="button" id="modal-konfirmasi">Konfirmasi Pembayaran</button>
        </div>
    </div>
</div>

<div class="modal" id="modal-struk" hidden>
    <div class="modal-kotak" style="max-width:420px">
        <h3>Pembayaran berhasil</h3>
        <div id="modal-struk-isi"></div>
        <div class="modal-aksi">
            <button class="tombol" type="button" id="struk-tutup">Tutup</button>
            <button class="tombol tombol-utama" type="button" id="struk-cetak"><?= icon('print') ?> Cetak / Simpan PDF</button>
        </div>
    </div>
</div>
<?php page_end(['assets/kasir.js', 'assets/layar2.js']); ?>
