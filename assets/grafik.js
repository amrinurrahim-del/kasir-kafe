/*
 * Grafik penjualan — dibuat sendiri dengan SVG (tanpa pustaka luar, tanpa internet).
 */
(function () {
    'use strict';

    var fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    function rp(n) { return 'Rp ' + fmt.format(Math.round(n)); }
    function rpPendek(n) {
        n = Math.round(n);
        if (n >= 1000000000) { return (n / 1000000000).toFixed(1).replace('.', ',') + ' M'; }
        if (n >= 1000000) { return (n / 1000000).toFixed(1).replace('.', ',') + ' jt'; }
        if (n >= 1000) { return (n / 1000).toFixed(0) + ' rb'; }
        return String(n);
    }

    function svgEl(nama, atr) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', nama);
        Object.keys(atr || {}).forEach(function (k) { el.setAttribute(k, atr[k]); });
        return el;
    }

    function kosong(wadah, pesan) {
        wadah.innerHTML = '<p class="kosong-teks">' + pesan + '</p>';
    }

    /** Grafik batang vertikal. */
    function batang(wadah, label, nilai, warna, opsi) {
        opsi = opsi || {};
        if (!nilai.length || Math.max.apply(null, nilai) <= 0) { kosong(wadah, 'Belum ada data untuk ditampilkan.'); return; }
        var W = 900, H = 320, kiri = 68, kanan = 16, atas = 18, bawah = 54;
        var lebarIsi = W - kiri - kanan;
        var tinggiIsi = H - atas - bawah;
        var maks = Math.max.apply(null, nilai);
        var svg = svgEl('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img' });

        /* garis bantu */
        for (var g = 0; g <= 4; g++) {
            var y = atas + tinggiIsi - (tinggiIsi * g / 4);
            svg.appendChild(svgEl('line', { x1: kiri, y1: y, x2: W - kanan, y2: y, stroke: '#e9d9c2', 'stroke-width': 1 }));
            var t = svgEl('text', { x: kiri - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 12, fill: '#6e5a48' });
            t.textContent = rpPendek(maks * g / 4);
            svg.appendChild(t);
        }

        var n = nilai.length;
        var lebar = Math.max(6, Math.min(46, lebarIsi / n - 8));
        nilai.forEach(function (v, i) {
            var x = kiri + (lebarIsi / n) * i + (lebarIsi / n - lebar) / 2;
            var h = maks > 0 ? (v / maks) * tinggiIsi : 0;
            var y = atas + tinggiIsi - h;
            var rect = svgEl('rect', { x: x, y: y, width: lebar, height: Math.max(1, h), rx: 5, fill: warna });
            var judul = svgEl('title');
            judul.textContent = label[i] + ': ' + rp(v) + (opsi.info && opsi.info[i] ? ' (' + opsi.info[i] + ')' : '');
            rect.appendChild(judul);
            svg.appendChild(rect);

            if (n <= 14) {
                var lt = svgEl('text', { x: x + lebar / 2, y: H - bawah + 18, 'text-anchor': 'middle', 'font-size': 11, fill: '#6e5a48' });
                lt.textContent = label[i];
                svg.appendChild(lt);
            }
        });
        if (n > 14) {
            var ket = svgEl('text', { x: kiri, y: H - bawah + 20, 'font-size': 11.5, fill: '#6e5a48' });
            ket.textContent = label[0] + '  →  ' + label[n - 1] + '  (' + n + ' titik)';
            svg.appendChild(ket);
        }
        wadah.innerHTML = '';
        wadah.appendChild(svg);
    }

    /** Grafik batang mendatar (untuk daftar nama panjang). */
    function batangMendatar(wadah, label, nilai, warna) {
        if (!nilai.length || Math.max.apply(null, nilai) <= 0) { kosong(wadah, 'Belum ada data untuk ditampilkan.'); return; }
        wadah.innerHTML = '';
        var maks = Math.max.apply(null, nilai);
        label.forEach(function (l, i) {
            var baris = document.createElement('div');
            baris.style.marginBottom = '9px';
            var head = document.createElement('div');
            head.style.display = 'flex';
            head.style.justifyContent = 'space-between';
            head.style.fontSize = '.84rem';
            var kiri = document.createElement('span');
            kiri.textContent = l;
            var kanan = document.createElement('strong');
            kanan.textContent = rp(nilai[i]);
            head.appendChild(kiri);
            head.appendChild(kanan);
            var jalur = document.createElement('div');
            jalur.style.height = '9px';
            jalur.style.borderRadius = '999px';
            jalur.style.background = 'var(--latte)';
            jalur.style.overflow = 'hidden';
            jalur.style.marginTop = '4px';
            var isi = document.createElement('div');
            isi.style.height = '100%';
            isi.style.width = (maks > 0 ? (nilai[i] / maks) * 100 : 0) + '%';
            isi.style.background = warna;
            isi.style.borderRadius = '999px';
            jalur.appendChild(isi);
            baris.appendChild(head);
            baris.appendChild(jalur);
            wadah.appendChild(baris);
        });
    }

    /** Grafik garis (omzet harian). */
    function garis(wadah, label, nilai, warna) {
        if (!nilai.length || Math.max.apply(null, nilai) <= 0) { kosong(wadah, 'Belum ada data untuk ditampilkan.'); return; }
        var W = 900, H = 300, kiri = 68, kanan = 16, atas = 18, bawah = 50;
        var lebarIsi = W - kiri - kanan, tinggiIsi = H - atas - bawah;
        var maks = Math.max.apply(null, nilai);
        var svg = svgEl('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img' });
        for (var g = 0; g <= 4; g++) {
            var y = atas + tinggiIsi - (tinggiIsi * g / 4);
            svg.appendChild(svgEl('line', { x1: kiri, y1: y, x2: W - kanan, y2: y, stroke: '#e9d9c2', 'stroke-width': 1 }));
            var t = svgEl('text', { x: kiri - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 12, fill: '#6e5a48' });
            t.textContent = rpPendek(maks * g / 4);
            svg.appendChild(t);
        }
        var n = nilai.length;
        var titik = nilai.map(function (v, i) {
            return {
                x: kiri + (n > 1 ? (lebarIsi / (n - 1)) * i : lebarIsi / 2),
                y: atas + tinggiIsi - (maks > 0 ? (v / maks) * tinggiIsi : 0)
            };
        });
        var d = titik.map(function (p, i) { return (i === 0 ? 'M' : 'L') + p.x.toFixed(1) + ' ' + p.y.toFixed(1); }).join(' ');
        svg.appendChild(svgEl('path', { d: d, fill: 'none', stroke: warna, 'stroke-width': 3, 'stroke-linejoin': 'round' }));
        titik.forEach(function (p, i) {
            var c = svgEl('circle', { cx: p.x, cy: p.y, r: 4.5, fill: '#fff', stroke: warna, 'stroke-width': 2.5 });
            var judul = svgEl('title');
            judul.textContent = label[i] + ': ' + rp(nilai[i]);
            c.appendChild(judul);
            svg.appendChild(c);
        });
        if (n <= 12) {
            titik.forEach(function (p, i) {
                var lt = svgEl('text', { x: p.x, y: H - bawah + 18, 'text-anchor': 'middle', 'font-size': 11, fill: '#6e5a48' });
                lt.textContent = label[i];
                svg.appendChild(lt);
            });
        } else {
            var ket = svgEl('text', { x: kiri, y: H - bawah + 20, 'font-size': 11.5, fill: '#6e5a48' });
            ket.textContent = label[0] + '  →  ' + label[n - 1];
            svg.appendChild(ket);
        }
        wadah.innerHTML = '';
        wadah.appendChild(svg);
    }

    /** Donat (per metode bayar). */
    function donat(wadah, label, nilai, warna) {
        var total = nilai.reduce(function (a, b) { return a + b; }, 0);
        if (total <= 0) { kosong(wadah, 'Belum ada data untuk ditampilkan.'); return; }
        var ukuran = 240;
        var pusat = ukuran / 2, radius = 92, tebal = 26;
        var svg = svgEl('svg', { viewBox: '0 0 ' + ukuran + ' ' + ukuran, role: 'img', style: 'max-width:240px;margin:0 auto;display:block' });
        var mulai = -Math.PI / 2;
        nilai.forEach(function (v, i) {
            var sudut = (v / total) * Math.PI * 2;
            var akhir = mulai + sudut;
            var x1 = pusat + radius * Math.cos(mulai), y1 = pusat + radius * Math.sin(mulai);
            var x2 = pusat + radius * Math.cos(akhir), y2 = pusat + radius * Math.sin(akhir);
            var besar = sudut > Math.PI ? 1 : 0;
            var d = 'M ' + x1 + ' ' + y1 + ' A ' + radius + ' ' + radius + ' 0 ' + besar + ' 1 ' + x2 + ' ' + y2;
            var busur = svgEl('path', { d: d, fill: 'none', stroke: warna[i % warna.length], 'stroke-width': tebal, 'stroke-linecap': 'butt' });
            var judul = svgEl('title');
            judul.textContent = label[i] + ': ' + rp(v) + ' (' + Math.round(v / total * 100) + '%)';
            busur.appendChild(judul);
            svg.appendChild(busur);
            mulai = akhir;
        });
        var t = svgEl('text', { x: pusat, y: pusat + 5, 'text-anchor': 'middle', 'font-size': 14, fill: '#241710', 'font-weight': 700 });
        t.textContent = rpPendek(total);
        svg.appendChild(t);
        wadah.innerHTML = '';
        wadah.appendChild(svg);
        var legenda = document.createElement('div');
        legenda.className = 'legend';
        legenda.style.justifyContent = 'center';
        label.forEach(function (l, i) {
            var s = document.createElement('span');
            s.innerHTML = '<i style="background:' + warna[i % warna.length] + '"></i>' + l + ' — ' + rp(nilai[i]);
            legenda.appendChild(s);
        });
        wadah.appendChild(legenda);
    }

    var WARNA = ['#b4763b', '#7a4a2a', '#d8a65c', '#2f9463', '#35607c', '#b03a2e', '#8c6b3f', '#4b6b45'];

    var data = JSON.parse((document.getElementById('data-grafik') || {}).textContent || '{}');

    var tanggal = Object.keys(data.harian || {});
    var labelHari = tanggal.map(function (t) {
        var p = t.split('-');
        return p[2] + '/' + p[1];
    });
    garis(document.getElementById('grafik-harian'), labelHari, tanggal.map(function (t) { return data.harian[t]; }), '#b4763b');
    garis(document.getElementById('grafik-transaksi'), labelHari, tanggal.map(function (t) { return (data.jumlah_harian || {})[t] || 0; }), '#2f9463');

    var labelJam = [];
    var nilaiJam = [];
    Object.keys(data.per_jam || {}).forEach(function (j) {
        labelJam.push(String(j).padStart(2, '0') + ':00');
        nilaiJam.push(data.per_jam[j]);
    });
    batang(document.getElementById('grafik-jam'), labelJam, nilaiJam, '#d8a65c');

    var metodeLabel = [];
    var metodeNilai = [];
    Object.keys(data.per_metode || {}).forEach(function (m) {
        metodeLabel.push(m === 'TUNAI' ? 'Tunai di Kasir' : (m === 'QRIS' ? 'QRIS' : m));
        metodeNilai.push(data.per_metode[m]);
    });
    donat(document.getElementById('grafik-metode'), metodeLabel, metodeNilai, WARNA);

    var katLabel = [], katNilai = [];
    (data.per_kategori || []).forEach(function (k) { katLabel.push(k.nama); katNilai.push(k.total); });
    batangMendatar(document.getElementById('grafik-kategori'), katLabel, katNilai, '#7a4a2a');

    var prodLabel = [], prodNilai = [];
    (data.top_produk || []).forEach(function (p) { prodLabel.push(p.nama + ' (' + p.qty + ')'); prodNilai.push(p.total); });
    batangMendatar(document.getElementById('grafik-produk'), prodLabel, prodNilai, '#b4763b');

    var mejaLabel = [], mejaNilai = [];
    Object.keys(data.per_meja || {}).forEach(function (m) { mejaLabel.push(m); mejaNilai.push(data.per_meja[m]); });
    batang(document.getElementById('grafik-meja'), mejaLabel, mejaNilai, '#2f9463');
})();
