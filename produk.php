<?php
declare(strict_types=1);

/* Data produk: kategori, harga, foto produk. */

require_once __DIR__ . '/lib.php';

$user = require_owner();

$aksi = (string) ($_POST['aksi'] ?? '');
$pesan = '';
$galat = '';

if (is_post()) {
    try {
        switch ($aksi) {
            case 'produk_simpan': {
                $id = (int) ($_POST['id'] ?? 0);
                $nama = trim((string) ($_POST['nama'] ?? ''));
                if ($nama === '') {
                    throw new RuntimeException('Nama produk wajib diisi.');
                }
                $foto = trim((string) ($_POST['foto_url'] ?? ''));

                /* Unggah foto produk (lewat proxy media platform — bukan disk lokal). */
                if (!empty($_FILES['foto']['name']) && (int) ($_FILES['foto']['error'] ?? 1) === 0) {
                    $berkas = (string) $_FILES['foto']['tmp_name'];
                    $namaAsli = (string) $_FILES['foto']['name'];
                    $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                        throw new RuntimeException('Foto harus berupa gambar (jpg, png, gif, webp).');
                    }
                    if ((int) $_FILES['foto']['size'] > batas_unggah_byte()) {
                        throw new RuntimeException('Ukuran foto melebihi batas server (' . ukuran_teks(batas_unggah_byte()) . ').');
                    }
                    $unggah = media_upload($berkas, 'produk-' . time() . '.' . $ext);
                    if (empty($unggah['ok'])) {
                        throw new RuntimeException('Foto gagal diunggah: ' . (string) ($unggah['error'] ?? 'penyimpanan media menolak berkas.'));
                    }
                    $foto = (string) $unggah['url'];
                }

                $data = [
                    'kode'         => trim((string) ($_POST['kode'] ?? '')),
                    'nama'         => $nama,
                    'kategori_id'  => (int) ($_POST['kategori_id'] ?? 0) ?: null,
                    'harga'        => (float) ($_POST['harga'] ?? 0),
                    'harga_member' => (float) ($_POST['harga_member'] ?? 0),
                    'satuan'       => trim((string) ($_POST['satuan'] ?? 'porsi')),
                    'deskripsi'    => trim((string) ($_POST['deskripsi'] ?? '')),
                    'foto_url'     => $foto,
                    'favorit'      => !empty($_POST['favorit']) ? 1 : 0,
                    'stok'         => (int) ($_POST['stok'] ?? -1),
                    'aktif'        => !empty($_POST['aktif']) ? 1 : 0,
                    'urutan'       => (int) ($_POST['urutan'] ?? 0),
                ];
                if ($data['harga'] < 0) {
                    throw new RuntimeException('Harga tidak boleh negatif.');
                }
                if ($id > 0) {
                    db()->prepare('UPDATE produk SET kode=?, nama=?, kategori_id=?, harga=?, harga_member=?, satuan=?, deskripsi=?,
                                   foto_url=?, favorit=?, stok=?, aktif=?, urutan=?, updated_at=? WHERE id=?')
                        ->execute([$data['kode'], $data['nama'], $data['kategori_id'], $data['harga'], $data['harga_member'],
                                   $data['satuan'], $data['deskripsi'], $data['foto_url'], $data['favorit'], $data['stok'],
                                   $data['aktif'], $data['urutan'], now(), $id]);
                    log_admin('produk_ubah', 'Produk #' . $id . ' — ' . $data['nama']);
                    $pesan = 'Produk "' . $data['nama'] . '" diperbarui.';
                } else {
                    db()->prepare('INSERT INTO produk (kode, nama, kategori_id, harga, harga_member, satuan, deskripsi, foto_url,
                                   favorit, stok, aktif, urutan, dibuat_oleh, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$data['kode'], $data['nama'], $data['kategori_id'], $data['harga'], $data['harga_member'],
                                   $data['satuan'], $data['deskripsi'], $data['foto_url'], $data['favorit'], $data['stok'],
                                   $data['aktif'], $data['urutan'], (string) $user['username'], now(), now()]);
                    log_admin('produk_tambah', 'Produk baru — ' . $data['nama']);
                    $pesan = 'Produk "' . $data['nama'] . '" ditambahkan.';
                }
                break;
            }

            case 'produk_hapus': {
                $id = (int) ($_POST['id'] ?? 0);
                $p = produk_row($id);
                if (!$p) {
                    throw new RuntimeException('Produk tidak ditemukan.');
                }
                db()->prepare('DELETE FROM produk WHERE id = ?')->execute([$id]);
                log_admin('produk_hapus', 'Produk #' . $id . ' — ' . (string) $p['nama']);
                $pesan = 'Produk "' . $p['nama'] . '" dihapus.';
                break;
            }

            case 'produk_aktif': {
                $id = (int) ($_POST['id'] ?? 0);
                db()->prepare('UPDATE produk SET aktif = 1 - aktif, updated_at = ? WHERE id = ?')->execute([now(), $id]);
                $pesan = 'Status produk diperbarui.';
                break;
            }

            case 'produk_favorit': {
                $id = (int) ($_POST['id'] ?? 0);
                db()->prepare('UPDATE produk SET favorit = 1 - favorit, updated_at = ? WHERE id = ?')->execute([now(), $id]);
                $pesan = 'Penanda favorit diperbarui.';
                break;
            }

            case 'kategori_simpan': {
                $id = (int) ($_POST['id'] ?? 0);
                $nama = trim((string) ($_POST['nama'] ?? ''));
                if ($nama === '') {
                    throw new RuntimeException('Nama kategori wajib diisi.');
                }
                $urutan = (int) ($_POST['urutan'] ?? 0);
                if ($id > 0) {
                    db()->prepare('UPDATE kategori SET nama = ?, urutan = ? WHERE id = ?')->execute([$nama, $urutan, $id]);
                    $pesan = 'Kategori diperbarui.';
                } else {
                    db()->prepare('INSERT INTO kategori (nama, urutan, aktif) VALUES (?, ?, 1)')->execute([$nama, $urutan]);
                    $pesan = 'Kategori "' . $nama . '" ditambahkan.';
                }
                break;
            }

            case 'kategori_hapus': {
                $id = (int) ($_POST['id'] ?? 0);
                $jml = db()->prepare('SELECT COUNT(*) FROM produk WHERE kategori_id = ?');
                $jml->execute([$id]);
                if ((int) $jml->fetchColumn() > 0) {
                    throw new RuntimeException('Kategori masih dipakai produk. Pindahkan produknya terlebih dahulu.');
                }
                db()->prepare('DELETE FROM kategori WHERE id = ?')->execute([$id]);
                $pesan = 'Kategori dihapus.';
                break;
            }

            case 'produk_hapus_contoh': {
                $jml = db()->prepare('DELETE FROM produk WHERE dibuat_oleh = ?');
                $jml->execute(['seed']);
                $n = $jml->rowCount();
                set_setting('demo_produk', '0');
                log_admin('produk_hapus_contoh', $n . ' produk contoh dihapus');
                $pesan = $n . ' produk contoh dihapus. Silakan tambahkan menu kafe Anda sendiri.';
                break;
            }
        }
    } catch (Throwable $e) {
        $galat = $e->getMessage();
    }
    if ($galat === '' && $pesan !== '') {
        redirect('produk.php?ok=' . urlencode($pesan) . '&tab=' . urlencode((string) ($_POST['tab_kembali'] ?? 'produk')));
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? produk_row($editId) : null;
$kategori = kategori_semua();
$cari = trim((string) ($_GET['cari'] ?? ''));
$filterKat = (int) ($_GET['kategori'] ?? 0);
$produk = produk_semua(false, $cari);
if ($filterKat > 0) {
    $produk = array_values(array_filter($produk, static fn($p) => (int) ($p['kategori_id'] ?? 0) === $filterKat));
}
$adaContoh = (int) db()->query("SELECT COUNT(*) FROM produk WHERE dibuat_oleh = 'seed'")->fetchColumn();
$tab = (string) ($_GET['tab'] ?? 'produk');

page_head('Data Produk', 'produk.php');
flash();
if ($galat !== '') {
    echo '<div class="flash flash-err">' . h($galat) . '</div>';
}
?>
<div class="tab-bar">
    <a href="#" data-tab="produk" data-tab-awal="produk" class="<?= $tab === 'produk' ? 'is-aktif' : '' ?>">Daftar Produk</a>
    <a href="#" data-tab="kategori" class="<?= $tab === 'kategori' ? 'is-aktif' : '' ?>">Kategori</a>
</div>

<div class="tab-panel<?= $tab === 'produk' ? ' is-aktif' : '' ?>" data-panel="produk">
    <div class="grid-kartu" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr)">
        <section class="kartu">
            <div class="kartu-judul">
                <h3><?= icon('box') ?> Daftar Produk (<?= count($produk) ?>)</h3>
                <form method="get" class="baris-tombol">
                    <input type="hidden" name="tab" value="produk">
                    <input type="search" name="cari" value="<?= h($cari) ?>" placeholder="Cari nama/kode produk..." style="width:200px">
                    <select name="kategori" data-kirim>
                        <option value="0">Semua kategori</option>
                        <?php foreach ($kategori as $k): ?>
                            <option value="<?= (int) $k['id'] ?>" <?= $filterKat === (int) $k['id'] ? 'selected' : '' ?>><?= h($k['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="tombol tombol-kecil" type="submit"><?= icon('search') ?> Cari</button>
                </form>
            </div>
            <?php if ($adaContoh > 0): ?>
                <div class="flash" style="margin-bottom:14px">
                    Masih ada <strong><?= (int) $adaContoh ?> produk contoh</strong> bawaan aplikasi.
                    <form method="post" style="display:inline">
                        <input type="hidden" name="aksi" value="produk_hapus_contoh">
                        <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus semua produk contoh? Produk yang Anda buat sendiri tidak terhapus.">Hapus produk contoh</button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if (!$produk): ?>
                <p class="kosong-teks">Belum ada produk. Tambahkan menu kafe Anda pada formulir di sebelah kanan.</p>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th>Produk</th><th>Kategori</th><th class="angka">Harga</th><th class="angka">Harga Member</th>
                                <th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($produk as $p): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;gap:10px;align-items:center">
                                        <?php if ((string) $p['foto_url'] !== ''): ?>
                                            <img class="gambar-produk" src="<?= h($p['foto_url']) ?>" alt="">
                                        <?php else: ?>
                                            <span class="gambar-produk" style="display:grid;place-items:center;font-weight:800;color:rgba(122,74,42,.55)"><?= h(strtoupper(substr((string) $p['nama'], 0, 1))) ?></span>
                                        <?php endif; ?>
                                        <span>
                                            <strong><?= h($p['nama']) ?></strong>
                                            <?php if ((int) $p['favorit'] === 1): ?><span class="pil pil-warn">Favorit</span><?php endif; ?>
                                            <em style="display:block;font-style:normal;font-size:.78rem;color:var(--muted)"><?= h($p['kode'] !== '' ? $p['kode'] : '—') ?> · <?= h($p['satuan']) ?></em>
                                        </span>
                                    </div>
                                </td>
                                <td><?= h($p['kategori'] ?? '—') ?></td>
                                <td class="angka"><?= h(rupiah($p['harga'])) ?></td>
                                <td class="angka"><?= (float) $p['harga_member'] > 0 ? h(rupiah($p['harga_member'])) : '—' ?></td>
                                <td>
                                    <span class="pil <?= (int) $p['aktif'] === 1 ? 'pil-ok' : 'pil-err' ?>"><?= (int) $p['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
                                </td>
                                <td>
                                    <div class="baris-tombol">
                                        <a class="tombol tombol-kecil" href="produk.php?edit=<?= (int) $p['id'] ?>#atas"><?= icon('edit') ?> Ubah</a>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="aksi" value="produk_favorit">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button class="tombol tombol-kecil" type="submit" title="Tandai favorit"><?= (int) $p['favorit'] === 1 ? '★' : '☆' ?></button>
                                        </form>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="aksi" value="produk_aktif">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button class="tombol tombol-kecil" type="submit"><?= (int) $p['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                        </form>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="aksi" value="produk_hapus">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="tab_kembali" value="produk">
                                            <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus produk <?= h($p['nama']) ?>?"><?= icon('trash') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="kartu" id="atas">
            <div class="kartu-judul"><h3><?= icon('plus') ?> <?= $edit ? 'Ubah Produk' : 'Tambah Produk' ?></h3></div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="produk_simpan">
                <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
                <input type="hidden" name="tab_kembali" value="produk">
                <div class="kolom" style="margin-bottom:12px">
                    <label for="nama">Nama produk *</label>
                    <input type="text" id="nama" name="nama" required value="<?= h($edit['nama'] ?? '') ?>">
                </div>
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="kode">Kode (opsional)</label>
                        <input type="text" id="kode" name="kode" value="<?= h($edit['kode'] ?? '') ?>">
                    </div>
                    <div class="kolom">
                        <label for="kategori_id">Kategori</label>
                        <select id="kategori_id" name="kategori_id">
                            <option value="0">— tanpa kategori —</option>
                            <?php foreach ($kategori as $k): ?>
                                <option value="<?= (int) $k['id'] ?>" <?= (int) ($edit['kategori_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>><?= h($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="harga">Harga jual (Rp) *</label>
                        <input type="number" id="harga" name="harga" min="0" step="500" required value="<?= h($edit ? (string) (float) $edit['harga'] : '') ?>">
                    </div>
                    <div class="kolom">
                        <label for="harga_member">Harga member (opsional)</label>
                        <input type="number" id="harga_member" name="harga_member" min="0" step="500" value="<?= h($edit ? (string) (float) $edit['harga_member'] : '') ?>">
                        <span class="bantuan">Kosongkan bila member membayar harga normal.</span>
                    </div>
                </div>
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="satuan">Satuan</label>
                        <input type="text" id="satuan" name="satuan" value="<?= h($edit['satuan'] ?? 'porsi') ?>">
                    </div>
                    <div class="kolom">
                        <label for="urutan">Urutan tampil</label>
                        <input type="number" id="urutan" name="urutan" value="<?= h($edit ? (string) (int) $edit['urutan'] : '0') ?>">
                    </div>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="deskripsi">Keterangan singkat</label>
                    <textarea id="deskripsi" name="deskripsi" placeholder="Contoh: kopi susu gula aren, disajikan dingin"><?= h($edit['deskripsi'] ?? '') ?></textarea>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="foto">Foto produk</label>
                    <?php if ($edit && (string) $edit['foto_url'] !== ''): ?>
                        <img class="gambar-produk-besar" src="<?= h($edit['foto_url']) ?>" alt="" style="margin-bottom:8px">
                    <?php endif; ?>
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <span class="bantuan">JPG/PNG/WEBP, maksimal <?= h(ukuran_teks(batas_unggah_byte())) ?>.</span>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="foto_url">atau tempel alamat foto (URL)</label>
                    <input type="text" id="foto_url" name="foto_url" value="<?= h($edit['foto_url'] ?? '') ?>">
                </div>
                <div class="baris-tombol" style="margin-bottom:14px">
                    <label class="cek"><input type="checkbox" name="aktif" value="1" <?= !$edit || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Aktif dijual</label>
                    <label class="cek"><input type="checkbox" name="favorit" value="1" <?= $edit && (int) $edit['favorit'] === 1 ? 'checked' : '' ?>> Tandai favorit</label>
                    <label class="cek"><input type="checkbox" name="stok_ada" value="1" disabled checked> <em style="font-size:.78rem">Stok bebas</em></label>
                </div>
                <div class="form-aksi">
                    <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> Simpan Produk</button>
                    <?php if ($edit): ?>
                        <a class="tombol" href="produk.php">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    </div>
</div>

<div class="tab-panel<?= $tab === 'kategori' ? ' is-aktif' : '' ?>" data-panel="kategori">
    <div class="grid-kartu grid-2">
        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('grid') ?> Kategori Menu</h3></div>
            <?php if (!$kategori): ?>
                <p class="kosong-teks">Belum ada kategori.</p>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th>Nama kategori</th><th class="angka">Urutan</th><th class="angka">Produk</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($kategori as $k): ?>
                            <?php
                            $st = db()->prepare('SELECT COUNT(*) FROM produk WHERE kategori_id = ?');
                            $st->execute([(int) $k['id']]);
                            $jml = (int) $st->fetchColumn();
                            ?>
                            <tr>
                                <td>
                                    <form method="post" id="kat-<?= (int) $k['id'] ?>" hidden>
                                        <input type="hidden" name="aksi" value="kategori_simpan">
                                        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                    </form>
                                    <input type="text" name="nama" form="kat-<?= (int) $k['id'] ?>" value="<?= h($k['nama']) ?>" style="width:170px" required>
                                </td>
                                <td class="angka"><input type="number" name="urutan" form="kat-<?= (int) $k['id'] ?>" value="<?= (int) $k['urutan'] ?>" style="width:78px"></td>
                                <td class="angka"><?= $jml ?></td>
                                <td>
                                    <div class="baris-tombol">
                                        <button class="tombol tombol-kecil" type="submit" form="kat-<?= (int) $k['id'] ?>"><?= icon('check') ?> Simpan</button>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="aksi" value="kategori_hapus">
                                            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                            <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus kategori <?= h($k['nama']) ?>?"><?= icon('trash') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('plus') ?> Tambah Kategori</h3></div>
            <form method="post">
                <input type="hidden" name="aksi" value="kategori_simpan">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="tab_kembali" value="kategori">
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="kat_nama">Nama kategori</label>
                        <input type="text" id="kat_nama" name="nama" required placeholder="Contoh: Kopi">
                    </div>
                    <div class="kolom">
                        <label for="kat_urutan">Urutan</label>
                        <input type="number" id="kat_urutan" name="urutan" value="0">
                    </div>
                </div>
                <div class="form-aksi">
                    <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> Simpan Kategori</button>
                </div>
            </form>
            <p class="bantuan" style="margin-top:14px;color:var(--muted);font-size:.82rem">
                Nama kategori di kiri bisa langsung diubah (ubah kolomnya lalu tekan Simpan).
            </p>
        </section>
    </div>
</div>
<?php page_end(); ?>
