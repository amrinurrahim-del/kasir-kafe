<?php
declare(strict_types=1);

/*
 * Autentikasi & hak akses Aplikasi Kasir Kafe.
 *
 * Tiga peran (tanpa pendaftaran publik):
 *   - owner : akses penuh (dasbor, kasir, data produk, penjualan, laporan, grafik,
 *             data pelanggan, pengaturan, display dapur) + membuat akun kasir.
 *   - kasir : HANYA menu Kasir (sesuai permintaan pemilik).
 *   - dapur : HANYA layar dapur (memperbarui status pesanan).
 *
 * Pelanggan tidak punya akun: mereka memesan lewat QR meja (halaman publik pesan.php).
 *
 * Sesi memakai cookie acak + tabel `sesi` (hash sha256), bukan session PHP,
 * supaya tidak bergantung pada folder penyimpanan session milik server.
 */

require_once __DIR__ . '/db.php';

const SESI_COOKIE = 'kk_sesi';
const SESI_JAM    = 12;
const MAX_GAGAL   = 5;
const KUNCI_DETIK = 300;

function request_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function shift_time(int $detik): string
{
    return date('Y-m-d H:i:s', time() + $detik);
}

function sesi_set_cookie(string $token, int $detik): void
{
    setcookie(SESI_COOKIE, $token, [
        'expires'  => time() + $detik,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => request_https(),
    ]);
    $_COOKIE[SESI_COOKIE] = $token;
}

function sesi_hapus_cookie(): void
{
    setcookie(SESI_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => request_https(),
    ]);
    unset($_COOKIE[SESI_COOKIE]);
}

/* ------------------------------------------------------------------ */
/* Peran                                                              */
/* ------------------------------------------------------------------ */

function daftar_peran(): array
{
    return [
        'owner' => 'Owner / Admin',
        'kasir' => 'Kasir',
        'dapur' => 'Petugas Dapur',
    ];
}

function label_role(string $role): string
{
    $p = daftar_peran();
    return $p[$role] ?? $role;
}

/** Menu yang boleh dibuka sebuah peran (dipakai header + penjagaan halaman). */
function menu_peran(string $role): array
{
    if ($role === 'owner') {
        return ['index.php', 'kasir.php', 'produk.php', 'penjualan.php', 'laporan.php', 'grafik.php', 'pelanggan.php', 'dapur.php', 'pengaturan.php'];
    }
    if ($role === 'kasir') {
        return ['kasir.php'];
    }
    if ($role === 'dapur') {
        return ['dapur.php'];
    }
    return [];
}

/* ------------------------------------------------------------------ */
/* Pengguna                                                           */
/* ------------------------------------------------------------------ */

function daftar_user(): array
{
    return db()->query('SELECT id, username, nama, role, aktif, created_at, updated_at, failed_count, locked_until
                        FROM user
                        ORDER BY CASE role WHEN "owner" THEN 1 WHEN "kasir" THEN 2 ELSE 3 END, username')->fetchAll();
}

/**
 * Tambah akun baru (owner membuat akun kasir / petugas dapur).
 */
function auth_tambah_user(string $username, string $password, string $ulang, string $role, string $nama): array
{
    $username = trim($username);
    $nama     = trim($nama);
    if (!preg_match('/^[A-Za-z0-9._\-]{3,32}$/', $username)) {
        return ['ok' => false, 'error' => 'Username 3-32 karakter, hanya huruf, angka, titik, garis bawah, atau strip.'];
    }
    if (strlen($password) < 5) {
        return ['ok' => false, 'error' => 'Kata sandi minimal 5 karakter.'];
    }
    if ($password !== $ulang) {
        return ['ok' => false, 'error' => 'Ulangi kata sandi tidak sama.'];
    }
    $role = array_key_exists($role, daftar_peran()) ? $role : 'kasir';
    $cek = db()->prepare('SELECT COUNT(*) FROM user WHERE lower(username) = lower(?)');
    $cek->execute([$username]);
    if ((int) $cek->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'Username sudah dipakai.'];
    }
    db()->prepare('INSERT INTO user (username, nama, password_hash, role, aktif, created_at, updated_at)
                   VALUES (?, ?, ?, ?, 1, ?, ?)')
        ->execute([$username, $nama !== '' ? $nama : $username, password_hash($password, PASSWORD_DEFAULT), $role, now(), now()]);
    return ['ok' => true, 'username' => $username];
}

function auth_ubah_user(int $id, string $username, string $nama, string $role, string $aktif, string $password, string $ulang, string $pengawas): array
{
    $me = auth_user();
    if (!$me) {
        return ['ok' => false, 'error' => 'Sesi berakhir, silakan masuk kembali.'];
    }
    $st = db()->prepare('SELECT * FROM user WHERE id = ?');
    $st->execute([$id]);
    $target = $st->fetch();
    if (!$target) {
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    }
    $pw = db()->prepare('SELECT password_hash FROM user WHERE id = ?');
    $pw->execute([(int) $me['id']]);
    if (!password_verify($pengawas, (string) $pw->fetchColumn())) {
        return ['ok' => false, 'error' => 'Kata sandi konfirmasi (akun Anda) salah. Perubahan dibatalkan.'];
    }

    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9._\-]{3,32}$/', $username)) {
        return ['ok' => false, 'error' => 'Username 3-32 karakter, hanya huruf, angka, titik, garis bawah, atau strip.'];
    }
    $cek = db()->prepare('SELECT COUNT(*) FROM user WHERE lower(username) = lower(?) AND id <> ?');
    $cek->execute([$username, $id]);
    if ((int) $cek->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'Username sudah dipakai akun lain.'];
    }
    $role = array_key_exists($role, daftar_peran()) ? $role : (string) $target['role'];

    if ((int) $target['id'] === (int) $me['id'] && $aktif !== '1') {
        return ['ok' => false, 'error' => 'Akun yang sedang Anda pakai tidak bisa dinonaktifkan.'];
    }
    /* Jangan biarkan aplikasi kehilangan owner terakhir. */
    if ((string) $target['role'] === 'owner' && $role !== 'owner') {
        $jml = (int) db()->query('SELECT COUNT(*) FROM user WHERE role = "owner" AND aktif = 1')->fetchColumn();
        if ($jml <= 1) {
            return ['ok' => false, 'error' => 'Ini satu-satunya akun owner yang aktif — perannya tidak boleh diubah.'];
        }
    }

    $ubahPw = $password !== '';
    if ($ubahPw) {
        if (strlen($password) < 5) {
            return ['ok' => false, 'error' => 'Kata sandi minimal 5 karakter.'];
        }
        if ($password !== $ulang) {
            return ['ok' => false, 'error' => 'Ulangi kata sandi tidak sama.'];
        }
    }

    db()->prepare('UPDATE user SET username = ?, nama = ?, role = ?, aktif = ?, updated_at = ? WHERE id = ?')
        ->execute([$username, $nama !== '' ? $nama : $username, $role, $aktif === '1' ? 1 : 0, now(), $id]);
    if ($ubahPw) {
        db()->prepare('UPDATE user SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        $hash = (string) ($me['token_hash'] ?? '');
        db()->prepare('DELETE FROM sesi WHERE user_id = ? AND token_hash <> ?')->execute([$id, $hash]);
    }
    if ($aktif !== '1') {
        db()->prepare('DELETE FROM sesi WHERE user_id = ?')->execute([$id]);
    }
    return ['ok' => true];
}

function auth_hapus_user(int $id): array
{
    $me = auth_user();
    if (!$me) {
        return ['ok' => false, 'error' => 'Sesi berakhir.'];
    }
    if ($id === (int) $me['id']) {
        return ['ok' => false, 'error' => 'Akun yang sedang dipakai tidak bisa dihapus.'];
    }
    $st = db()->prepare('SELECT * FROM user WHERE id = ?');
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) {
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    }
    if ((string) $t['role'] === 'owner') {
        $jml = (int) db()->query('SELECT COUNT(*) FROM user WHERE role = "owner"')->fetchColumn();
        if ($jml <= 1) {
            return ['ok' => false, 'error' => 'Akun owner terakhir tidak boleh dihapus.'];
        }
    }
    db()->prepare('DELETE FROM sesi WHERE user_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM user WHERE id = ?')->execute([$id]);
    return ['ok' => true];
}

/* ------------------------------------------------------------------ */
/* Sesi                                                               */
/* ------------------------------------------------------------------ */

function auth_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;

    $token = (string) ($_COOKIE[SESI_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);

    $st = db()->prepare('SELECT s.token_hash, s.expires_at, u.id, u.username, u.nama, u.role, u.aktif
                         FROM sesi s JOIN user u ON u.id = s.user_id
                         WHERE s.token_hash = ?');
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row || (int) $row['aktif'] !== 1) {
        return null;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        db()->prepare('DELETE FROM sesi WHERE token_hash = ?')->execute([$hash]);
        return null;
    }
    db()->prepare('UPDATE sesi SET last_seen = ?, expires_at = ? WHERE token_hash = ?')
        ->execute([now(), shift_time(SESI_JAM * 3600), $hash]);

    $cache = [
        'id'         => (int) $row['id'],
        'username'   => (string) $row['username'],
        'nama'       => (string) $row['nama'],
        'role'       => (string) $row['role'],
        'token_hash' => $hash,
    ];
    return $cache;
}

function auth_buat_sesi(int $userId): void
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('DELETE FROM sesi WHERE expires_at < ?')->execute([now()]);
    db()->prepare('INSERT INTO sesi (token_hash, user_id, created_at, expires_at, last_seen) VALUES (?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $userId, now(), shift_time(SESI_JAM * 3600), now()]);
    sesi_set_cookie($token, SESI_JAM * 3600);
}

function auth_login(string $username, string $password): array
{
    $username = trim($username);
    $st = db()->prepare('SELECT * FROM user WHERE lower(username) = lower(?) LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();

    if (!$user || (int) $user['aktif'] !== 1) {
        return ['ok' => false, 'error' => 'Username atau kata sandi salah.'];
    }
    if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
        $sisa = max(1, (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60));
        return ['ok' => false, 'error' => 'Akun dikunci sementara karena percobaan gagal berulang. Coba lagi ' . $sisa . ' menit lagi.'];
    }
    if (!password_verify($password, (string) $user['password_hash'])) {
        $gagal = (int) $user['failed_count'] + 1;
        if ($gagal >= MAX_GAGAL) {
            db()->prepare('UPDATE user SET failed_count = 0, locked_until = ? WHERE id = ?')
                ->execute([shift_time(KUNCI_DETIK), (int) $user['id']]);
            return ['ok' => false, 'error' => 'Terlalu banyak percobaan gagal. Akun dikunci ' . (int) ceil(KUNCI_DETIK / 60) . ' menit.'];
        }
        db()->prepare('UPDATE user SET failed_count = ? WHERE id = ?')->execute([$gagal, (int) $user['id']]);
        return ['ok' => false, 'error' => 'Username atau kata sandi salah. Sisa ' . (MAX_GAGAL - $gagal) . ' percobaan sebelum dikunci sementara.'];
    }

    db()->prepare('UPDATE user SET failed_count = 0, locked_until = NULL WHERE id = ?')->execute([(int) $user['id']]);
    auth_buat_sesi((int) $user['id']);
    return ['ok' => true, 'role' => (string) $user['role'], 'username' => (string) $user['username']];
}

function auth_logout(): void
{
    $u = auth_user();
    if ($u) {
        db()->prepare('DELETE FROM sesi WHERE token_hash = ?')->execute([$u['token_hash']]);
    }
    sesi_hapus_cookie();
}

/* ------------------------------------------------------------------ */
/* Penjagaan halaman                                                  */
/* ------------------------------------------------------------------ */

function require_login(array $peran = []): array
{
    $u = auth_user();
    if (!$u) {
        redirect('login.php?err=' . urlencode('Silakan masuk terlebih dahulu.'));
    }
    if ($peran && !in_array((string) $u['role'], $peran, true)) {
        redirect(halaman_utama_role((string) $u['role']) . '?err=' . urlencode('Menu itu tidak tersedia untuk peran akun Anda.'));
    }
    return $u;
}

function require_owner(): array
{
    return require_login(['owner']);
}

/** Halaman pertama yang pantas dibuka sebuah peran. */
function halaman_utama_role(string $role): string
{
    if ($role === 'kasir') {
        return 'kasir.php';
    }
    if ($role === 'dapur') {
        return 'dapur.php';
    }
    return 'index.php';
}

/** Peringatan bila akun masih memakai kata sandi bawaan. */
function kredensial_bawaan(): array
{
    $bawaan = ['owner' => 'owner', 'kasir' => 'kasir', 'dapur' => 'dapur'];
    $hasil = [];
    foreach (daftar_user() as $u) {
        $st = db()->prepare('SELECT password_hash FROM user WHERE id = ?');
        $st->execute([(int) $u['id']]);
        $hash = (string) $st->fetchColumn();
        $nama = (string) $u['username'];
        if (isset($bawaan[$nama]) && password_verify($bawaan[$nama], $hash)) {
            $hasil[] = 'Akun "' . $nama . '" masih memakai kata sandi bawaan — sebaiknya segera diganti.';
        }
    }
    return $hasil;
}
