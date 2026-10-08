/*
 * Skrip umum aplikasi kasir kafe: notifikasi, jam, kalender tanggal ringan,
 * konfirmasi tombol, tab pengaturan, dan pembantu keranjang.
 */
(function () {
    'use strict';

    /* ---------------- Notifikasi kecil ---------------- */
    window.kabar = function (pesan, jenis, lamaMs) {
        var wrap = document.getElementById('toast-wrap');
        if (!wrap) { window.alert(pesan); return; }
        var div = document.createElement('div');
        div.className = 'toast ' + (jenis === 'err' ? 'toast-err' : (jenis === 'info' ? 'toast-info' : 'toast-ok'));
        div.textContent = pesan;
        wrap.appendChild(div);
        setTimeout(function () { div.classList.add('is-hilang'); }, lamaMs || 4000);
        setTimeout(function () { div.remove(); }, (lamaMs || 4000) + 600);
    };

    /* ---------------- Jam berjalan ---------------- */
    function jamJalan() {
        var el = document.getElementById('jam-app');
        if (!el) { return; }
        var dasar = new Date(el.textContent.replace(/(\d{2})\/(\d{2})\/(\d{4})/, '$3-$2-$1').replace(' ', 'T'));
        var mulai = Date.now();
        if (isNaN(dasar.getTime())) { return; }
        setInterval(function () {
            var d = new Date(dasar.getTime() + (Date.now() - mulai));
            var p = function (n) { return n < 10 ? '0' + n : '' + n; };
            el.textContent = p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
        }, 15000);
    }

    /* ---------------- Kalender tanggal (dd/mm/yyyy) ---------------- */
    var NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    function pecahTanggal(teks) {
        var m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec((teks || '').trim());
        if (!m) { return null; }
        var d = parseInt(m[1], 10), b = parseInt(m[2], 10), t = parseInt(m[3], 10);
        var uji = new Date(t, b - 1, d);
        if (uji.getFullYear() !== t || uji.getMonth() !== b - 1 || uji.getDate() !== d) { return null; }
        return { d: d, b: b, t: t };
    }

    function teksTanggal(d, b, t) {
        var p = function (n) { return n < 10 ? '0' + n : '' + n; };
        return p(d) + '/' + p(b) + '/' + t;
    }

    function pasangTanggal(input) {
        if (input.dataset.tglSiap === '1') { return; }
        input.dataset.tglSiap = '1';
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('inputmode', 'numeric');
        input.placeholder = input.placeholder || 'dd/mm/yyyy';

        var bungkus = input.parentNode;
        if (!bungkus.classList.contains('tgl-input-bungkus')) {
            var b = document.createElement('div');
            b.className = 'tgl-input-bungkus';
            bungkus.insertBefore(b, input);
            b.appendChild(input);
            bungkus = b;
        }

        var kalender = null;

        function tutup() {
            if (kalender) { kalender.remove(); kalender = null; }
        }

        function posisi() {
            if (!kalender) { return; }
            var r = input.getBoundingClientRect();
            kalender.style.left = '0px';
            kalender.style.top = (input.offsetHeight + 4) + 'px';
            var lebar = kalender.offsetWidth || 262;
            var ruang = (bungkus.clientWidth || 260) - lebar;
            if (ruang < 0) { kalender.style.left = ruang + 'px'; }
        }

        function gambar(lihatT, lihatB) {
            var kini = pecahTanggal(input.value) || (function () {
                var n = new Date();
                return { d: n.getDate(), b: n.getMonth() + 1, t: n.getFullYear() };
            })();
            var html = '<header><button type="button" data-geser="-1">&#8592;</button><strong>' +
                NAMA_BULAN[lihatB - 1] + ' ' + lihatT + '</strong><button type="button" data-geser="1">&#8594;</button></header>' +
                '<div class="grid-hari"><span>M</span><span>S</span><span>S</span><span>R</span><span>K</span><span>J</span><span>S</span></div>';
            var pertama = new Date(lihatT, lihatB - 1, 1);
            var geser = (pertama.getDay() + 6) % 7;               /* Senin = 0 */
            var jumlah = new Date(lihatT, lihatB, 0).getDate();
            html += '<div class="grid-hari">';
            for (var i = 0; i < geser; i++) { html += '<button type="button" disabled class="is-luar"></button>'; }
            for (var d = 1; d <= jumlah; d++) {
                var kelas = (d === kini.d && lihatB === kini.b && lihatT === kini.t) ? ' class="is-kini"' : '';
                html += '<button type="button" data-hari="' + d + '"' + kelas + '>' + d + '</button>';
            }
            html += '</div>';
            kalender.innerHTML = html;
            posisi();
        }

        function buka() {
            tutup();
            kalender = document.createElement('div');
            kalender.className = 'tgl-kalender';
            bungkus.appendChild(kalender);
            var t = pecahTanggal(input.value);
            gambar(t ? t.t : new Date().getFullYear(), t ? t.b : new Date().getMonth() + 1);
        }

        input.addEventListener('focus', buka);
        input.addEventListener('click', buka);
        input.addEventListener('input', function () {
            var t = pecahTanggal(input.value);
            if (t && kalender) { gambar(t.t, t.b); }
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { tutup(); }
        });
        document.addEventListener('click', function (e) {
            if (!kalender) { return; }
            if (kalender.contains(e.target) || e.target === input) { return; }
            tutup();
        });
        /* Kalender ikut berpindah saat halaman digulir (jangan ditutup — dulu ini membuat
           pengujian tampak gagal karena kalender hilang sebelum ditekan). */
        function ikuti() { posisi(); }
        window.addEventListener('scroll', ikuti, true);
        window.addEventListener('resize', ikuti);

        bungkus.addEventListener('click', function (e) {
            var tombol = e.target.closest('button');
            if (!tombol || !kalender) { return; }
            if (tombol.dataset.geser) {
                var t = pecahTanggal(input.value) || { t: new Date().getFullYear(), b: new Date().getMonth() + 1, d: 1 };
                var b = t.b + parseInt(tombol.dataset.geser, 10);
                var th = t.t;
                if (b < 1) { b = 12; th--; }
                if (b > 12) { b = 1; th++; }
                gambar(th, b);
                return;
            }
            if (tombol.dataset.hari) {
                var head = kalender.querySelector('header strong').textContent.split(' ');
                var tahun = parseInt(head[head.length - 1], 10);
                var bulan = NAMA_BULAN.indexOf(head.slice(0, -1).join(' ')) + 1;
                input.value = teksTanggal(parseInt(tombol.dataset.hari, 10), bulan, tahun);
                input.dispatchEvent(new Event('change', { bubbles: true }));
                tutup();
            }
        });
    }

    window.pasangTanggal = pasangTanggal;

    /* ---------------- Konfirmasi tombol ---------------- */
    function pasangKonfirmasi(akar) {
        (akar || document).querySelectorAll('[data-konfirmasi]').forEach(function (el) {
            if (el.dataset.konfirmasiSiap === '1') { return; }
            el.dataset.konfirmasiSiap = '1';
            el.addEventListener('click', function (e) {
                if (!window.confirm(el.dataset.konfirmasi)) { e.preventDefault(); }
            });
        });
    }

    /* ---------------- Tab ---------------- */
    function pasangTab() {
        var bar = document.querySelector('.tab-bar');
        if (!bar) { return; }
        var panel = document.querySelectorAll('.tab-panel');
        function pilih(nama) {
            bar.querySelectorAll('a').forEach(function (a) { a.classList.toggle('is-aktif', a.dataset.tab === nama); });
            panel.forEach(function (p) { p.classList.toggle('is-aktif', p.dataset.panel === nama); });
            var input = document.getElementById('tab-aktif');
            if (input) { input.value = nama; }
        }
        bar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                pilih(a.dataset.tab);
                history.replaceState(null, '', '#' + a.dataset.tab);
            });
        });
        var awal = (location.hash || '').replace('#', '');
        if (!awal || !bar.querySelector('[data-tab="' + awal + '"]')) {
            /* Tab yang sudah dipilih server (mis. setelah redirect simpan setelan) dihormati lebih dulu. */
            var aktif = bar.querySelector('a.is-aktif');
            var penanda = bar.querySelector('[data-tab-awal]');
            var pertama = bar.querySelector('a');
            awal = aktif ? aktif.dataset.tab : (penanda ? penanda.dataset.tabAwal : (pertama ? pertama.dataset.tab : ''));
        }
        pilih(awal);
    }

    /* ---------------- Saring tabel cepat ---------------- */
    function pasangSaring() {
        document.querySelectorAll('[data-saring]').forEach(function (input) {
            var target = document.querySelector(input.dataset.saring);
            if (!target) { return; }
            input.addEventListener('input', function () {
                var q = input.value.toLowerCase().trim();
                target.querySelectorAll('tbody tr').forEach(function (tr) {
                    tr.hidden = q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1;
                });
            });
        });
    }

    /* ---------------- Tombol layar penuh mengambang ---------------- */
    /*
     * Markup tombol berasal dari tombol_layar_penuh() (lib.php) sehingga seluruh halaman
     * menu/form memakai tombol yang sama (sama seperti pada display menu).
     */
    function pasangLayarPenuh() {
        var tombol = document.getElementById('btn-penuh');
        if (!tombol || tombol.dataset.penuhSiap === '1') { return; }
        tombol.dataset.penuhSiap = '1';

        function elemenPenuh() {
            return document.fullscreenElement || document.webkitFullscreenElement || null;
        }

        function sinkron() {
            var aktif = !!elemenPenuh();
            tombol.classList.toggle('is-aktif', aktif);
            tombol.setAttribute('aria-pressed', aktif ? 'true' : 'false');
            var label = aktif ? 'Keluar dari layar penuh' : 'Layar penuh';
            tombol.title = label;
            tombol.setAttribute('aria-label', label);
        }

        tombol.addEventListener('click', function () {
            var dokumen = document;
            var el = document.documentElement;
            try {
                if (elemenPenuh()) {
                    var keluar = dokumen.exitFullscreen || dokumen.webkitExitFullscreen;
                    if (keluar) { keluar.call(dokumen); }
                } else {
                    var masuk = el.requestFullscreen || el.webkitRequestFullscreen;
                    if (!masuk) {
                        window.kabar('Browser ini tidak mendukung mode layar penuh.', 'info');
                    } else {
                        var hasil = masuk.call(el);
                        if (hasil && hasil.catch) {
                            hasil.catch(function () {
                                window.kabar('Layar penuh ditolak browser. Coba tekan tombolnya sekali lagi.', 'err');
                            });
                        }
                    }
                }
            } catch (e) {
                window.kabar('Gagal mengubah mode layar penuh: ' + e.message, 'err');
            }
            /* Status sesungguhnya dipastikan lewat event fullscreenchange (atau jeda pendek). */
            setTimeout(sinkron, 150);
        });

        document.addEventListener('fullscreenchange', sinkron);
        document.addEventListener('webkitfullscreenchange', sinkron);
        sinkron();
    }

    /* ---------------- Kirim otomatis (select) ---------------- */
    function pasangKirim() {
        document.querySelectorAll('[data-kirim]').forEach(function (el) {
            if (el.dataset.kirimSiap === '1') { return; }
            el.dataset.kirimSiap = '1';
            el.addEventListener('change', function () {
                var form = el.form;
                if (!form) { return; }
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
            });
        });
    }

    window.pasangUlang = function (akar) {
        (akar || document).querySelectorAll('input.tgl').forEach(pasangTanggal);
        pasangKonfirmasi(akar || document);
        pasangKirim();
        pasangSaring();
        pasangLayarPenuh();
    };

    document.addEventListener('DOMContentLoaded', function () {
        jamJalan();
        pasangTab();
        window.pasangUlang(document);
    });
})();
