/*
 * Papan dapur: menampilkan pesanan hari ini per status dan mengirim perubahan status
 * (pesanan diterima → sedang disiapkan → siap disajikan → selesai) ke server.
 */
(function () {
    'use strict';

    var grid = document.getElementById('dapur-grid');
    var wadahSelesai = document.getElementById('dapur-selesai');
    if (!grid) { return; }

    var KOLOM = [
        { status: 'BARU', judul: 'Pesanan Masuk' },
        { status: 'DITERIMA', judul: 'Pesanan Diterima' },
        { status: 'DISIAPKAN', judul: 'Sedang Disiapkan' },
        { status: 'SIAP', judul: 'Siap Disajikan' }
    ];
    var AKSI = {
        BARU: 'Terima Pesanan',
        DITERIMA: 'Mulai Disiapkan',
        DISIAPKAN: 'Siap Disajikan',
        SIAP: 'Selesai — Sajikan'
    };

    var data = JSON.parse((document.getElementById('data-dapur') || {}).textContent || '{}');
    var stamp = data.stamp || '';
    var sibuk = {};

    function rpNama(p) {
        return p.kode + ' · ' + p.meja;
    }

    function kartu(p) {
        var el = document.createElement('article');
        el.className = 'dapur-tiket' + (p.status === 'BARU' ? ' is-baru' : '');

        var head = document.createElement('header');
        var kiri = document.createElement('strong');
        kiri.textContent = p.kode;
        var kanan = document.createElement('span');
        kanan.className = 'meja-teks';
        kanan.textContent = p.meja;
        head.appendChild(kiri);
        head.appendChild(kanan);
        el.appendChild(head);

        var meta = document.createElement('div');
        meta.className = 'dapur-waktu';
        meta.textContent = p.jam + ' · ' + p.sumber_label + (p.nama_pelanggan ? ' · ' + p.nama_pelanggan : '');
        el.appendChild(meta);

        var bayar = document.createElement('div');
        bayar.className = 'pil ' + (p.status_bayar === 'LUNAS' ? 'pil-ok' : 'pil-err');
        bayar.textContent = (p.status_bayar === 'LUNAS' ? 'Sudah dibayar' : 'Belum dibayar') + ' · ' + p.metode_label;
        el.appendChild(bayar);

        var daftar = document.createElement('div');
        daftar.style.display = 'flex';
        daftar.style.flexDirection = 'column';
        daftar.style.gap = '4px';
        (p.items || []).forEach(function (it) {
            var b = document.createElement('div');
            b.className = 'dapur-item';
            var q = document.createElement('span');
            q.className = 'qty';
            q.textContent = it.qty_teks + '×';
            var n = document.createElement('span');
            n.textContent = it.nama;
            b.appendChild(q);
            b.appendChild(n);
            if (it.catatan) {
                var k = document.createElement('span');
                k.className = 'ket';
                k.textContent = '“' + it.catatan + '”';
                n.appendChild(k);
            }
            daftar.appendChild(b);
        });
        el.appendChild(daftar);

        if (p.catatan) {
            var cat = document.createElement('div');
            cat.className = 'dapur-waktu';
            cat.textContent = 'Catatan: ' + p.catatan;
            el.appendChild(cat);
        }

        var aksi = document.createElement('div');
        aksi.className = 'aksi';

        if (AKSI[p.status]) {
            var maju = document.createElement('button');
            maju.type = 'button';
            maju.className = 'tombol tombol-kecil tombol-utama';
            maju.textContent = AKSI[p.status];
            maju.disabled = !!sibuk[p.id];
            maju.addEventListener('click', function () { ubah(p.id, 'maju'); });
            aksi.appendChild(maju);
        }
        var mundur = document.createElement('button');
        mundur.type = 'button';
        mundur.className = 'tombol tombol-kecil';
        mundur.textContent = '↩ Kembalikan';
        mundur.disabled = !!sibuk[p.id];
        mundur.addEventListener('click', function () { ubah(p.id, 'mundur'); });
        aksi.appendChild(mundur);

        el.appendChild(aksi);
        return el;
    }

    function gambar() {
        grid.innerHTML = '';
        KOLOM.forEach(function (k) {
            var daftar = (data.kolom && data.kolom[k.status]) || [];
            var kolom = document.createElement('section');
            kolom.className = 'dapur-kolom';
            var head = document.createElement('header');
            var judul = document.createElement('strong');
            judul.textContent = k.judul;
            var jml = document.createElement('span');
            jml.className = 'jumlah';
            jml.textContent = daftar.length;
            head.appendChild(judul);
            head.appendChild(jml);
            kolom.appendChild(head);

            if (!daftar.length) {
                var ksg = document.createElement('p');
                ksg.className = 'kosong-teks';
                ksg.style.padding = '12px 4px';
                ksg.textContent = '—';
                kolom.appendChild(ksg);
            }
            daftar.forEach(function (p) { kolom.appendChild(kartu(p)); });
            grid.appendChild(kolom);
        });

        var selesai = data.selesai || [];
        document.getElementById('jumlah-selesai').textContent = selesai.length;
        wadahSelesai.innerHTML = '';
        if (!selesai.length) {
            var ksg2 = document.createElement('p');
            ksg2.className = 'kosong-teks';
            ksg2.textContent = 'Belum ada pesanan yang selesai hari ini.';
            wadahSelesai.appendChild(ksg2);
        }
        selesai.forEach(function (p) {
            var el = document.createElement('article');
            el.className = 'dapur-tiket';
            el.innerHTML = '';
            var head = document.createElement('header');
            var kiri = document.createElement('strong');
            kiri.textContent = p.kode;
            var kanan = document.createElement('span');
            kanan.className = 'meja-teks';
            kanan.textContent = p.meja;
            head.appendChild(kiri);
            head.appendChild(kanan);
            el.appendChild(head);
            var w = document.createElement('div');
            w.className = 'dapur-waktu';
            w.textContent = 'Selesai ' + (p.siap_at || '') + ' · ' + p.total_teks;
            el.appendChild(w);
            wadahSelesai.appendChild(el);
        });

        var baru = ((data.kolom && data.kolom.BARU) || []).length;
        document.getElementById('info-baru').textContent = baru + ' pesanan baru';
    }

    function ubah(id, arah) {
        sibuk[id] = 1;
        fetch('api.php?action=dapur_status', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, arah: arah })
        }).then(function (r) { return r.json(); })
            .then(function (d) {
                delete sibuk[id];
                if (!d.ok) { window.kabar(d.error || 'Gagal memperbarui status.', 'err'); return; }
                stamp = d.stamp || '';
                muat(true);
            })
            .catch(function () {
                delete sibuk[id];
                window.kabar('Koneksi terputus — status belum tersimpan.', 'err');
            });
    }

    function muat(paksa) {
        return fetch('api.php?action=dapur', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { return; }
                if (paksa !== true && d.stamp === stamp) { return; }
                stamp = d.stamp || '';
                data = d;
                gambar();
                document.getElementById('status-hubung').textContent = 'Terhubung ' + (d.jam || '');
                document.getElementById('status-hubung').className = 'pil pil-ok';
            })
            .catch(function () {
                var el = document.getElementById('status-hubung');
                el.textContent = 'Koneksi bermasalah — mencoba lagi…';
                el.className = 'pil pil-err';
            });
    }

    gambar();
    document.getElementById('status-hubung').textContent = 'Terhubung ' + (data.jam || '');
    document.getElementById('status-hubung').className = 'pil pil-ok';

    setInterval(function () { muat(false); }, 3000);
})();
