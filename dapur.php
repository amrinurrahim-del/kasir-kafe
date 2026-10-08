<?php
declare(strict_types=1);

/* Display dapur: memperbarui status pesanan (diterima → disiapkan → siap → selesai). */

require_once __DIR__ . '/lib.php';

$user = require_login(['owner', 'dapur']);
$awal = payload_dapur();

page_head('Display Dapur', 'dapur.php', ['kelas' => 'dapur-mode']);
flash();
?>
<div class="kartu" style="margin-bottom:20px">
    <div class="kartu-judul" style="margin-bottom:0">
        <h3><?= icon('chef') ?> Papan Dapur — <?= h(tgl_label(hari_ini(), true)) ?></h3>
        <div class="baris-tombol">
            <span class="pil pil-gelap" id="jam-dapur"><?= h(date('H:i:s')) ?></span>
            <span class="pil" id="info-baru">0 pesanan baru</span>
            <span class="pil" id="status-hubung">Memantau…</span>
        </div>
    </div>
</div>

<div class="dapur-grid" id="dapur-grid"></div>

<section class="kartu" style="margin-top:22px">
    <div class="kartu-judul"><h3><?= icon('check') ?> Selesai (selamat menikmati)</h3><span class="pil" id="jumlah-selesai">0</span></div>
    <div class="dapur-selesai" id="dapur-selesai"></div>
</section>

<script id="data-dapur" type="application/json"><?= json_encode($awal, JSON_UNESCAPED_UNICODE) ?></script>
<?php page_end('assets/dapur.js'); ?>
