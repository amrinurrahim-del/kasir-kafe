<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

$err = '';
if (is_post()) {
    $r = auth_login((string) ($_POST['username'] ?? ''), (string) ($_POST['sandi'] ?? ''));
    if (!empty($r['ok'])) {
        redirect(halaman_utama_role((string) $r['role']));
    }
    $err = (string) $r['error'];
}
$pesanErr = $err !== '' ? $err : (string) ($_GET['err'] ?? '');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — <?= h(nama_kafe()) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
</head>
<body class="masuk-halaman">
<div class="masuk-kartu">
    <div class="masuk-logo">
        <?php if (logo_url() !== ''): ?>
            <img src="<?= h(logo_url()) ?>" alt="<?= h(nama_kafe()) ?>">
        <?php else: ?>
            <span class="brand-ikon"><?= icon('coffee') ?></span>
        <?php endif; ?>
        <h1><?= h(nama_kafe()) ?></h1>
        <p><?= h(setting('tagline_kafe', 'Aplikasi Kasir Kafe')) ?></p>
    </div>

    <?php if ($pesanErr !== ''): ?>
        <div class="flash flash-err"><?= h($pesanErr) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="kolom" style="margin-bottom:14px">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <div class="kolom" style="margin-bottom:18px">
            <label for="sandi">Kata sandi</label>
            <input type="password" id="sandi" name="sandi" required>
        </div>
        <button class="tombol tombol-utama tombol-blok" type="submit"><?= icon('out') ?> Masuk</button>
    </form>

    <?php $peringatan = kredensial_bawaan(); ?>
    <?php if ($peringatan): ?>
        <div class="flash" style="margin-top:18px">
            <strong>Akun bawaan masih aktif:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">
                <li>owner / owner — akses penuh</li>
                <li>kasir / kasir — hanya menu Kasir</li>
                <li>dapur / dapur — hanya Display Dapur</li>
            </ul>
            <em style="font-size:.8rem">Segera ganti kata sandi di Pengaturan → Akun &amp; Keamanan setelah masuk.</em>
        </div>
    <?php endif; ?>

    <p style="text-align:center;margin:16px 0 0">
        <a href="display.php" target="_blank" rel="noopener">Buka Display Menu (TV)</a>
    </p>
</div>

<?php /* Tombol layar penuh mengambang — sama seperti di seluruh halaman aplikasi. */ ?>
<?php tombol_layar_penuh(); ?>
<div class="toast-wrap" id="toast-wrap"></div>
<script src="<?= h(asset('assets/app.js')) ?>"></script>
</body>
</html>
