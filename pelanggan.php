<?php
declare(strict_types=1);

/* Data pelanggan — pendaftaran khusus anggota member. */

require_once __DIR__ . '/lib.php';

$user = require_owner();

$aksi = (string) ($_POST['aksi'] ?? '');
$pesan = '';
$galat = '';

if (is_post()) {
    try {
        switch ($aksi) {
            case 'pelanggan_simpan': {
                $id = (int) ($_POST['id'] ?? 0);
                $nama = trim((string) ($_POST['nama'] ?? ''));
                $telp = trim((string) ($_POST['telepon'] ?? ''));
                if ($nama === '') {
                    throw new RuntimeException('Nama pelanggan wajib diisi.');
                }
                $member = !empty($_POST['member']) ? 1 : 0;
                $email = trim((string) ($_POST['email'] ?? ''));
                $catatan = trim((string) ($_POST['catatan'] ?? ''));

                if ($id > 0) {
                    db()->prepare('UPDATE pelanggan SET nama=?, telepon=?, email=?, member=?, catatan=?, updated_at=? WHERE id=?')
                        ->execute([$nama, $telp, $email, $member, $catatan, now(), $id]);
                    $pesan = 'Data pelanggan "' . $nama . '" diperbarui.';
                } else {
                    if ($telp !== '') {
                        $ada = pelanggan_by_telepon($telp);
                        if ($ada) {
                            throw new RuntimeException('Nomor HP ini sudah terdaftar atas nama ' . $ada['nama'] . '.');
                        }
                    }
                    pelanggan_tambah($nama, $telp, $email, $member === 1, (string) $user['username'], $catatan);
                    $pesan = 'Pelanggan "' . $nama . '" didaftarkan.';
                }
                break;
            }

            case 'pelanggan_hapus': {
                $id = (int) ($_POST['id'] ?? 0);
                $p = pelanggan_row($id);
                if (!$p) {
                    throw new RuntimeException('Pelanggan tidak ditemukan.');
                }
                db()->prepare('UPDATE pesanan SET pelanggan_id = NULL WHERE pelanggan_id = ?')->execute([$id]);
                db()->prepare('DELETE FROM pelanggan WHERE id = ?')->execute([$id]);
                log_admin('pelanggan_hapus', $p['nama']);
                $pesan = 'Pelanggan "' . $p['nama'] . '" dihapus (riwayat pesanan tetap tersimpan).';
                break;
            }

            case 'poin_reset': {
                $id = (int) ($_POST['id'] ?? 0);
                db()->prepare('UPDATE pelanggan SET poin = 0, updated_at = ? WHERE id = ?')->execute([now(), $id]);
                $pesan = 'Poin pelanggan dikosongkan.';
                break;
            }

            case 'poin_tukar': {
                $id = (int) ($_POST['id'] ?? 0);
                $pakai = (float) ($_POST['pakai'] ?? 0);
                $p = pelanggan_row($id);
                if (!$p) {
                    throw new RuntimeException('Pelanggan tidak ditemukan.');
                }
                if ($pakai <= 0 || $pakai > (float) $p['poin']) {
                    throw new RuntimeException('Jumlah poin yang ditukar tidak sesuai.');
                }
                db()->prepare('UPDATE pelanggan SET poin = poin - ?, updated_at = ? WHERE id = ?')->execute([$pakai, now(), $id]);
                log_admin('poin_tukar', $p['nama'] . ' memakai ' . angka_qty($pakai) . ' poin');
                $pesan = 'Poin ' . angka_qty($pakai) . ' berhasil dikurangi dari ' . $p['nama'] . '.';
                break;
            }
        }
    } catch (Throwable $e) {
        $galat = $e->getMessage();
    }
    if ($galat === '' && $pesan !== '') {
        redirect('pelanggan.php?ok=' . urlencode($pesan));
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? pelanggan_row($editId) : null;
$cari = trim((string) ($_GET['cari'] ?? ''));
$daftar = pelanggan_semua($cari);
$lihat = (int) ($_GET['lihat'] ?? 0);
$detail = $lihat > 0 ? pelanggan_row($lihat) : null;

$riwayat = [];
if ($detail) {
    $st = db()->prepare('SELECT p.*, m.nomor AS meja_nomor, m.nama AS meja_nama FROM pesanan p
                         LEFT JOIN meja m ON m.id = p.meja_id
                         WHERE p.pelanggan_id = ? ORDER BY p.created_at DESC LIMIT 10');
    $st->execute([(int) $detail['id']]);
    $riwayat = $st->fetchAll();
}

page_head('Data Pelanggan', 'pelanggan.php');
flash();
if ($galat !== '') {
    echo '<div class="flash flash-err">' . h($galat) . '</div>';
}
?>
<div class="stat-grid" style="margin-bottom:22px">
    <div class="stat"><span>Total pelanggan terdaftar</span><strong><?= count(pelanggan_semua()) ?></strong><small>termasuk member</small></div>
    <div class="stat"><span>Member aktif</span><strong><?= count(pelanggan_semua('', true)) ?></strong><small>mendapat harga &amp; poin member</small></div>
    <div class="stat"><span>Poin terkumpul</span><strong><?php
        $tot = 0.0;
        foreach (pelanggan_semua() as $p) { $tot += (float) $p['poin']; }
        echo h(angka_qty($tot));
    ?></strong><small>seluruh pelanggan</small></div>
    <div class="stat"><span>Diskon member</span><strong><?= h(setting_num('diskon_member_persen', 0) > 0 ? angka_qty(setting_num('diskon_member_persen', 0)) . '%' : '—') ?></strong><small>diatur di Pengaturan → Pajak &amp; Member</small></div>
</div>

<div class="grid-kartu" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr)">
    <section class="kartu">
        <div class="kartu-judul">
            <h3><?= icon('users') ?> Daftar Pelanggan</h3>
            <form method="get" class="baris-tombol">
                <input type="search" name="cari" value="<?= h($cari) ?>" placeholder="Cari nama / nomor HP">
                <button class="tombol tombol-kecil" type="submit"><?= icon('search') ?> Cari</button>
            </form>
        </div>
        <?php if (!$daftar): ?>
            <p class="kosong-teks">
                Belum ada pelanggan terdaftar. Pendaftaran hanya untuk <strong>anggota member</strong> kafe —
                pelanggan biasa bisa langsung memesan lewat QR meja tanpa didaftarkan.
            </p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr><th>Nama</th><th>Kontak</th><th class="angka">Poin</th><th class="angka">Kunjungan</th><th class="angka">Total Belanja</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daftar as $p): ?>
                        <tr>
                            <td>
                                <strong><?= h($p['nama']) ?></strong>
                                <?php if ((int) $p['member'] === 1): ?><span class="pil pil-ok">Member</span><?php else: ?><span class="pil">Umum</span><?php endif; ?>
                                <?php if ((string) $p['terakhir'] !== ''): ?><em style="display:block;font-style:normal;font-size:.76rem;color:var(--muted)">Terakhir datang <?= h(tgl_label(substr((string) $p['terakhir'], 0, 10))) ?></em><?php endif; ?>
                            </td>
                            <td><?= h($p['telepon'] !== '' ? $p['telepon'] : '—') ?><br><em style="font-style:normal;font-size:.76rem;color:var(--muted)"><?= h($p['email']) ?></em></td>
                            <td class="angka"><?= h(angka_qty($p['poin'])) ?></td>
                            <td class="angka"><?= (int) $p['jumlah_kunjungan'] ?></td>
                            <td class="angka"><?= h(rupiah($p['total_belanja'])) ?></td>
                            <td>
                                <div class="baris-tombol">
                                    <a class="tombol tombol-kecil" href="pelanggan.php?edit=<?= (int) $p['id'] ?>"><?= icon('edit') ?></a>
                                    <a class="tombol tombol-kecil" href="pelanggan.php?lihat=<?= (int) $p['id'] ?>">Riwayat</a>
                                    <form method="post">
                                        <input type="hidden" name="aksi" value="pelanggan_hapus">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus pelanggan <?= h($p['nama']) ?>?"><?= icon('trash') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($detail): ?>
            <h3 style="margin-top:24px">Riwayat pesanan — <?= h($detail['nama']) ?></h3>
            <?php if (!$riwayat): ?>
                <p class="kosong-teks">Pelanggan ini belum memiliki riwayat pesanan.</p>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th>Tanggal</th><th>Kode</th><th>Meja</th><th>Status</th><th>Bayar</th><th class="angka">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($riwayat as $r): ?>
                            <tr>
                                <td><?= h(tgl_label((string) $r['tanggal'], false)) ?> <?= h(jam_teks((string) $r['created_at'], false)) ?></td>
                                <td><?= h($r['kode']) ?></td>
                                <td><?= h(pesanan_label_meja($r)) ?></td>
                                <td><span class="pil pil-info"><?= h(LABEL_STATUS_PENDEK[(string) $r['status']] ?? '') ?></span></td>
                                <td><span class="pil <?= (string) $r['status_bayar'] === BAYAR_LUNAS ? 'pil-ok' : 'pil-err' ?>"><?= h((string) $r['status_bayar'] === BAYAR_LUNAS ? 'Lunas' : 'Belum') ?></span></td>
                                <td class="angka"><?= h(rupiah($r['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" class="baris-tombol" style="margin-top:14px">
                    <input type="hidden" name="aksi" value="poin_tukar">
                    <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
                    <input type="number" name="pakai" min="1" max="<?= h((string) $detail['poin']) ?>" placeholder="Poin dipakai" style="width:150px">
                    <button class="tombol tombol-kecil" type="submit"><?= icon('check') ?> Tukar poin</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('plus') ?> <?= $edit ? 'Ubah Pelanggan' : 'Daftarkan Member Baru' ?></h3></div>
        <p class="kartu-sub">Pendaftaran ini untuk <strong>anggota member</strong> kafe. Member mendapat harga khusus (bila diisi), diskon member, dan poin belanja.</p>
        <form method="post">
            <input type="hidden" name="aksi" value="pelanggan_simpan">
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="kolom" style="margin-bottom:12px">
                <label for="nama">Nama lengkap *</label>
                <input type="text" id="nama" name="nama" required value="<?= h($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris form-2">
                <div class="kolom">
                    <label for="telepon">Nomor HP</label>
                    <input type="tel" id="telepon" name="telepon" value="<?= h($edit['telepon'] ?? '') ?>" placeholder="0812....">
                </div>
                <div class="kolom">
                    <label for="email">Email (opsional)</label>
                    <input type="email" id="email" name="email" value="<?= h($edit['email'] ?? '') ?>">
                </div>
            </div>
            <div class="kolom" style="margin-bottom:12px">
                <label for="catatan">Catatan</label>
                <textarea id="catatan" name="catatan" placeholder="Contoh: langganan kopi susu, alergi kacang"><?= h($edit['catatan'] ?? '') ?></textarea>
            </div>
            <label class="cek" style="margin-bottom:14px"><input type="checkbox" name="member" value="1" <?= !$edit || (int) $edit['member'] === 1 ? 'checked' : '' ?>> Daftarkan sebagai anggota member</label>
            <div class="form-aksi">
                <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> Simpan</button>
                <?php if ($edit): ?><a class="tombol" href="pelanggan.php">Batal</a><?php endif; ?>
            </div>
        </form>

        <?php if ($edit): ?>
            <form method="post" style="margin-top:16px">
                <input type="hidden" name="aksi" value="poin_reset">
                <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Kosongkan poin pelanggan ini?">Kosongkan poin</button>
            </form>
        <?php endif; ?>
    </section>
</div>
<?php page_end(); ?>
