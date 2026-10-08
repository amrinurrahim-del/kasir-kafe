/*
 * Layar display menu kafe (TV / monitor kedua).
 *
 * Aturan penting (mengikuti pengalaman aplikasi sebelumnya):
 *  - Pemutaran/gulir TIDAK boleh dimulai ulang setiap kali data disegarkan; gulir hanya
 *    diulang saat isinya benar-benar berubah (sidik jari "stamp").
 *  - Gulir memakai salinan kedua isi supaya menyambung tanpa lompatan, dan jarak gulir
 *    diukur dari posisi nyata salinan kedua (bukan dari perkiraan gap).
 */
(function () {
    'use strict';

    var menuData = JSON.parse((document.getElementById('data-menu') || {}).textContent || '{}');
    var siap = JSON.parse((document.getElementById('data-siap') || {}).textContent || '[]');
    var track = document.getElementById('menu-track');
    var scrollBox = document.getElementById('menu-scroll');
    var siapList = document.getElementById('siap-list');
    var kecepatan = parseFloat(menuData.kecepatan || '38') || 38;
    var tampilHarga = menuData.tampil_harga !== 0;
    var animasiMenu = null;
    var animasiSiap = null;
    var stampMenu = menuData.stamp || '';
    var stampSiap = '';
    var offsetServer = 0;

    /* ---------------- Jam (disinkronkan ke jam server) ---------------- */
    function setJamAcuan(teks) {
        if (!teks) { return; }
        var m = /^(\d{1,2}):(\d{2}):?(\d{2})?$/.exec(teks);
        if (!m) { return; }
        var d = new Date();
        var server = new Date(d.getFullYear(), d.getMonth(), d.getDate(), parseInt(m[1], 10), parseInt(m[2], 10), parseInt(m[3] || '0', 10));
        offsetServer = server.getTime() - d.getTime();
    }

    function jamTick() {
        var d = new Date(Date.now() + offsetServer);
        var p = function (n) { return n < 10 ? '0' + n : '' + n; };
        var el = document.getElementById('jam-display');
        if (el) { el.textContent = p(d.getHours()) + ':' + p(d.getMinutes()); }
    }

    /* ---------------- Menu ---------------- */
    function kartuMenu(p) {
        var el = document.createElement('article');
        el.className = 'display-kartu';

        /* Panel gambar transparan; foto mengikuti ukuran panel (object-fit: cover). */
        var foto = document.createElement('div');
        foto.className = 'foto';
        if (p.foto) {
            var img = document.createElement('img');
            img.src = p.foto;
            img.alt = '';
            img.loading = 'lazy';
            foto.appendChild(img);
        } else {
            foto.classList.add('is-kosong');
            var mono = document.createElement('span');
            mono.className = 'monogram';
            mono.textContent = (p.nama || '?').substring(0, 1).toUpperCase();
            foto.appendChild(mono);
        }

        var teks = document.createElement('div');
        teks.className = 'teks';

        var nama = document.createElement('strong');
        nama.className = 'nama';
        nama.textContent = p.nama;

        /* Susunan label: NAMA — kategori — keterangan — harga, dipisah garis tipis. */
        var katBaris = document.createElement('div');
        katBaris.className = 'kat-baris';
        var kat = document.createElement('span');
        kat.className = 'kat';
        kat.textContent = p.kategori || '';
        katBaris.appendChild(kat);
        if (p.favorit) {
            var favorit = document.createElement('span');
            favorit.className = 'favorit';
            favorit.textContent = '★ Favorit';
            katBaris.appendChild(favorit);
        }

        teks.appendChild(nama);
        teks.appendChild(pemisah());
        teks.appendChild(katBaris);

        if (p.deskripsi) {
            var ket = document.createElement('span');
            ket.className = 'ket';
            ket.textContent = p.deskripsi;
            teks.appendChild(pemisah());
            teks.appendChild(ket);
        }

        if (tampilHarga) {
            var harga = document.createElement('span');
            harga.className = 'harga';
            harga.textContent = p.harga_teks;
            harga.title = p.harga_teks + (p.harga_member_teks ? ' · member ' + p.harga_member_teks : '');
            teks.appendChild(pemisah());
            teks.appendChild(harga);
        }

        el.appendChild(foto);
        el.appendChild(teks);
        return el;
    }

    /** Garis pemisah antar label di dalam kartu menu. */
    function pemisah() {
        var s = document.createElement('span');
        s.className = 'pemisah';
        return s;
    }

    function gambarMenu(paksa) {
        var stampBaru = menuData.stamp || '';
        if (!paksa && stampBaru === stampMenu && track.childElementCount) { return; }
        track.innerHTML = '';
        (menuData.produk || []).forEach(function (p) { track.appendChild(kartuMenu(p)); });
        document.getElementById('menu-kosong').hidden = (menuData.produk || []).length > 0;
        stampMenu = stampBaru;
        mulaiGulirMenu();
    }

    function mulaiGulirMenu() {
        if (animasiMenu) { animasiMenu.cancel(); animasiMenu = null; }
        track.style.transform = '';
        var asli = track.scrollHeight;
        var kotak = scrollBox.clientHeight;
        /* Salinan kedua hanya bila isi melebihi kotak. */
        track.querySelectorAll('[data-salinan]').forEach(function (n) { n.remove(); });
        if (asli <= kotak + 6) { return; }
        var salinan = document.createElement('div');
        salinan.setAttribute('data-salinan', '1');
        salinan.style.display = 'contents';
        Array.prototype.slice.call(track.children).forEach(function (n) {
            salinan.appendChild(n.cloneNode(true));
        });
        track.appendChild(salinan);
        var jarak = salinan.firstElementChild ? (salinan.firstElementChild.offsetTop - track.firstElementChild.offsetTop) : 0;
        if (jarak <= 0) { return; }
        var durasi = Math.max(18000, (jarak / kecepatan) * 1000);
        animasiMenu = track.animate(
            [{ transform: 'translateY(0px)' }, { transform: 'translateY(-' + jarak + 'px)' }],
            { duration: durasi, iterations: Infinity, easing: 'linear' }
        );
    }

    /* ---------------- Panel pesanan siap ---------------- */
    function gambarSiap(daftar) {
        if (!siapList) { return; }
        var kunci = JSON.stringify(daftar.map(function (s) { return s.kode + ':' + s.status; }));
        if (kunci === stampSiap) { return; }
        stampSiap = kunci;
        siapList.innerHTML = '';
        if (!daftar.length) {
            var ksg = document.createElement('span');
            ksg.className = 'kosong';
            ksg.textContent = 'Belum ada pesanan yang siap disajikan.';
            siapList.appendChild(ksg);
            if (animasiSiap) { animasiSiap.cancel(); animasiSiap = null; }
            return;
        }
        daftar.forEach(function (s) {
            var chip = document.createElement('div');
            chip.className = 'display-siap-chip';
            var kode = document.createElement('strong');
            kode.textContent = s.meja;
            var ket = document.createElement('span');
            ket.textContent = s.kode + ' · ' + (s.status === 'SELESAI' ? 'selesai' : 'siap');
            chip.appendChild(kode);
            chip.appendChild(ket);
            siapList.appendChild(chip);
        });
        mulaiGulirSiap();
    }

    function mulaiGulirSiap() {
        if (animasiSiap) { animasiSiap.cancel(); animasiSiap = null; }
        siapList.style.transform = '';
        siapList.querySelectorAll('[data-salinan]').forEach(function (n) { n.remove(); });
        var asli = siapList.scrollWidth;
        var kotak = siapList.clientWidth;
        if (asli <= kotak + 4) { return; }
        var salinan = document.createElement('span');
        salinan.setAttribute('data-salinan', '1');
        salinan.style.display = 'contents';
        Array.prototype.slice.call(siapList.children).forEach(function (n) {
            salinan.appendChild(n.cloneNode(true));
        });
        siapList.appendChild(salinan);
        var jarak = salinan.firstElementChild ? (salinan.firstElementChild.offsetLeft - siapList.firstElementChild.offsetLeft) : 0;
        if (jarak <= 0) { return; }
        var durasi = Math.max(14000, (jarak / 40) * 1000);
        animasiSiap = siapList.animate(
            [{ transform: 'translateX(0px)' }, { transform: 'translateX(-' + jarak + 'px)' }],
            { duration: durasi, iterations: Infinity, easing: 'linear' }
        );
    }

    /* ---------------- Footer berjalan ---------------- */
    function mulaiMarquee() {
        var track = document.getElementById('marquee');
        if (!track) { return; }
        var lebar = track.scrollWidth / 2;
        if (lebar <= 0) { return; }
        track.animate(
            [{ transform: 'translateX(0px)' }, { transform: 'translateX(-' + lebar + 'px)' }],
            { duration: Math.max(16000, (lebar / 70) * 1000), iterations: Infinity, easing: 'linear' }
        );
    }

    /* ---------------- Layar penuh ---------------- */
    function cobaLayarPenuh() {
        var fs = new URLSearchParams(location.search).get('fs');
        if (fs !== '1') { return; }
        var coba = function () {
            var el = document.documentElement;
            var fn = el.requestFullscreen || el.webkitRequestFullscreen;
            if (!fn) { return; }
            try {
                var r = fn.call(el);
                if (r && r.catch) { r.catch(function () { /* ditolak: tunggu interaksi */ }); }
            } catch (e) { /* diabaikan */ }
        };
        coba();
        document.addEventListener('click', coba, { once: true });
    }

    document.getElementById('btn-layar-penuh').addEventListener('click', function () {
        var el = document.documentElement;
        if (document.fullscreenElement) {
            if (document.exitFullscreen) { document.exitFullscreen(); }
            return;
        }
        var fn = el.requestFullscreen || el.webkitRequestFullscreen;
        if (fn) { fn.call(el); }
    });

    /* ---------------- Pemantauan perubahan ---------------- */
    function muatMenu() {
        fetch('api.php?action=menu', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { return; }
                setJamAcuan(d.jam);
                if (window.__cekVersiAset) { window.__cekVersiAset(d.asset_version); }
                var berubah = (d.stamp || '') !== (menuData.stamp || '');
                menuData = d;
                kecepatan = parseFloat(d.kecepatan || '38') || 38;
                tampilHarga = d.tampil_harga !== 0;
                if (berubah) { gambarMenu(true); }
                gambarSiap(d.siap || []);
            })
            .catch(function () { /* coba lagi pada denyut berikutnya */ });
    }

    function pantau() {
        var stampTerakhir = '';
        setInterval(function () {
            fetch('api.php?action=menu_cek', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { return; }
                    setJamAcuan(d.jam);
                    if (window.__cekVersiAset) { window.__cekVersiAset(d.asset_version); }
                    if (stampTerakhir === '') { stampTerakhir = d.stamp; return; }
                    if (d.stamp !== stampTerakhir || d.stamp_siap !== stampTerakhir) {
                        stampTerakhir = d.stamp;
                        muatMenu();
                    }
                })
                .catch(function () { });
        }, 5000);
    }

    /* ---------------- Mulai ---------------- */
    setJamAcuan(menuData.jam);
    gambarMenu(true);
    gambarSiap(siap);
    mulaiMarquee();
    cobaLayarPenuh();
    setInterval(jamTick, 10000);
    pantau();

    window.addEventListener('resize', function () {
        mulaiGulirMenu();
        mulaiGulirSiap();
    });

    /* Muat ulang SEKALI bila CSS/JS di server sudah diperbarui.
       Layar TV tidak pernah di-refresh manual, jadi tanpa ini gaya lama bisa bertahan selamanya.
       Penanda ?v_aset= mencegah muat ulang berulang bila HTML-nya sendiri datang dari cache. */
    var versiHalaman = parseInt(window.ASSET_VERSI || '0', 10);
    function cekVersiAset(versiServer) {
        if (!versiServer || !versiHalaman || parseInt(versiServer, 10) === versiHalaman) { return; }
        var sudah = new URLSearchParams(location.search).get('v_aset');
        if (sudah === String(versiServer)) { return; }
        var url = new URL(location.href);
        url.searchParams.set('v_aset', String(versiServer));
        location.replace(url.toString());
    }
    window.__cekVersiAset = cekVersiAset;
})();
