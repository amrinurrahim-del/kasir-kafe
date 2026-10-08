<?php
declare(strict_types=1);

/* Pengaturan: identitas & tampilan, pembayaran, member, meja & QR, struk, akun, data. */

require_once __DIR__ . '/lib.php';

$user = require_owner();

$tab = (string) ($_POST['tab'] ?? $_GET['tab'] ?? 'identitas');
$pesan = '';
$galat = '';

/** Unggah gambar lewat proxy media platform; mengembalikan URL atau melempar galat. */
function unggah_gambar(string $field, string $awalan): string
{
    if (empty($_FILES[$field]['name']) || (int) ($_FILES[$field]['error'] ?? 1) !== 0) {
        return '';
    }
    $namaAsli = (string) $_FILES[$field]['name'];
    $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
        throw new RuntimeException('Berkas harus berupa gambar (jpg, png, gif, webp).');
    }
    if ((int) $_FILES[$field]['size'] > batas_unggah_byte()) {
        throw new RuntimeException('Ukuran berkas melebihi batas server (' . ukuran_teks(batas_unggah_byte()) . ').');
    }
    $hasil = media_upload((string) $_FILES[$field]['tmp_name'], $awalan . '-' . time() . '.' . $ext);
    if (empty($hasil['ok'])) {
        throw new RuntimeException('Unggah gagal: ' . (string) ($hasil['error'] ?? 'penyimpanan menolak berkas.'));
    }
    return (string) $hasil['url'];
}

if (is_post()) {
    $aksi = (string) ($_POST['aksi'] ?? '');
    try {
        switch ($aksi) {
            case 'simpan_identitas': {
                $logo = unggah_gambar('logo', 'logo-kafe');
                $set = [
                    'nama_kafe'          => trim((string) ($_POST['nama_kafe'] ?? '')) ?: 'Kafe',
                    'tagline_kafe'       => trim((string) ($_POST['tagline_kafe'] ?? '')),
                    'alamat_kafe'        => trim((string) ($_POST['alamat_kafe'] ?? '')),
                    'telepon_kafe'       => trim((string) ($_POST['telepon_kafe'] ?? '')),
                    'zona_waktu'         => (string) ($_POST['zona_waktu'] ?? 'Asia/Makassar'),
                    'display_judul'      => trim((string) ($_POST['display_judul'] ?? 'MENU KAFE')),
                    'display_footer'     => trim((string) ($_POST['display_footer'] ?? '')),
                    'display_kolom'      => (string) max(2, min(4, (int) ($_POST['display_kolom'] ?? 3))),
                    'display_kecepatan'  => (string) max(10, min(120, (int) ($_POST['display_kecepatan'] ?? 38))),
                    'display_panel_siap' => !empty($_POST['display_panel_siap']) ? '1' : '0',
                    'display_tampil_harga' => !empty($_POST['display_tampil_harga']) ? '1' : '0',
                ];
                if (!in_array($set['zona_waktu'], timezone_identifiers_list(), true)) {
                    $set['zona_waktu'] = 'Asia/Makassar';
                }
                if ($logo !== '') {
                    $set['logo_url'] = $logo;
                }
                set_setting_banyak($set);
                log_admin('pengaturan_identitas', 'Identitas & tampilan diperbarui');
                $pesan = 'Identitas & tampilan kafe disimpan.';
                break;
            }

            case 'simpan_pembayaran': {
                $qris = unggah_gambar('qris', 'qris-kafe');
                $set = [
                    'pajak_aktif'       => !empty($_POST['pajak_aktif']) ? '1' : '0',
                    'pajak_persen'      => (string) max(0, min(100, (float) ($_POST['pajak_persen'] ?? 0))),
                    'service_aktif'     => !empty($_POST['service_aktif']) ? '1' : '0',
                    'service_persen'    => (string) max(0, min(100, (float) ($_POST['service_persen'] ?? 0))),
                    'pembulatan'        => (string) max(0, (int) ($_POST['pembulatan'] ?? 0)),
                    'tunai_aktif'       => !empty($_POST['tunai_aktif']) ? '1' : '0',
                    'qris_aktif'        => !empty($_POST['qris_aktif']) ? '1' : '0',
                    'qris_catatan'      => trim((string) ($_POST['qris_catatan'] ?? '')),
                    'kode_pesanan_prefix' => substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['kode_pesanan_prefix'] ?? 'K')) ?: 'K', 0, 3),
                    'alamat_publik'     => rtrim(trim((string) ($_POST['alamat_publik'] ?? '')), '/'),
                    'mata_uang'         => trim((string) ($_POST['mata_uang'] ?? 'Rp')) ?: 'Rp',
                ];
                if ($qris !== '') {
                    $set['qris_gambar_url'] = $qris;
                }
                set_setting_banyak($set);
                log_admin('pengaturan_pembayaran', 'Setelan pembayaran diperbarui');
                $pesan = 'Setelan pembayaran disimpan.';
                break;
            }

            case 'simpan_member': {
                set_setting_banyak([
                    'poin_aktif'           => !empty($_POST['poin_aktif']) ? '1' : '0',
                    'poin_per_rupiah'      => (string) max(1, (float) ($_POST['poin_per_rupiah'] ?? 10000)),
                    'poin_nilai'           => (string) max(0, (float) ($_POST['poin_nilai'] ?? 1)),
                    'diskon_member_persen' => (string) max(0, min(100, (float) ($_POST['diskon_member_persen'] ?? 0))),
                ]);
                $pesan = 'Setelan member & poin disimpan.';
                break;
            }

            case 'simpan_struk': {
                set_setting_banyak([
                    'struk_judul'        => trim((string) ($_POST['struk_judul'] ?? 'STRUK PEMBAYARAN')),
                    'struk_catatan'      => trim((string) ($_POST['struk_catatan'] ?? '')),
                    'struk_tampil_logo'  => !empty($_POST['struk_tampil_logo']) ? '1' : '0',
                    'printer_kertas'     => (string) ($_POST['printer_kertas'] ?? 'thermal80'),
                    'printer_otomatis'   => !empty($_POST['printer_otomatis']) ? '1' : '0',
                ]);
                $pesan = 'Setelan struk & printer disimpan.';
                break;
            }

            case 'logo_hapus': {
                set_setting('logo_url', '');
                log_admin('logo_hapus', 'Logo kafe dihapus');
                $pesan = 'Logo kafe dihapus.';
                break;
            }

            case 'qris_hapus': {
                set_setting('qris_gambar_url', '');
                $pesan = 'Gambar QRIS dihapus.';
                break;
            }

            case 'meja_simpan': {
                $id = (int) ($_POST['id'] ?? 0);
                $nomor = trim((string) ($_POST['nomor'] ?? ''));
                $nama = trim((string) ($_POST['nama'] ?? ''));
                if ($nomor === '') {
                    throw new RuntimeException('Nomor meja wajib diisi.');
                }
                $cek = db()->prepare('SELECT COUNT(*) FROM meja WHERE lower(nomor) = lower(?) AND id <> ?');
                $cek->execute([$nomor, $id]);
                if ((int) $cek->fetchColumn() > 0) {
                    throw new RuntimeException('Nomor meja ' . $nomor . ' sudah ada.');
                }
                $urutan = (int) ($_POST['urutan'] ?? 0);
                $aktif = !empty($_POST['aktif']) ? 1 : 0;
                if ($id > 0) {
                    db()->prepare('UPDATE meja SET nomor=?, nama=?, urutan=?, aktif=? WHERE id=?')->execute([$nomor, $nama, $urutan, $aktif, $id]);
                    $pesan = 'Meja diperbarui.';
                } else {
                    db()->prepare('INSERT INTO meja (nomor, nama, token, aktif, urutan) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$nomor, $nama !== '' ? $nama : ('Meja ' . $nomor), bin2hex(random_bytes(10)), $aktif, $urutan]);
                    $pesan = 'Meja ' . $nomor . ' ditambahkan beserta QR-nya.';
                }
                break;
            }

            case 'meja_hapus': {
                $id = (int) ($_POST['id'] ?? 0);
                $ada = db()->prepare('SELECT COUNT(*) FROM pesanan WHERE meja_id = ?');
                $ada->execute([$id]);
                if ((int) $ada->fetchColumn() > 0) {
                    throw new RuntimeException('Meja ini sudah dipakai pada pesanan — nonaktifkan saja agar riwayat tetap utuh.');
                }
                db()->prepare('DELETE FROM meja WHERE id = ?')->execute([$id]);
                log_admin('meja_hapus', 'Meja #' . $id);
                $pesan = 'Meja dihapus.';
                break;
            }

            case 'meja_token': {
                $id = (int) ($_POST['id'] ?? 0);
                db()->prepare('UPDATE meja SET token = ? WHERE id = ?')->execute([bin2hex(random_bytes(10)), $id]);
                log_admin('meja_token_baru', 'QR meja #' . $id . ' dibuat ulang');
                $pesan = 'QR meja dibuat ulang — cetak ulang kartu QR meja tersebut.';
                break;
            }

            case 'meja_tambah_banyak': {
                $jumlah = max(1, min(50, (int) ($_POST['jumlah'] ?? 0)));
                $awal = max(1, (int) ($_POST['mulai'] ?? 1));
                $ins = db()->prepare('INSERT INTO meja (nomor, nama, token, aktif, urutan) VALUES (?, ?, ?, 1, ?)');
                $n = 0;
                for ($i = 0; $i < $jumlah; $i++) {
                    $nomor = (string) ($awal + $i);
                    $cek = db()->prepare('SELECT COUNT(*) FROM meja WHERE nomor = ?');
                    $cek->execute([$nomor]);
                    if ((int) $cek->fetchColumn() === 0) {
                        $ins->execute([$nomor, 'Meja ' . $nomor, bin2hex(random_bytes(10)), $awal + $i]);
                        $n++;
                    }
                }
                $pesan = $n . ' meja ditambahkan.';
                break;
            }

            case 'user_simpan': {
                $username = (string) ($_POST['username'] ?? '');
                $nama = (string) ($_POST['nama'] ?? '');
                $role = (string) ($_POST['role'] ?? 'kasir');
                $idAktif = (int) ($_POST['id'] ?? 0);
                if (!array_key_exists($role, daftar_peran())) {
                    $role = 'kasir';
                }
                $hasil = auth_ubah_user($idAktif, $username, $nama, $role, !empty($_POST['aktif']) ? '1' : '0', (string) ($_POST['password'] ?? ''), (string) ($_POST['ulang'] ?? ''), (string) ($_POST['pengawas'] ?? ''));
                if (empty($hasil['ok'])) {
                    throw new RuntimeException((string) $hasil['error']);
                }
                log_admin('user_ubah', 'Akun ' . $username);
                $pesan = 'Akun "' . $username . '" disimpan.';
                break;
            }

            case 'user_tambah': {
                $hasil = auth_tambah_user((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['ulang'] ?? ''), (string) ($_POST['role'] ?? 'kasir'), (string) ($_POST['nama'] ?? ''));
                if (empty($hasil['ok'])) {
                    throw new RuntimeException((string) $hasil['error']);
                }
                log_admin('user_tambah', 'Akun ' . (string) $hasil['username'] . ' (' . (string) $_POST['role'] . ')');
                $pesan = 'Akun kasir/petugas baru "' . (string) $hasil['username'] . '" dibuat.';
                break;
            }

            case 'user_hapus': {
                $hasil = auth_hapus_user((int) ($_POST['id'] ?? 0));
                if (empty($hasil['ok'])) {
                    throw new RuntimeException((string) $hasil['error']);
                }
                log_admin('user_hapus', 'Akun #' . (int) $_POST['id']);
                $pesan = 'Akun dihapus.';
                break;
            }

            case 'pesanan_hapus': {
                $id = (int) ($_POST['id'] ?? 0);
                $p = pesanan_row($id);
                if (!$p) {
                    throw new RuntimeException('Pesanan tidak ditemukan.');
                }
                $pdo = db();
                $pdo->exec('BEGIN IMMEDIATE');
                try {
                    $pdo->prepare('DELETE FROM pesanan_item WHERE pesanan_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM pesanan_log WHERE pesanan_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM pesanan WHERE id = ?')->execute([$id]);
                    $pdo->exec('COMMIT');
                } catch (Throwable $e) {
                    $pdo->exec('ROLLBACK');
                    throw new RuntimeException('Gagal menghapus pesanan.');
                }
                /* Nomor pesanan hari ini sengaja tidak dimundurkan supaya tidak ada nomor kembar. */
                log_admin('pesanan_hapus', 'Pesanan ' . (string) $p['kode'] . ' (' . rupiah($p['total']) . ') dihapus');
                $pesan = 'Pesanan ' . (string) $p['kode'] . ' dihapus.';
                break;
            }

            case 'data_hapus': {
                $dari = tanggal_valid((string) ($_POST['dari'] ?? ''));
                $sampai = tanggal_valid((string) ($_POST['sampai'] ?? ''));
                if ($dari === '' || $sampai === '') {
                    throw new RuntimeException('Rentang tanggal belum benar.');
                }
                if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
                    throw new RuntimeException('Ketik HAPUS pada kolom konfirmasi untuk melanjutkan.');
                }
                $st = db()->prepare('SELECT COUNT(*) FROM pesanan WHERE tanggal >= ? AND tanggal <= ?');
                $st->execute([$dari, $sampai]);
                $jml = (int) $st->fetchColumn();

                $pdo = db();
                $pdo->exec('BEGIN IMMEDIATE');
                try {
                    $pdo->prepare('DELETE FROM pesanan_item WHERE pesanan_id IN (SELECT id FROM pesanan WHERE tanggal >= ? AND tanggal <= ?)')->execute([$dari, $sampai]);
                    $pdo->prepare('DELETE FROM pesanan_log WHERE pesanan_id IN (SELECT id FROM pesanan WHERE tanggal >= ? AND tanggal <= ?)')->execute([$dari, $sampai]);
                    $pdo->prepare('DELETE FROM pesanan WHERE tanggal >= ? AND tanggal <= ?')->execute([$dari, $sampai]);
                    /* Nomor pesanan hari ini tidak boleh mundur: counter tetap. */
                    $pdo->exec('COMMIT');
                } catch (Throwable $e) {
                    $pdo->exec('ROLLBACK');
                    throw new RuntimeException('Gagal menghapus data: ' . $e->getMessage());
                }
                log_admin('data_hapus', $jml . ' pesanan ' . $dari . ' s/d ' . $sampai . ' dihapus');
                $pesan = $jml . ' data pesanan pada rentang tersebut dihapus.';
                break;
            }
        }
    } catch (Throwable $e) {
        $galat = $e->getMessage();
    }
    if ($galat === '' && $pesan !== '') {
        redirect('pengaturan.php?tab=' . urlencode($tab) . '&ok=' . urlencode($pesan));
    }
}

$meja = meja_semua();
$akun = daftar_user();
$editUser = (int) ($_GET['user'] ?? 0);

page_head('Pengaturan', 'pengaturan.php');
flash();
if ($galat !== '') {
    echo '<div class="flash flash-err">' . h($galat) . '</div>';
}
$peringatan = kredensial_bawaan();
if ($peringatan) {
    echo '<div class="flash">';
    foreach ($peringatan as $p) {
        echo '<div>' . h($p) . '</div>';
    }
    echo '</div>';
}
$dasar = url_publik_dasar();
?>
<div class="tab-bar">
    <a href="#" data-tab="identitas" data-tab-awal="identitas" class="<?= $tab === 'identitas' ? 'is-aktif' : '' ?>">Identitas &amp; Display</a>
    <a href="#" data-tab="pembayaran" class="<?= $tab === 'pembayaran' ? 'is-aktif' : '' ?>">Pembayaran &amp; QRIS</a>
    <a href="#" data-tab="member" class="<?= $tab === 'member' ? 'is-aktif' : '' ?>">Member &amp; Poin</a>
    <a href="#" data-tab="meja" class="<?= $tab === 'meja' ? 'is-aktif' : '' ?>">Meja &amp; QR</a>
    <a href="#" data-tab="struk" class="<?= $tab === 'struk' ? 'is-aktif' : '' ?>">Struk &amp; Printer</a>
    <a href="#" data-tab="akun" class="<?= $tab === 'akun' ? 'is-aktif' : '' ?>">Akun &amp; Keamanan</a>
    <a href="#" data-tab="data" class="<?= $tab === 'data' ? 'is-aktif' : '' ?>">Data &amp; Riwayat</a>
</div>

<!-- ============ Identitas & Display ============ -->
<div class="tab-panel<?= $tab === 'identitas' ? ' is-aktif' : '' ?>" data-panel="identitas">
    <div class="grid-kartu grid-2">
        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('coffee') ?> Identitas Kafe</h3></div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="simpan_identitas">
                <input type="hidden" name="tab" value="identitas">
                <div class="kolom" style="margin-bottom:12px">
                    <label for="nama_kafe">Nama kafe *</label>
                    <input type="text" id="nama_kafe" name="nama_kafe" required value="<?= h(setting('nama_kafe')) ?>">
                </div>
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="tagline_kafe">Tagline</label>
                        <input type="text" id="tagline_kafe" name="tagline_kafe" value="<?= h(setting('tagline_kafe')) ?>">
                    </div>
                    <div class="kolom">
                        <label for="telepon_kafe">Telepon</label>
                        <input type="text" id="telepon_kafe" name="telepon_kafe" value="<?= h(setting('telepon_kafe')) ?>">
                    </div>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="alamat_kafe">Alamat</label>
                    <textarea id="alamat_kafe" name="alamat_kafe"><?= h(setting('alamat_kafe')) ?></textarea>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="logo">Logo kafe</label>
                    <?php if (logo_url() !== ''): ?>
                        <img src="<?= h(logo_url()) ?>" alt="Logo" style="max-width:170px;max-height:110px;border-radius:12px;border:1px solid var(--line);margin-bottom:8px">
                    <?php endif; ?>
                    <input type="file" id="logo" name="logo" accept="image/*">
                    <span class="bantuan">Logo dipakai di header aplikasi, halaman pelanggan, display TV, dan struk. Maksimal <?= h(ukuran_teks(batas_unggah_byte())) ?>.</span>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="zona_waktu">Zona waktu</label>
                    <select id="zona_waktu" name="zona_waktu">
                        <?php foreach (['Asia/Makassar', 'Asia/Jakarta', 'Asia/Jayapura'] as $tz): ?>
                            <option value="<?= h($tz) ?>" <?= setting('zona_waktu') === $tz ? 'selected' : '' ?>><?= h($tz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-baris form-3">
                    <div class="kolom">
                        <label for="display_judul">Judul display TV</label>
                        <input type="text" id="display_judul" name="display_judul" value="<?= h(setting('display_judul')) ?>">
                    </div>
                    <div class="kolom">
                        <label for="display_kolom">Jumlah kolom menu</label>
                        <select id="display_kolom" name="display_kolom">
                            <?php foreach ([2, 3, 4] as $k): ?>
                                <option value="<?= $k ?>" <?= (int) setting_num('display_kolom', 3) === $k ? 'selected' : '' ?>><?= $k ?> kolom</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kolom">
                        <label for="display_kecepatan">Kecepatan gulir</label>
                        <input type="number" id="display_kecepatan" name="display_kecepatan" min="10" max="120" value="<?= h((string) (int) setting_num('display_kecepatan', 38)) ?>">
                    </div>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="display_footer">Teks berjalan bawah display</label>
                    <input type="text" id="display_footer" name="display_footer" value="<?= h(setting('display_footer')) ?>">
                </div>
                <div class="baris-tombol" style="margin-bottom:14px">
                    <label class="cek"><input type="checkbox" name="display_panel_siap" value="1" <?= setting_bool('display_panel_siap', true) ? 'checked' : '' ?>> Tampilkan panel "Pesanan Siap Disajikan"</label>
                    <label class="cek"><input type="checkbox" name="display_tampil_harga" value="1" <?= setting_bool('display_tampil_harga', true) ? 'checked' : '' ?>> Tampilkan harga di display</label>
                </div>
                <div class="form-aksi">
                    <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> Simpan</button>
                    <a class="tombol" href="display.php" target="_blank" rel="noopener"><?= icon('tv') ?> Buka Display</a>
                </div>
            </form>
        </section>

        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('tv') ?> Pratinjau Display</h3></div>
            <div id="pratinjau-display" style="border-radius:16px;overflow:hidden;border:1px solid var(--line)">
                <div style="background:linear-gradient(150deg,#38231a,#140c08);color:#f7ecdc;padding:14px 16px;display:flex;align-items:center;gap:12px">
                    <?php if (logo_url() !== ''): ?>
                        <img src="<?= h(logo_url()) ?>" alt="" style="height:38px;border-radius:9px;background:#fff;padding:3px">
                    <?php endif; ?>
                    <div>
                        <strong id="prev-nama" style="font-size:1.05rem"><?= h(nama_kafe()) ?></strong>
                        <div id="prev-judul" style="font-size:.78rem;opacity:.75"><?= h(setting('display_judul')) ?></div>
                    </div>
                </div>
                <div style="background:#f7f1e6;padding:14px">
                    <div id="prev-grid" style="display:grid;grid-template-columns:repeat(<?= (int) max(2, min(4, setting_num('display_kolom', 3))) ?>,minmax(0,1fr));gap:10px"></div>
                </div>
                <div style="background:linear-gradient(90deg,#7a4a2a,#b4763b);color:#fff8ec;padding:8px 14px;font-size:.8rem" id="prev-footer"><?= h(setting('display_footer')) ?></div>
            </div>
            <p class="bantuan" style="color:var(--muted);font-size:.82rem;margin-top:10px">
                Pratinjau di atas hanya contoh susunan. Tampilan sebenarnya dibuka di layar TV.
            </p>
            <form method="post" style="margin-top:10px">
                <input type="hidden" name="aksi" value="logo_hapus">
                <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus logo kafe?">Hapus logo</button>
            </form>
        </section>
    </div>
    <script>
    (function () {
        /* Pratinjau mini ini mengikuti susunan kartu display yang sebenarnya:
           panel gambar transparan, nama besar, garis pemisah antar label, dan kapsul harga coklat. */
        var data = <?= json_encode(payload_menu(), JSON_UNESCAPED_UNICODE) ?>;
        var grid = document.getElementById('prev-grid');
        (data.produk || []).slice(0, 9).forEach(function (p) {
            var d = document.createElement('div');
            d.style.cssText = 'background:#fff;border-radius:11px;padding:9px;font-size:.72rem;display:flex;gap:8px;align-items:center';
            var foto = document.createElement('div');
            foto.style.cssText = 'width:42px;height:42px;border-radius:9px;background:transparent;flex:none;overflow:hidden;display:grid;place-items:center;font-weight:800;color:rgba(122,74,42,.45);font-size:1.1rem';
            if (p.foto) { foto.innerHTML = '<img src="' + p.foto + '" style="width:100%;height:100%;object-fit:cover;display:block">'; }
            else { foto.style.border = '1px dashed rgba(122,74,42,.3)'; foto.textContent = (p.nama || '?').substring(0, 1).toUpperCase(); }
            var t = document.createElement('div');
            t.style.cssText = 'flex:1;min-width:0';
            var nama = document.createElement('strong');
            nama.style.cssText = 'display:block;font-size:.82rem;font-weight:800;line-height:1.2';
            nama.textContent = p.nama;
            var kat = document.createElement('span');
            kat.style.cssText = 'display:block;font-size:.62rem;color:#8a745f;text-transform:uppercase;letter-spacing:.06em;border-top:1px solid rgba(122,74,42,.18);margin-top:3px;padding-top:3px';
            kat.textContent = p.kategori || '';
            var harga = document.createElement('span');
            harga.style.cssText = 'display:block;width:78px;margin:4px 0 0 auto;padding:4px 0;border-radius:999px;text-align:center;font-weight:800;font-size:.66rem;color:#f6ecdc;background:linear-gradient(120deg,#7a4a2a,#57331e)';
            harga.textContent = p.harga_teks;
            t.appendChild(nama);
            t.appendChild(kat);
            t.appendChild(harga);
            d.appendChild(foto);
            d.appendChild(t);
            grid.appendChild(d);
        });
    })();
    </script>
</div>

<!-- ============ Pembayaran & QRIS ============ -->
<div class="tab-panel<?= $tab === 'pembayaran' ? ' is-aktif' : '' ?>" data-panel="pembayaran">
    <div class="grid-kartu grid-2">
        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('cash') ?> Pajak, Service &amp; Pembulatan</h3></div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="simpan_pembayaran">
                <input type="hidden" name="tab" value="pembayaran">
                <div class="baris-tombol" style="margin-bottom:12px">
                    <label class="cek"><input type="checkbox" name="pajak_aktif" value="1" <?= setting_bool('pajak_aktif', true) ? 'checked' : '' ?>> Pajak aktif</label>
                    <label class="cek"><input type="checkbox" name="service_aktif" value="1" <?= setting_bool('service_aktif', false) ? 'checked' : '' ?>> Service charge aktif</label>
                </div>
                <div class="form-baris form-3">
                    <div class="kolom">
                        <label for="pajak_persen">Pajak (%)</label>
                        <input type="number" id="pajak_persen" name="pajak_persen" min="0" max="100" step="0.5" value="<?= h((string) setting_num('pajak_persen', 0)) ?>">
                    </div>
                    <div class="kolom">
                        <label for="service_persen">Service (%)</label>
                        <input type="number" id="service_persen" name="service_persen" min="0" max="100" step="0.5" value="<?= h((string) setting_num('service_persen', 0)) ?>">
                    </div>
                    <div class="kolom">
                        <label for="pembulatan">Pembulatan total</label>
                        <select id="pembulatan" name="pembulatan">
                            <?php foreach ([0 => 'Tidak dibulatkan', 100 => 'ke atas 100', 500 => 'ke atas 500', 1000 => 'ke atas 1.000'] as $v => $l): ?>
                                <option value="<?= (int) $v ?>" <?= (int) setting_num('pembulatan', 0) === (int) $v ? 'selected' : '' ?>><?= h($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-baris form-3">
                    <div class="kolom">
                        <label for="mata_uang">Awalan mata uang</label>
                        <input type="text" id="mata_uang" name="mata_uang" value="<?= h(setting('mata_uang', 'Rp')) ?>">
                    </div>
                    <div class="kolom">
                        <label for="kode_pesanan_prefix">Prefiks kode pesanan</label>
                        <input type="text" id="kode_pesanan_prefix" name="kode_pesanan_prefix" maxlength="3" value="<?= h(setting('kode_pesanan_prefix', 'K')) ?>">
                    </div>
                    <div class="kolom">
                        <label for="alamat_publik">Alamat publik aplikasi (untuk QR)</label>
                        <input type="text" id="alamat_publik" name="alamat_publik" value="<?= h(setting('alamat_publik')) ?>" placeholder="<?= h($dasar) ?>">
                    </div>
                </div>
                <p class="bantuan" style="color:var(--muted);font-size:.82rem">
                    Alamat publik terdeteksi: <strong><?= h($dasar !== '' ? $dasar : 'tidak terdeteksi') ?></strong>.
                    Isi kolom di atas hanya bila QR meja perlu menunjuk alamat lain (mis. domain sendiri).
                </p>
                <div class="baris-tombol" style="margin:12px 0">
                    <label class="cek"><input type="checkbox" name="tunai_aktif" value="1" <?= setting_bool('tunai_aktif', true) ? 'checked' : '' ?>> Aktifkan pembayaran tunai di kasir</label>
                    <label class="cek"><input type="checkbox" name="qris_aktif" value="1" <?= setting_bool('qris_aktif', true) ? 'checked' : '' ?>> Aktifkan pembayaran QRIS</label>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="qris">Gambar QRIS kafe (statis)</label>
                    <?php if (setting('qris_gambar_url') !== ''): ?>
                        <img src="<?= h(setting('qris_gambar_url')) ?>" alt="QRIS" style="max-width:220px;border-radius:12px;border:1px solid var(--line);margin-bottom:8px">
                    <?php endif; ?>
                    <input type="file" id="qris" name="qris" accept="image/*">
                    <span class="bantuan">Gambar QRIS ini muncul di halaman pelanggan dan di layar kasir saat menandai pembayaran QRIS.</span>
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="qris_catatan">Catatan pembayaran QRIS</label>
                    <input type="text" id="qris_catatan" name="qris_catatan" value="<?= h(setting('qris_catatan')) ?>">
                </div>
                <div class="form-aksi">
                    <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> Simpan</button>
                </div>
            </form>
            <?php if (setting('qris_gambar_url') !== ''): ?>
                <form method="post" style="margin-top:10px">
                    <input type="hidden" name="aksi" value="qris_hapus">
                    <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus gambar QRIS?">Hapus gambar QRIS</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('wifi') ?> Cara Kerja Pembayaran</h3></div>
            <p class="kartu-sub">Aplikasi ini memakai <strong>QRIS statis</strong> milik kafe (gambar yang diunggah di atas):</p>
            <div class="info-baris"><span>1. Pelanggan memilih menu lewat QR meja</span><strong>Pilih "QRIS" atau "Tunai di Kasir"</strong></div>
            <div class="info-baris"><span>2. Untuk QRIS, pelanggan memindai gambar QRIS</span><strong>Nominal diisi manual di aplikasi bank/e-wallet</strong></div>
            <div class="info-baris"><span>3. Kasir memeriksa mutasi masuk</span><strong>Lalu tekan "Bayar" → QRIS pada pesanan itu</strong></div>
            <div class="info-baris"><span>4. Untuk tunai, kasir memasukkan uang diterima</span><strong>Kembalian dihitung otomatis</strong></div>
            <div class="info-baris"><span>5. Setelah lunas</span><strong>Struk digital pelanggan aktif otomatis</strong></div>
            <p class="bantuan" style="color:var(--muted);font-size:.82rem;margin-top:12px">
                Konfirmasi pembayaran dilakukan manual oleh kasir (aplikasi tidak terhubung ke rekening bank),
                jadi tidak ada notifikasi pembayaran otomatis dari bank.
            </p>
        </section>
    </div>
</div>

<!-- ============ Member & Poin ============ -->
<div class="tab-panel<?= $tab === 'member' ? ' is-aktif' : '' ?>" data-panel="member">
    <section class="kartu">
        <div class="kartu-judul"><h3><?= icon('users') ?> Program Member</h3></div>
        <form method="post">
            <input type="hidden" name="aksi" value="simpan_member">
            <input type="hidden" name="tab" value="member">
            <label class="cek" style="margin-bottom:14px"><input type="checkbox" name="poin_aktif" value="1" <?= setting_bool('poin_aktif', true) ? 'checked' : '' ?>> Aktifkan pemberian poin belanja untuk member</label>
            <div class="form-baris form-3">
                <div class="kolom">
                    <label for="poin_per_rupiah">Poin per (Rp)</label>
                    <input type="number" id="poin_per_rupiah" name="poin_per_rupiah" min="1" step="1000" value="<?= h((string) (int) setting_num('poin_per_rupiah', 10000)) ?>">
                    <span class="bantuan">Contoh: 10.000 → setiap Rp 10.000 belanja mendapat 1 poin.</span>
                </div>
                <div class="kolom">
                    <label for="poin_nilai">Nilai poin</label>
                    <input type="number" id="poin_nilai" name="poin_nilai" min="0" step="0.5" value="<?= h((string) setting_num('poin_nilai', 1)) ?>">
                </div>
                <div class="kolom">
                    <label for="diskon_member_persen">Diskon member otomatis (%)</label>
                    <input type="number" id="diskon_member_persen" name="diskon_member_persen" min="0" max="100" step="0.5" value="<?= h((string) setting_num('diskon_member_persen', 0)) ?>">
                    <span class="bantuan">Diterapkan otomatis di kasir saat pelanggan member dipilih.</span>
                </div>
            </div>
            <div class="form-aksi">
                <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> Simpan</button>
                <a class="tombol" href="pelanggan.php">Kelola Data Pelanggan</a>
            </div>
        </form>
    </section>
</div>

<!-- ============ Meja & QR ============ -->
<div class="tab-panel<?= $tab === 'meja' ? ' is-aktif' : '' ?>" data-panel="meja">
    <div class="grid-kartu" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)">
        <section class="kartu">
            <div class="kartu-judul">
                <h3><?= icon('table') ?> Meja &amp; QR Pemesanan (<?= count($meja) ?>)</h3>
                <a class="tombol tombol-kecil tombol-utama" href="meja-qr.php" target="_blank" rel="noopener"><?= icon('qr') ?> Cetak Kartu QR</a>
            </div>
            <?php if (!$meja): ?>
                <p class="kosong-teks">Belum ada meja. Tambahkan meja agar pelanggan bisa memesan lewat QR.</p>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th>Meja</th><th>Mulai QR</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($meja as $m): ?>
                            <tr>
                                <td>
                                    <form method="post" id="meja-<?= (int) $m['id'] ?>" hidden>
                                        <input type="hidden" name="aksi" value="meja_simpan">
                                        <input type="hidden" name="tab" value="meja">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktif" value="<?= (int) $m['aktif'] ?>">
                                    </form>
                                    <input type="text" name="nomor" form="meja-<?= (int) $m['id'] ?>" value="<?= h($m['nomor']) ?>" style="width:80px" required>
                                    <input type="text" name="nama" form="meja-<?= (int) $m['id'] ?>" value="<?= h($m['nama']) ?>" style="width:120px">
                                    <input type="hidden" name="urutan" form="meja-<?= (int) $m['id'] ?>" value="<?= (int) $m['urutan'] ?>">
                                </td>
                                <td>
                                    <a class="tombol tombol-kecil" href="<?= h(url_meja($m)) ?>" target="_blank" rel="noopener">Buka tautan</a>
                                    <div style="font-size:.72rem;color:var(--muted);word-break:break-all;max-width:280px"><?= h(url_meja($m)) ?></div>
                                </td>
                                <td><span class="pil <?= (int) $m['aktif'] === 1 ? 'pil-ok' : 'pil-err' ?>"><?= (int) $m['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                                <td>
                                    <div class="baris-tombol">
                                        <button class="tombol tombol-kecil" type="submit" form="meja-<?= (int) $m['id'] ?>"><?= icon('check') ?></button>
                                        <form method="post">
                                            <input type="hidden" name="aksi" value="meja_token">
                                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                            <input type="hidden" name="tab" value="meja">
                                            <button class="tombol tombol-kecil" data-konfirmasi="Buat ulang QR meja ini? Kartu QR lama tidak berlaku lagi.">QR baru</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="aksi" value="meja_hapus">
                                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                            <input type="hidden" name="tab" value="meja">
                                            <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus meja <?= h($m['nomor']) ?>?"><?= icon('trash') ?></button>
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
            <div class="kartu-judul"><h3><?= icon('plus') ?> Tambah Meja</h3></div>
            <form method="post">
                <input type="hidden" name="aksi" value="meja_simpan">
                <input type="hidden" name="tab" value="meja">
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="meja_nomor">Nomor meja *</label>
                        <input type="text" id="meja_nomor" name="nomor" required placeholder="11">
                    </div>
                    <div class="kolom">
                        <label for="meja_nama">Nama tampilan</label>
                        <input type="text" id="meja_nama" name="nama" placeholder="Meja Teras">
                    </div>
                </div>
                <div class="baris-tombol" style="margin-bottom:12px">
                    <label class="cek"><input type="checkbox" name="aktif" value="1" checked> Meja aktif</label>
                </div>
                <div class="form-aksi">
                    <button class="tombol tombol-utama" type="submit"><?= icon('plus') ?> Tambah Meja</button>
                </div>
            </form>
            <hr style="border:0;border-top:1px dashed var(--line);margin:18px 0">
            <form method="post">
                <input type="hidden" name="aksi" value="meja_tambah_banyak">
                <input type="hidden" name="tab" value="meja">
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="mulai">Mulai dari nomor</label>
                        <input type="number" id="mulai" name="mulai" min="1" value="11">
                    </div>
                    <div class="kolom">
                        <label for="jumlah">Jumlah meja</label>
                        <input type="number" id="jumlah" name="jumlah" min="1" max="50" value="10">
                    </div>
                </div>
                <div class="form-aksi">
                    <button class="tombol" type="submit">Tambah sekaligus</button>
                </div>
            </form>
            <p class="bantuan" style="color:var(--muted);font-size:.82rem;margin-top:14px">
                Setiap meja punya QR sendiri. Cetak kartu QR-nya (tombol "Cetak Kartu QR"), lalu letakkan di meja.
                Pelanggan memindai QR → memilih menu → memilih metode bayar → pesanan langsung tampil di kasir.
            </p>
        </section>
    </div>
</div>

<!-- ============ Struk & Printer ============ -->
<div class="tab-panel<?= $tab === 'struk' ? ' is-aktif' : '' ?>" data-panel="struk">
    <div class="grid-kartu grid-2">
        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('receipt') ?> Struk</h3></div>
            <form method="post">
                <input type="hidden" name="aksi" value="simpan_struk">
                <input type="hidden" name="tab" value="struk">
                <div class="kolom" style="margin-bottom:12px">
                    <label for="struk_judul">Judul struk</label>
                    <input type="text" id="struk_judul" name="struk_judul" value="<?= h(setting('struk_judul')) ?>">
                </div>
                <div class="kolom" style="margin-bottom:12px">
                    <label for="struk_catatan">Catatan bawah struk</label>
                    <textarea id="struk_catatan" name="struk_catatan"><?= h(setting('struk_catatan')) ?></textarea>
                </div>
                <div class="form-baris form-2">
                    <div class="kolom">
                        <label for="printer_kertas">Ukuran kertas cetak</label>
                        <select id="printer_kertas" name="printer_kertas">
                            <option value="thermal80" <?= setting('printer_kertas') === 'thermal80' ? 'selected' : '' ?>>Thermal 80 mm</option>
                            <option value="thermal58" <?= setting('printer_kertas') === 'thermal58' ? 'selected' : '' ?>>Thermal 58 mm</option>
                            <option value="a4" <?= setting('printer_kertas') === 'a4' ? 'selected' : '' ?>>A4 / Letter</option>
                        </select>
                    </div>
                </div>
                <div class="baris-tombol" style="margin-bottom:14px">
                    <label class="cek"><input type="checkbox" name="struk_tampil_logo" value="1" <?= setting_bool('struk_tampil_logo', true) ? 'checked' : '' ?>> Tampilkan logo di struk</label>
                    <label class="cek"><input type="checkbox" name="printer_otomatis" value="1" <?= setting_bool('printer_otomatis', true) ? 'checked' : '' ?>> Buka dialog cetak otomatis setelah pembayaran</label>
                </div>
                <div class="form-aksi">
                    <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> Simpan</button>
                </div>
            </form>
        </section>

        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('print') ?> Cetak Tanpa Dialog (Opsional)</h3></div>
            <p class="kartu-sub">
                Browser selalu menampilkan dialog cetak — itu aturan keamanan dan tidak bisa dilewati dari dalam halaman web.
                Untuk kasir yang ingin <strong>langsung mencetak</strong> (tanpa menekan tombol), jalankan browser kasir dengan
                mode cetak senyap:
            </p>
            <div class="info-baris"><span>Chrome / Edge (Windows)</span><strong>Buat pintasan dengan tambahan <code>--kiosk-printing</code></strong></div>
            <pre style="background:var(--cream);padding:12px;border-radius:11px;overflow-x:auto;font-size:.82rem">"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing</pre>
            <div class="info-baris"><span>Firefox</span><strong>about:config → <code>print.always_print_silent = true</code></strong></div>
            <p class="bantuan" style="color:var(--muted);font-size:.82rem;margin-top:12px">
                Struk juga selalu bisa dibuka ulang dari menu <strong>Data Penjualan → Struk</strong> atau dari HP pelanggan.
            </p>
        </section>
    </div>
</div>

<!-- ============ Akun & Keamanan ============ -->
<div class="tab-panel<?= $tab === 'akun' ? ' is-aktif' : '' ?>" data-panel="akun">
    <div class="grid-kartu" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)">
        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('users') ?> Akun Pengguna</h3></div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th>Akses</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($akun as $a): ?>
                        <tr>
                            <td><strong><?= h($a['username']) ?></strong></td>
                            <td><?= h($a['nama']) ?></td>
                            <td><?= h(label_role((string) $a['role'])) ?></td>
                            <td style="font-size:.78rem;color:var(--muted)">
                                <?php
                                $akses = [];
                                foreach (menu_peran((string) $a['role']) as $f) {
                                    $m = menu_akses();
                                    $akses[] = $m[$f][0] ?? $f;
                                }
                                echo h(implode(', ', $akses) ?: '—');
                                ?>
                            </td>
                            <td><span class="pil <?= (int) $a['aktif'] === 1 ? 'pil-ok' : 'pil-err' ?>"><?= (int) $a['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <div class="baris-tombol">
                                    <a class="tombol tombol-kecil" href="pengaturan.php?tab=akun&user=<?= (int) $a['id'] ?>"><?= icon('edit') ?> Ubah</a>
                                    <form method="post">
                                        <input type="hidden" name="aksi" value="user_hapus">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <input type="hidden" name="tab" value="akun">
                                        <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus akun <?= h($a['username']) ?>?"><?= icon('trash') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
            $u = null;
            if ($editUser > 0) {
                foreach ($akun as $a) {
                    if ((int) $a['id'] === $editUser) {
                        $u = $a;
                    }
                }
            }
            ?>
            <h3 style="margin-top:22px"><?= $u ? 'Ubah Akun: ' . h($u['username']) : 'Buat Akun Baru (Kasir / Petugas Dapur)' ?></h3>
            <form method="post">
                <input type="hidden" name="aksi" value="<?= $u ? 'user_simpan' : 'user_tambah' ?>">
                <input type="hidden" name="tab" value="akun">
                <input type="hidden" name="id" value="<?= $u ? (int) $u['id'] : 0 ?>">
                <div class="form-baris form-3">
                    <div class="kolom">
                        <label for="u_username">Username</label>
                        <input type="text" id="u_username" name="username" required value="<?= h($u['username'] ?? '') ?>" placeholder="kasir2">
                    </div>
                    <div class="kolom">
                        <label for="u_nama">Nama lengkap</label>
                        <input type="text" id="u_nama" name="nama" value="<?= h($u['nama'] ?? '') ?>">
                    </div>
                    <div class="kolom">
                        <label for="u_role">Peran</label>
                        <?php
                        /* Saat MEMBUAT akun baru, bawaan = Kasir (bukan Owner), supaya kekeliruan memilih
                           tidak diam-diam membuat akun berakses penuh. Saat mengubah, peran akun ditampilkan. */
                        $peranTerpilih = $u ? (string) $u['role'] : 'kasir';
                        ?>
                        <select id="u_role" name="role">
                            <?php foreach (daftar_peran() as $k => $v): ?>
                                <option value="<?= h($k) ?>" <?= $peranTerpilih === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="bantuan">Kasir hanya bisa membuka menu Kasir. Owner dapat membuka seluruh menu.</span>
                    </div>
                </div>
                <div class="form-baris form-3">
                    <div class="kolom">
                        <label for="u_password">Kata sandi <?= $u ? '(kosongkan bila tidak diubah)' : '' ?></label>
                        <input type="password" id="u_password" name="password" <?= $u ? '' : 'required' ?>>
                    </div>
                    <div class="kolom">
                        <label for="u_ulang">Ulangi kata sandi</label>
                        <input type="password" id="u_ulang" name="ulang" <?= $u ? '' : 'required' ?>>
                    </div>
                    <div class="kolom">
                        <label for="u_aktif">Status akun</label>
                        <select id="u_aktif" name="aktif">
                            <option value="1" <?= !$u || (int) $u['aktif'] === 1 ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $u && (int) $u['aktif'] === 0 ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <?php if ($u): ?>
                    <div class="kolom" style="margin-bottom:12px">
                        <label for="u_pengawas">Konfirmasi dengan kata sandi akun Anda sendiri</label>
                        <input type="password" id="u_pengawas" name="pengawas" required>
                    </div>
                <?php endif; ?>
                <div class="form-aksi">
                    <button class="tombol tombol-utama" type="submit"><?= icon('check') ?> <?= $u ? 'Simpan Perubahan' : 'Buat Akun' ?></button>
                    <?php if ($u): ?><a class="tombol" href="pengaturan.php?tab=akun">Batal</a><?php endif; ?>
                </div>
            </form>
        </section>

        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('cog') ?> Hak Akses</h3></div>
            <div class="info-baris"><span>Owner / Admin</span><strong>Seluruh menu + Pengaturan</strong></div>
            <div class="info-baris"><span>Kasir</span><strong>Hanya menu Kasir</strong></div>
            <div class="info-baris"><span>Petugas Dapur</span><strong>Hanya Display Dapur</strong></div>
            <div class="info-baris"><span>Pelanggan</span><strong>Tanpa akun (lewat QR meja)</strong></div>
            <p class="bantuan" style="color:var(--muted);font-size:.82rem;margin-top:14px">
                Setiap perubahan akun wajib dikonfirmasi dengan kata sandi akun yang sedang Anda pakai.
                Mengganti kata sandi akan memutus sesi lain pada akun tersebut.
            </p>
        </section>
    </div>
</div>

<!-- ============ Data & Riwayat ============ -->
<div class="tab-panel<?= $tab === 'data' ? ' is-aktif' : '' ?>" data-panel="data">
    <div class="grid-kartu grid-2">
        <section class="kartu" id="hapus-pesanan">
            <div class="kartu-judul"><h3><?= icon('trash') ?> Hapus Satu Pesanan (salah input / pelanggan batal)</h3></div>
            <p class="kartu-sub">
                Dipakai bila ada pesanan yang salah dibuat atau dibatalkan pelanggan. Penghapusan dicatat pada
                Riwayat Tindakan. Nomor pesanan hari ini tidak dimundurkan, jadi tidak akan ada nomor kembar.
            </p>
            <?php $pesananHariIni = pesanan_daftar(hari_ini(), hari_ini(), ['batas' => 40]); ?>
            <?php if (!$pesananHariIni): ?>
                <p class="kosong-teks">Belum ada pesanan hari ini.</p>
            <?php else: ?>
                <div class="tabel-bungkus" style="max-height:340px;overflow-y:auto">
                    <table class="tabel">
                        <thead><tr><th>Jam</th><th>Kode</th><th>Meja</th><th>Pelanggan</th><th>Status</th><th class="angka">Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($pesananHariIni as $r): ?>
                            <tr>
                                <td><?= h(jam_teks((string) $r['created_at'], false)) ?></td>
                                <td><strong><?= h($r['kode']) ?></strong></td>
                                <td><?= h(pesanan_label_meja($r)) ?></td>
                                <td style="font-size:.82rem"><?= h((string) $r['nama_pelanggan'] !== '' ? (string) $r['nama_pelanggan'] : '—') ?></td>
                                <td>
                                    <span class="pil <?= (string) $r['status_bayar'] === BAYAR_LUNAS ? 'pil-ok' : 'pil-warn' ?>"><?= h((string) $r['status_bayar'] === BAYAR_LUNAS ? 'Lunas' : 'Belum') ?></span>
                                    <span class="pil pil-info"><?= h(LABEL_STATUS_PENDEK[(string) $r['status']] ?? '') ?></span>
                                </td>
                                <td class="angka"><?= h(rupiah($r['total'])) ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="aksi" value="pesanan_hapus">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <input type="hidden" name="tab" value="data">
                                        <button class="tombol tombol-kecil tombol-bahaya" data-konfirmasi="Hapus pesanan <?= h($r['kode']) ?>? Data pesanan ini akan hilang dari laporan."><?= icon('trash') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="kartu" id="hapus-laporan">
            <div class="kartu-judul"><h3><?= icon('trash') ?> Hapus Data Penjualan per Rentang</h3></div>
            <p class="kartu-sub">
                Dipakai membersihkan data uji atau percobaan. Menghapus pesanan (beserta item dan riwayat statusnya)
                pada rentang tanggal tertentu. <strong>Data yang dihapus tidak dapat dikembalikan.</strong>
            </p>
            <form method="post">
                <input type="hidden" name="aksi" value="data_hapus">
                <input type="hidden" name="tab" value="data">
                <div class="form-baris form-3">
                    <div class="kolom">
                        <label for="hapus_dari">Dari tanggal</label>
                        <input type="text" class="tgl" id="hapus_dari" name="dari" value="<?= h(tgl_input(hari_ini())) ?>" required>
                    </div>
                    <div class="kolom">
                        <label for="hapus_sampai">Sampai tanggal</label>
                        <input type="text" class="tgl" id="hapus_sampai" name="sampai" value="<?= h(tgl_input(hari_ini())) ?>" required>
                    </div>
                    <div class="kolom">
                        <label for="konfirmasi">Ketik HAPUS untuk konfirmasi</label>
                        <input type="text" id="konfirmasi" name="konfirmasi" placeholder="HAPUS" required>
                    </div>
                </div>
                <div class="form-aksi">
                    <button class="tombol tombol-bahaya" type="submit" data-konfirmasi="Hapus data pesanan pada rentang tanggal ini?">Hapus Data</button>
                </div>
            </form>
        </section>

        <section class="kartu">
            <div class="kartu-judul"><h3><?= icon('receipt') ?> Riwayat Tindakan (Audit)</h3></div>
            <?php $log = log_admin_terakhir(25); ?>
            <?php if (!$log): ?>
                <p class="kosong-teks">Belum ada catatan tindakan.</p>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th>Waktu</th><th>Oleh</th><th>Tindakan</th><th>Rincian</th></tr></thead>
                        <tbody>
                        <?php foreach ($log as $l): ?>
                            <tr>
                                <td><?= h(jam_teks((string) $l['waktu'])) ?></td>
                                <td><?= h(nama_pengguna((string) $l['oleh'])) ?></td>
                                <td><?= h(str_replace('_', ' ', (string) $l['aksi'])) ?></td>
                                <td style="font-size:.8rem"><?= h((string) $l['rincian']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php page_end(); ?>
