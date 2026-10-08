/*
 * Skrip halaman pelanggan (pesan.php):
 *  1) mode pesan   — pilih menu, isi data, pilih metode bayar, kirim ke kasir.
 *  2) mode lacak   — memantau status pesanan + notifikasi + struk.
 */
(function () {
    'use strict';

    var fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    function rp(n) { return 'Rp ' + fmt.format(Math.round(n)); }

    /* ==================================================================
       Mode 1: memesan
       ================================================================== */
    var app = document.getElementById('pesan-app');
    if (app) {
        var menuData = JSON.parse((document.getElementById('data-menu') || {}).textContent || '{}');
        var daftar = menuData.produk || [];
        var keranjang = {};
        var kategoriAktif = 0;
        var metode = (document.querySelector('.pembayaran-opsi.is-aktif') || { dataset: { metode: 'TUNAI' } }).dataset.metode;

        function kartu(p) {
            var el = document.createElement('article');
            el.className = 'pesan-kartu';

            var foto = document.createElement('div');
            foto.className = 'foto';
            if (p.foto) {
                var img = document.createElement('img');
                img.src = p.foto;
                img.alt = p.nama;
                img.loading = 'lazy';
                foto.appendChild(img);
            } else {
                var mono = document.createElement('span');
                mono.className = 'monogram';
                mono.style.fontWeight = '800';
                mono.style.color = 'rgba(122,74,42,.55)';
                mono.textContent = (p.nama || '?').substring(0, 1).toUpperCase();
                foto.appendChild(mono);
            }

            var teks = document.createElement('span');
            teks.className = 'teks';
            var nama = document.createElement('div');
            nama.className = 'nama';
            nama.textContent = p.nama;
            var harga = document.createElement('div');
            harga.className = 'harga';
            harga.textContent = p.harga_teks + (p.favorit ? ' ★' : '');
            teks.appendChild(nama);
            if (p.deskripsi) {
                var ket = document.createElement('div');
                ket.className = 'ket';
                ket.textContent = p.deskripsi;
                teks.appendChild(ket);
            }
            teks.appendChild(harga);

            var atur = document.createElement('div');
            atur.className = 'atur-qty';
            var kurang = document.createElement('button');
            kurang.type = 'button';
            kurang.textContent = '−';
            kurang.setAttribute('aria-label', 'Kurangi');
            var nilai = document.createElement('span');
            nilai.className = 'nilai';
            nilai.textContent = (keranjang[p.id] || 0);
            var tambah = document.createElement('button');
            tambah.type = 'button';
            tambah.textContent = '+';
            tambah.setAttribute('aria-label', 'Tambah');
            kurang.addEventListener('click', function () { ubah(p.id, -1); });
            tambah.addEventListener('click', function () { ubah(p.id, 1); });
            atur.appendChild(kurang);
            atur.appendChild(nilai);
            atur.appendChild(tambah);

            el.appendChild(foto);
            el.appendChild(teks);
            el.appendChild(atur);
            return el;
        }

        function gambarMenu() {
            var wadah = document.getElementById('menu-daftar');
            var daftarTampil = daftar.filter(function (p) {
                return kategoriAktif === 0 || p.kategori_id === kategoriAktif;
            });
            wadah.innerHTML = '';
            document.getElementById('menu-kosong').hidden = daftarTampil.length > 0;
            daftarTampil.forEach(function (p) { wadah.appendChild(kartu(p)); });
        }

        function ubah(id, delta) {
            keranjang[id] = (keranjang[id] || 0) + delta;
            if (keranjang[id] <= 0) { delete keranjang[id]; }
            gambarMenu();
            gambarBar();
        }

        function hitung() {
            var total = 0, jumlah = 0, rincian = [];
            Object.keys(keranjang).forEach(function (id) {
                var p = daftar.filter(function (x) { return String(x.id) === String(id); })[0];
                if (!p) { return; }
                var q = keranjang[id];
                total += p.harga * q;
                jumlah += q;
                rincian.push({ nama: p.nama, qty: q, subtotal: p.harga * q });
            });
            return { total: total, jumlah: jumlah, rincian: rincian };
        }

        function gambarBar() {
            var h = hitung();
            var bar = document.getElementById('pesan-bar');
            bar.hidden = h.jumlah === 0;
            document.getElementById('bar-item').textContent = h.jumlah + ' item dipilih';
            document.getElementById('bar-total').textContent = rp(h.total);
        }

        function gambarCheckout() {
            var h = hitung();
            var wadah = document.getElementById('ringkasan-keranjang');
            wadah.innerHTML = '';
            h.rincian.forEach(function (r) {
                var baris = document.createElement('div');
                baris.className = 'info-baris';
                var kiri = document.createElement('span');
                kiri.textContent = r.qty + '× ' + r.nama;
                var kanan = document.createElement('strong');
                kanan.textContent = rp(r.subtotal);
                baris.appendChild(kiri);
                baris.appendChild(kanan);
                wadah.appendChild(baris);
            });
            var total = document.createElement('div');
            total.className = 'info-baris';
            total.innerHTML = '<span><strong>Total</strong></span><strong>' + rp(h.total) + '</strong>';
            wadah.appendChild(total);
        }

        document.getElementById('kategori-bar').addEventListener('click', function (e) {
            var b = e.target.closest('.kategori-chip');
            if (!b) { return; }
            kategoriAktif = parseInt(b.dataset.kategori, 10) || 0;
            document.querySelectorAll('#kategori-bar .kategori-chip').forEach(function (x) {
                x.classList.toggle('is-aktif', x === b);
            });
            gambarMenu();
        });

        document.getElementById('btn-lanjut').addEventListener('click', function () {
            if (hitung().jumlah === 0) { return; }
            gambarCheckout();
            document.getElementById('bagian-checkout').hidden = false;
            document.getElementById('bagian-checkout').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        document.getElementById('btn-batal').addEventListener('click', function () {
            document.getElementById('bagian-checkout').hidden = true;
        });

        document.querySelectorAll('.pembayaran-opsi').forEach(function (b) {
            b.addEventListener('click', function () {
                metode = b.dataset.metode;
                document.querySelectorAll('.pembayaran-opsi').forEach(function (x) { x.classList.toggle('is-aktif', x === b); });
                document.getElementById('petunjuk-bayar').textContent = metode === 'QRIS'
                    ? 'Pindai QRIS di kasir setelah pesanan dikirim.'
                    : 'Bayar tunai ke kasir setelah pesanan tercatat.';
            });
        });

        document.getElementById('btn-kirim').addEventListener('click', function () {
            var nama = document.getElementById('nama').value.trim();
            var telp = document.getElementById('telepon').value.trim();
            var h = hitung();
            if (h.jumlah === 0) { window.kabar('Belum ada menu yang dipilih.', 'err'); return; }
            if (nama === '') { window.kabar('Mohon isi nama Anda.', 'err'); return; }
            if (!/^[0-9+\-\s]{8,20}$/.test(telp)) { window.kabar('Nomor HP belum benar (contoh: 081234567890).', 'err'); return; }

            var tombol = document.getElementById('btn-kirim');
            tombol.disabled = true;
            fetch('api.php?action=pesan_simpan', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    meja_id: app.dataset.meja,
                    meja_token: app.dataset.token,
                    nama: nama,
                    telepon: telp,
                    catatan: document.getElementById('catatan').value.trim(),
                    member: document.getElementById('member').checked,
                    metode_bayar: metode,
                    items: Object.keys(keranjang).map(function (id) {
                        return { produk_id: parseInt(id, 10), qty: keranjang[id] };
                    })
                })
            }).then(function (r) { return r.json(); }).then(function (d) {
                tombol.disabled = false;
                if (!d.ok) { window.kabar(d.error || 'Pesanan gagal dikirim.', 'err', 7000); return; }
                try {
                    localStorage.setItem('kasir_kafe_terakhir', JSON.stringify({ p: d.kode, k: d.token }));
                } catch (e) { /* localStorage bisa diblokir */ }
                location.href = 'pesan.php?p=' + encodeURIComponent(d.kode) + '&k=' + encodeURIComponent(d.token) + '&baru=1';
            }).catch(function () {
                tombol.disabled = false;
                window.kabar('Koneksi bermasalah — pesanan belum terkirim. Coba lagi.', 'err', 7000);
            });
        });

        gambarMenu();
        gambarBar();
    }

    /* ==================================================================
       Mode 2: lacak pesanan
       ================================================================== */
    var lacak = document.getElementById('lacak');
    if (lacak) {
        var kode = lacak.dataset.kode;
        var token = lacak.dataset.token;
        var statusKini = lacak.dataset.status;
        var dataPesanan = JSON.parse((document.getElementById('data-pesanan') || {}).textContent || '{}');
        var tadi = statusKini;

        function mintaIzinNotif() {
            if (!('Notification' in window)) {
                window.kabar('HP/browser ini tidak mendukung notifikasi.', 'info');
                return;
            }
            Notification.requestPermission().then(function (hasil) {
                window.kabar(hasil === 'granted' ? 'Notifikasi diaktifkan.' : 'Izin notifikasi tidak diberikan.', hasil === 'granted' ? 'ok' : 'err');
            });
        }
        document.getElementById('btn-notif').addEventListener('click', mintaIzinNotif);

        function kirimNotif(judul, isi) {
            try {
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(judul, { body: isi, icon: dataPesanan.logo || undefined });
                }
            } catch (e) { /* diabaikan */ }
        }

        function getar() {
            try { if (navigator.vibrate) { navigator.vibrate([120, 80, 120]); } } catch (e) { }
        }

        function popup(pesan) {
            window.kabar(pesan, 'ok', 9000);
        }

        function perbarui(d) {
            var p = d.pesanan;
            if (!p) { return; }
            dataPesanan = p;
            document.querySelectorAll('#langkah .langkah-item').forEach(function (el) {
                var pos = p.langkah.filter(function (l) { return l.status === el.dataset.status; })[0];
                el.classList.toggle('is-aktif', !!(pos && pos.aktif));
            });
            document.getElementById('status-teks').textContent = p.status === 'SELESAI'
                ? 'Pesanan selesai. Selamat menikmati!'
                : 'Status pesanan: ' + p.status_label;
            if (p.status !== tadi) {
                tadi = p.status;
                kirimNotif('Pesanan ' + p.kode, p.status_label);
                popup('Pesanan Anda: ' + p.status_label);
                getar();
                document.title = '(' + p.kode + ') ' + p.status_label;
                if (p.status_bayar === 'LUNAS' || p.status === 'SELESAI') {
                    /* struktur halaman berubah (bagian bayar → struk), muat ulang sekali */
                    location.reload();
                }
            }
            lacak.dataset.status = p.status;
        }

        function pantau() {
            var jeda = 4000;
            var hidup = true;
            document.addEventListener('visibilitychange', function () { hidup = !document.hidden; });
            var denyut = function () {
                if (!hidup) { setTimeout(denyut, jeda); return; }
                fetch('api.php?action=pesan_status&p=' + encodeURIComponent(kode) + '&k=' + encodeURIComponent(token), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.ok) { perbarui(d); }
                        if (d.ok && d.pesanan && d.pesanan.status === 'SELESAI') { return; }  /* berhenti memantau */
                        setTimeout(denyut, jeda);
                    })
                    .catch(function () { setTimeout(denyut, jeda * 2); });
            };
            setTimeout(denyut, jeda);
        }

        if (statusKini !== 'SELESAI') { pantau(); }
        var baru = new URLSearchParams(location.search).get('baru');
        if (baru === '1') {
            window.kabar('Pesanan terkirim ke kasir. Terima kasih!', 'ok', 7000);
            /* Ajakan mengaktifkan notifikasi sekali saja (audio/notif butuh interaksi pengguna). */
            if ('Notification' in window && Notification.permission === 'default') {
                setTimeout(function () { mintaIzinNotif(); }, 1500);
            }
        }
    }
})();
