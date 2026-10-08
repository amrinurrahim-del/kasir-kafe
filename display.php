<?php
declare(strict_types=1);

/* Layar display menu kafe (TV / monitor kedua) — PUBLIK, tanpa login. */

require_once __DIR__ . '/lib.php';

$menu = payload_menu();
$kategori = $menu['kategori'];
$siap = payload_siap();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Display Menu — <?= h(nama_kafe()) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
<style>:root{--accent:<?= h(setting('tema_warna', '#B4763B')) ?>;}</style>
</head>
<body class="display-halaman">
<div class="display-shell">
    <header class="display-head">
        <?php if (logo_url() !== ''): ?>
            <img src="<?= h(logo_url()) ?>" alt="<?= h(nama_kafe()) ?>">
        <?php endif; ?>
        <div class="nama">
            <h1><?= h(nama_kafe()) ?></h1>
            <p><?= h(setting('display_judul', 'MENU KAFE')) ?><?= setting('tagline_kafe') !== '' ? ' · ' . h(setting('tagline_kafe')) : '' ?></p>
        </div>
        <div class="display-jam">
            <strong id="jam-display"><?= h(date('H:i')) ?></strong>
            <span id="tgl-display"><?= h(tgl_label(hari_ini(), true)) ?></span>
        </div>
    </header>

    <section class="display-menu" id="menu-scroll">
        <div class="display-menu-track" id="menu-track" data-kolom="<?= (int) max(2, min(4, setting_num('display_kolom', 3))) ?>"></div>
        <p class="display-kosong" id="menu-kosong" hidden>Menu belum tersedia.</p>
    </section>

    <?php if (setting_bool('display_panel_siap', true)): ?>
        <section class="display-siap" id="panel-siap">
            <h2>Pesanan Siap Disajikan</h2>
            <div class="display-siap-list" id="siap-list"></div>
        </section>
    <?php endif; ?>

    <div class="display-footer-wrap">
        <div class="marquee-track" id="marquee">
            <span id="marquee-teks"><?= h(setting('display_footer')) ?></span>
            <span><?= h(setting('display_footer')) ?></span>
        </div>
    </div>
</div>

<div class="display-tombol">
    <button type="button" id="btn-layar-penuh" title="Layar penuh" aria-label="Layar penuh">
        <svg viewBox="0 0 24 24"><path d="M4 4h6v2H6v4H4zm10 0h6v6h-2V6h-4zM4 14h2v4h4v2H4zm14 0h2v6h-6v-2h4z"/></svg>
    </button>
</div>

<script id="data-menu" type="application/json"><?= json_encode($menu, JSON_UNESCAPED_UNICODE) ?></script>
<script id="data-siap" type="application/json"><?= json_encode($siap, JSON_UNESCAPED_UNICODE) ?></script>
<?php
/* Aset ditutup: display.js memakai window.ASSET_VERSI untuk memuat ulang dirinya
   satu kali bila CSS/JS-nya sudah diperbarui (layar TV tidak pernah di-refresh manual). */
?>
<script>window.ASSET_VERSI = <?= (int) asset_versi() ?>;</script>
<script src="<?= h(asset('assets/display.js')) ?>"></script>
</body>
</html>
