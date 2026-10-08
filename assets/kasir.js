/*
 * Skrip halaman Akses Kasir:
 * katalog bergambar -> keranjang -> pemilihan meja/pelanggan/metode bayar -> simpan & bayar,
 * ditambah pemantauan pesanan masuk dari pelanggan (QR meja).
 */
(function () {
    'use strict';

    var app = document.getElementById('kasir-app');
    if (!app) { return; }

    var CFG = {
        qris: app.dataset.qris === '1',
        tunai: app.dataset.tunai === '1',
        pajak: parseFloat(app.dataset.pajak || '0') || 0,
        service: parseFloat(app.dataset.service || '0') || 0,
        diskonMember: parseFloat(app.dataset.diskonMember || '0') || 0,
        pembulatan: parseInt(app.dataset.pembulatan || '0', 10) || 0,
        qrisGambar: app.dataset.qrisGambar || '',
        qrisCatatan: app.dataset.qrisCatatan || ''
    };

    var produk = JSON.parse((document.getElementById('data-produk') || {}).textContent || '[]');
    var masuk = JSON.parse((document.getElementById('data-masuk') || {}).textContent || '[]');

    var keranjang = {};            /* id -> { produk, qty } */
    var pelangganTerpilih = null;  /* {id, nama, member} */
    var metode = 'TUNAI';
    var kategoriAktif = 0;
    var cari = '';
    var stampTerakhir = '';
    var bayarKontek = null;        /* {id, kode, total, metode} untuk modal pembayaran */

    var fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    function rp(n) { return 'Rp ' + fmt.format(Math.round(n)); }

    /* ---------------- Katalog ---------------- */

    function gambarProduk() {
        var wadah = document.getElementById('produk-grid');
        var kosong = document.getElementById('produk-kosong');
        var q = cari.toLowerCase();
        var daftar = produk.filter(function (p) {
            if (kategoriAktif > 0 && p.kategori_id !== kategoriAktif) { return false; }
            if (q !== '' && p.nama.toLowerCase().indexOf(q) === -1) { return false; }
            return true;
        });
        wadah.innerHTML = '';
        kosong.hidden = daftar.length > 0;
        daftar.forEach(function (p) {
            var kartu = document.createElement('button');
            kartu.type = 'button';
            kartu.className = 'produk-kartu';
            kartu.dataset.id = p.id;

            var foto = document.createElement('div');
            foto.className = 'produk-foto';
            if (p.foto) {
                var img = document.createElement('img');
                img.src = p.foto;
                img.alt = p.nama;
                img.loading = 'lazy';
                foto.appendChild(img);
            } else {
                var mono = document.createElement('span');
                mono.className = 'monogram';
                mono.textContent = (p.nama || '?').substring(0, 1).toUpperCase();
                foto.appendChild(mono);
            }
            if (p.favorit) {
                var tanda = document.createElement('span');
                tanda.className = 'pil pil-warn produk-tanda';
                tanda.textContent = 'Favorit';
                foto.appendChild(tanda);
            }

            var nama = document.createElement('span');
            nama.className = 'produk-nama';
            nama.textContent = p.nama;

            var harga = document.createElement('span');
            harga.className = 'produk-harga';
            harga.textContent = (pelangganTerpilih && pelangganTerpilih.member && p.harga_member > 0)
                ? p.harga_member_teks + ' · member'
                : p.harga_teks;

            var kat = document.createElement('span');
            kat.className = 'produk-kat';
            kat.textContent = p.kategori || '';

            kartu.appendChild(foto);
            kartu.appendChild(nama);
            kartu.appendChild(harga);
            kartu.appendChild(kat);
            kartu.addEventListener('click', function () { tambah(p.id); });
            wadah.appendChild(kartu);
        });
    }

    /* ---------------- Keranjang ---------------- */

    function hargaSatuan(p) {
        if (pelangganTerpilih && pelangganTerpilih.member && p.harga_member > 0) { return p.harga_member; }
        return p.harga;
    }

    function tambah(id, qty) {
        var p = produk.filter(function (x) { return x.id === id; })[0];
        if (!p) { return; }
        if (!keranjang[id]) { keranjang[id] = { produk: p, qty: 0 }; }
        keranjang[id].qty += (qty || 1);
        gambarKeranjang();
    }

    function ubahQty(id, qty) {
        if (!keranjang[id]) { return; }
        if (qty <= 0) { delete keranjang[id]; } else { keranjang[id].qty = qty; }
        gambarKeranjang();
    }

    function hitung() {
        var subtotal = 0;
        var jumlah = 0;
        Object.keys(keranjang).forEach(function (id) {
            var it = keranjang[id];
            subtotal += hargaSatuan(it.produk) * it.qty;
            jumlah += it.qty;
        });
        var diskonPersen = parseFloat(document.getElementById('diskon-persen').value) || 0;
        var diskon = Math.round(subtotal * Math.max(0, diskonPersen) / 100);
        var dasar = Math.max(0, subtotal - diskon);
        var service = CFG.service > 0 ? Math.round(dasar * CFG.service / 100) : 0;
        var pajak = CFG.pajak > 0 ? Math.round((dasar + service) * CFG.pajak / 100) : 0;
        var total = dasar + service + pajak;
        if (CFG.pembulatan > 1) { total = Math.ceil(total / CFG.pembulatan) * CFG.pembulatan; }
        return { subtotal: subtotal, diskon: diskon, service: service, pajak: pajak, total: total, jumlah: jumlah, diskonPersen: diskonPersen };
    }

    function gambarKeranjang() {
        var wadah = document.getElementById('keranjang');
        var kunci = Object.keys(keranjang);
        wadah.innerHTML = '';
        if (!kunci.length) {
            var kosong = document.createElement('p');
            kosong.className = 'keranjang-kosong';
            kosong.innerHTML = 'Keranjang masih kosong.<br>Pilih menu di sebelah kiri.';
            wadah.appendChild(kosong);
        }
        kunci.forEach(function (id) {
            var it = keranjang[id];
            var baris = document.createElement('div');
            baris.className = 'keranjang-item';

            var kiri = document.createElement('div');
            var nama = document.createElement('div');
            nama.className = 'keranjang-nama';
            nama.textContent = it.produk.nama;
            var harga = document.createElement('div');
            harga.className = 'keranjang-harga';
            harga.textContent = rp(hargaSatuan(it.produk)) + ' × ' + it.qty + ' = ' + rp(hargaSatuan(it.produk) * it.qty);
            kiri.appendChild(nama);
            kiri.appendChild(harga);
            if (it.produk.deskripsi) {
                var ket = document.createElement('div');
                ket.className = 'keranjang-harga';
                ket.textContent = it.produk.deskripsi;
                kiri.appendChild(ket);
            }

            var atur = document.createElement('div');
            atur.className = 'qty-atur';
            var kurang = document.createElement('button');
            kurang.type = 'button';
            kurang.textContent = '−';
            kurang.addEventListener('click', function () { ubahQty(id, it.qty - 1); });
            var input = document.createElement('input');
            input.type = 'number';
            input.min = '1';
            input.value = it.qty;
            input.addEventListener('change', function () { ubahQty(id, parseFloat(input.value) || 0); });
            var tambahBtn = document.createElement('button');
            tambahBtn.type = 'button';
            tambahBtn.textContent = '+';
            tambahBtn.addEventListener('click', function () { ubahQty(id, it.qty + 1); });
            var hapus = document.createElement('button');
            hapus.type = 'button';
            hapus.textContent = '×';
            hapus.title = 'Hapus item';
            hapus.addEventListener('click', function () { ubahQty(id, 0); });
            atur.appendChild(kurang);
            atur.appendChild(input);
            atur.appendChild(tambahBtn);
            atur.appendChild(hapus);

            baris.appendChild(kiri);
            baris.appendChild(atur);
            wadah.appendChild(baris);
        });

        var h = hitung();
        document.getElementById('jumlah-item').textContent = h.jumlah + ' item';
        document.getElementById('t-subtotal').textContent = rp(h.subtotal);
        document.getElementById('baris-diskon').hidden = h.diskon <= 0;
        document.getElementById('t-diskon').textContent = '- ' + rp(h.diskon);
        document.getElementById('baris-service').hidden = h.service <= 0;
        document.getElementById('t-service').textContent = rp(h.service);
        document.getElementById('baris-pajak').hidden = h.pajak <= 0;
        document.getElementById('t-pajak').textContent = rp(h.pajak);
        document.getElementById('t-total').textContent = rp(h.total);
        hitungKembalian();
    }

    function hitungKembalian() {
        var h = hitung();
        var uang = parseFloat(document.getElementById('uang-diterima').value) || 0;
        var kembali = Math.max(0, uang - h.total);
        document.getElementById('t-kembali').textContent = rp(kembali);
    }

    function kosongkan() {
        keranjang = {};
        pelangganTerpilih = null;
        document.getElementById('cari-pelanggan').value = '';
        document.getElementById('hasil-pelanggan').innerHTML = '';
        document.getElementById('info-pelanggan').textContent = 'Belum ada pelanggan dipilih (tanpa member harga tetap normal).';
        document.getElementById('nama-pelanggan').value = '';
        document.getElementById('telepon').value = '';
        document.getElementById('catatan-pesanan').value = '';
        document.getElementById('uang-diterima').value = '';
        document.getElementById('diskon-persen').value = '0';
        gambarKeranjang();
        gambarProduk();
    }

    /* ---------------- Metode pembayaran ---------------- */

    function gambarMetode() {
        document.querySelectorAll('#metode-pilih .pembayaran-opsi').forEach(function (b) {
            b.classList.toggle('is-aktif', b.dataset.metode === metode);
        });
        document.getElementById('bungkus-uang').hidden = metode !== 'TUNAI';
        if (metode === 'QRIS') {
            var h = hitung();
            document.getElementById('uang-diterima').value = String(h.total);
        }
        hitungKembalian();
    }

    /* ---------------- Pelanggan ---------------- */

    function cariPelanggan() {
        var q = document.getElementById('cari-pelanggan').value.trim();
        var wadah = document.getElementById('hasil-pelanggan');
        if (q.length < 2) { wadah.innerHTML = ''; return; }
        fetch('api.php?action=pelanggan_cari&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                wadah.innerHTML = '';
                if (!d.pelanggan || !d.pelanggan.length) {
                    var p = document.createElement('span');
                    p.className = 'bantuan';
                    p.textContent = 'Tidak ada pelanggan yang cocok.';
                    wadah.appendChild(p);
                    return;
                }
                d.pelanggan.forEach(function (pl) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'tombol tombol-kecil';
                    b.style.margin = '4px 4px 0 0';
                    b.textContent = pl.nama + (pl.member ? ' ★ ' + pl.poin_teks : '');
                    b.addEventListener('click', function () { pilihPelanggan(pl); });
                    wadah.appendChild(b);
                });
            });
    }

    function pilihPelanggan(pl) {
        pelangganTerpilih = pl;
        document.getElementById('nama-pelanggan').value = pl.nama;
        document.getElementById('telepon').value = pl.telepon;
        document.getElementById('info-pelanggan').textContent = 'Pelanggan terpilih: ' + pl.nama + (pl.member ? ' (member, ' + pl.poin_teks + ')' : ' (bukan member)');
        document.getElementById('hasil-pelanggan').innerHTML = '';
        if (pl.member && CFG.diskonMember > 0) {
            document.getElementById('diskon-persen').value = String(CFG.diskonMember);
        }
        gambarKeranjang();
        gambarProduk();
    }

    /* ---------------- Pesanan masuk ---------------- */

    function teruskanItem(p) {
        if (!p.items) { return ''; }
        return p.items.map(function (it) { return it.qty_teks + '× ' + it.nama; }).join(', ');
    }

    function gambarMasuk() {
        var wadah = document.getElementById('masuk-strip');
        document.getElementById('jumlah-masuk').textContent = masuk.length;
        wadah.innerHTML = '';
        if (!masuk.length) {
            var kosong = document.createElement('p');
            kosong.className = 'kosong-teks';
            kosong.style.margin = '0';
            kosong.textContent = 'Belum ada pesanan yang menunggu.';
            wadah.appendChild(kosong);
            return;
        }
        masuk.forEach(function (p) {
            var kartu = document.createElement('div');
            kartu.className = 'masuk-kartu' + (p.status_bayar === 'LUNAS' ? ' is-lunas' : '');

            var head = document.createElement('header');
            var kode = document.createElement('strong');
            kode.textContent = p.kode;
            var pil = document.createElement('span');
            pil.className = 'pil ' + (p.sumber === 'pelanggan' ? 'pil-warn' : 'pil-info');
            pil.textContent = p.sumber_label;
            head.appendChild(kode);
            head.appendChild(pil);

            var meja = document.createElement('div');
            meja.className = 'meja-teks';
            meja.textContent = p.meja + ' · ' + p.jam + (p.nama_pelanggan ? ' · ' + p.nama_pelanggan : '');

            var ul = document.createElement('ul');
            (p.items || []).forEach(function (it) {
                var li = document.createElement('li');
                li.textContent = it.qty_teks + '× ' + it.nama + (it.catatan ? ' (' + it.catatan + ')' : '');
                ul.appendChild(li);
            });

            var total = document.createElement('div');
            total.innerHTML = '<span class="produk-harga"></span>';
            total.querySelector('span').textContent = p.total_teks + ' · ' + p.metode_label;

            var aksi = document.createElement('div');
            aksi.className = 'aksi';
            if (p.status_bayar !== 'LUNAS') {
                var bayarBtn = document.createElement('button');
                bayarBtn.type = 'button';
                bayarBtn.className = 'tombol tombol-kecil tombol-utama';
                bayarBtn.textContent = 'Bayar';
                bayarBtn.addEventListener('click', function () { bukaBayar(p); });
                aksi.appendChild(bayarBtn);
            }
            var kabarBtn = document.createElement('button');
            kabarBtn.type = 'button';
            kabarBtn.className = 'tombol tombol-kecil';
            kabarBtn.textContent = 'Kirim ke Dapur';
            kabarBtn.addEventListener('click', function () {
                fetch('api.php?action=dapur_status', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: p.id, arah: 'maju' })
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.ok) { kabar(p.kode + ' diteruskan ke dapur.', 'ok'); muatMasuk(true); }
                    else { kabar(d.error || 'Gagal memperbarui status.', 'err'); }
                });
            });
            aksi.appendChild(kabarBtn);

            var batalBtn = document.createElement('button');
            batalBtn.type = 'button';
            batalBtn.className = 'tombol tombol-kecil tombol-bahaya';
            batalBtn.textContent = 'Batal';
            batalBtn.addEventListener('click', function () {
                if (!window.confirm('Batalkan pesanan ' + p.kode + '?')) { return; }
                fetch('api.php?action=batal', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: p.id, alasan: 'Dibatalkan kasir' })
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.ok) { kabar('Pesanan ' + p.kode + ' dibatalkan.', 'ok'); muatMasuk(true); }
                    else { kabar(d.error || 'Gagal membatalkan.', 'err'); }
                });
            });
            aksi.appendChild(batalBtn);

            kartu.appendChild(head);
            kartu.appendChild(meja);
            kartu.appendChild(ul);
            kartu.appendChild(total);
            kartu.appendChild(aksi);
            wadah.appendChild(kartu);
        });
    }

    function muatMasuk(paksa) {
        return fetch('api.php?action=kasir_pesanan', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { return; }
                if (paksa !== true && d.stamp === stampTerakhir) { return; }
                if (stampTerakhir !== '') {
                    var lama = {};
                    masuk.forEach(function (p) { lama[p.id] = 1; });
                    var baru = (d.pesanan || []).filter(function (p) {
                        return !lama[p.id] && p.status_bayar !== 'LUNAS' && p.sumber === 'pelanggan';
                    });
                    if (baru.length) {
                        kabar(baru.length + ' pesanan baru masuk dari pelanggan!', 'info', 6000);
                        bunyi();
                    }
                }
                stampTerakhir = d.stamp || '';
                masuk = d.pesanan || [];
                gambarMasuk();
            });
    }

    /* Pantau pesanan baru setiap 3 detik (ringan: hanya sidik jari + jumlah). */
    function pantau() {
        setInterval(function () {
            fetch('api.php?action=kasir_cek', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { return; }
                    if (stampTerakhir === '') { stampTerakhir = d.stamp; return; }
                    if (d.stamp !== stampTerakhir) { muatMasuk(); }
                })
                .catch(function () { /* koneksi terputus: coba lagi pada denyut berikutnya */ });
        }, 3000);
    }

    function bunyi() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { return; }
            var ctx = new Ctx();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.value = 0.08;
            osc.start();
            setTimeout(function () { osc.frequency.value = 1320; }, 140);
            setTimeout(function () { osc.stop(); ctx.close(); }, 320);
        } catch (e) { /* suara tidak wajib */ }
    }

    /* ---------------- Simpan pesanan ---------------- */

    function kirimPesanan(lunas) {
        var h = hitung();
        if (h.jumlah <= 0) {
            kabar('Keranjang masih kosong — pilih menu terlebih dahulu.', 'err');
            return;
        }
        var uang = lunas && metode === 'TUNAI' ? (parseFloat(document.getElementById('uang-diterima').value) || 0) : h.total;
        if (lunas && metode === 'TUNAI' && uang < h.total) {
            kabar('Uang diterima kurang dari total (' + rp(h.total) + ').', 'err');
            return;
        }
        var items = Object.keys(keranjang).map(function (id) {
            return { produk_id: parseInt(id, 10), qty: keranjang[id].qty, catatan: '' };
        });
        var isi = {
            meja_id: parseInt(document.getElementById('meja-id').value, 10) || 0,
            pelanggan_id: pelangganTerpilih ? pelangganTerpilih.id : 0,
            nama: document.getElementById('nama-pelanggan').value.trim(),
            telepon: document.getElementById('telepon').value.trim(),
            metode_bayar: metode,
            diskon_persen: h.diskonPersen,
            catatan: document.getElementById('catatan-pesanan').value.trim(),
            items: items,
            lunas: !!lunas,
            dibayar: uang
        };
        var tombol = lunas ? document.getElementById('btn-bayar') : document.getElementById('btn-simpan');
        tombol.disabled = true;
        fetch('api.php?action=kasir_simpan', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(isi)
        }).then(function (r) { return r.json(); }).then(function (d) {
            tombol.disabled = false;
            if (!d.ok) { kabar(d.error || 'Pesanan gagal disimpan.', 'err', 6000); return; }
            stampTerakhir = d.stamp || '';
            if (lunas) {
                tampilkanStruk(d.pesanan, true);
            } else {
                kabar('Pesanan ' + d.kode + ' disimpan. Pembayaran dapat dilakukan nanti.', 'ok');
            }
            kosongkan();
            muatMasuk(true);
        }).catch(function () {
            tombol.disabled = false;
            kabar('Gagal menghubungi server. Periksa koneksi lalu coba lagi.', 'err');
        });
    }

    /* ---------------- Modal pembayaran pesanan masuk ---------------- */

    function bukaBayar(p) {
        bayarKontek = { id: p.id, kode: p.kode, total: p.total_angka || 0, metode: p.metode_bayar || 'TUNAI' };
        var isi = document.getElementById('modal-isi');
        document.getElementById('modal-judul').textContent = 'Pembayaran ' + p.kode + ' — ' + p.total_teks;
        if (!bayarKontek.total) { bayarKontek.total = hargaDariTeks(p.total); }
        isi.innerHTML = '';
        var info = document.createElement('p');
        info.className = 'kartu-sub';
        info.textContent = p.meja + ' · ' + (p.nama_pelanggan || 'tanpa nama') + ' · ' + (p.sumber_label || '') + ' · ' + p.jam;
        isi.appendChild(info);

        /* Rincian pesanan supaya kasir tahu apa yang dibayar. */
        var rincian = document.createElement('ul');
        rincian.style.cssText = 'margin:0 0 12px;padding-left:18px;font-size:.86rem';
        (p.items || []).forEach(function (it) {
            var li = document.createElement('li');
            li.textContent = it.qty_teks + '× ' + it.nama + (it.catatan ? ' (' + it.catatan + ')' : '');
            rincian.appendChild(li);
        });
        isi.appendChild(rincian);

        var totalBaris = document.createElement('div');
        totalBaris.className = 'total-baris besar';
        totalBaris.innerHTML = '<span>Total tagihan</span><span>' + p.total_teks + '</span>';
        isi.appendChild(totalBaris);

        var pilih = document.createElement('div');
        pilih.className = 'pembayaran-pilih';
        pilih.style.marginTop = '12px';
        ['TUNAI', 'QRIS'].forEach(function (m) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pembayaran-opsi' + (m === bayarKontek.metode ? ' is-aktif' : '');
            b.textContent = m === 'TUNAI' ? 'Tunai di Kasir' : 'QRIS';
            b.addEventListener('click', function () {
                bayarKontek.metode = m;
                pilih.querySelectorAll('button').forEach(function (x) { x.classList.remove('is-aktif'); });
                b.classList.add('is-aktif');
                isi.querySelector('#bayar-tunai-bagian').hidden = m !== 'TUNAI';
                isi.querySelector('#bayar-qris-bagian').hidden = m !== 'QRIS';
                kabarKembalian();
            });
            pilih.appendChild(b);
        });
        isi.appendChild(pilih);

        var bagianTunai = document.createElement('div');
        bagianTunai.id = 'bayar-tunai-bagian';
        bagianTunai.hidden = bayarKontek.metode !== 'TUNAI';
        bagianTunai.style.marginTop = '12px';
        bagianTunai.innerHTML = '<label class="kolom"><span style="font-size:.82rem;color:var(--muted);font-weight:600">Uang diterima (Rp)</span>' +
            '<input type="number" id="bayar-uang" min="0" step="1000" value="' + Math.round(bayarKontek.total) + '"></label>' +
            '<div class="uang-kembalian">Kembalian: <strong id="bayar-kembali">Rp 0</strong></div>';
        isi.appendChild(bagianTunai);

        var uangCepat = document.createElement('div');
        uangCepat.className = 'baris-tombol';
        uangCepat.style.marginTop = '8px';
        [bayarKontek.total, 50000, 100000, 150000, 200000].forEach(function (n) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'tombol tombol-kecil';
            b.textContent = n === bayarKontek.total ? 'Uang pas' : rp(n);
            b.addEventListener('click', function () {
                var inp = document.getElementById('bayar-uang');
                inp.value = String(Math.round(n));
                kabarKembalian();
            });
            uangCepat.appendChild(b);
        });
        bagianTunai.appendChild(uangCepat);

        var bagianQris = document.createElement('div');
        bagianQris.id = 'bayar-qris-bagian';
        bagianQris.hidden = bayarKontek.metode !== 'QRIS';
        bagianQris.style.marginTop = '12px';
        if (CFG.qrisGambar) {
            bagianQris.innerHTML = '<p style="font-size:.86rem">Minta pelanggan memindai QRIS berikut, lalu tekan Konfirmasi Pembayaran setelah pembayaran masuk.</p>' +
                '<img src="' + CFG.qrisGambar + '" alt="QRIS" style="width:100%;max-width:260px;border-radius:12px;border:1px solid var(--line)">';
        } else {
            bagianQris.innerHTML = '<p style="font-size:.86rem">Gambar QRIS belum diunggah. Unggah di <em>Pengaturan → Pembayaran (QRIS)</em>, atau konfirmasi manual setelah pembayaran diterima.</p>';
        }
        if (CFG.qrisCatatan) {
            var cat = document.createElement('p');
            cat.className = 'kartu-sub';
            cat.textContent = CFG.qrisCatatan;
            bagianQris.appendChild(cat);
        }
        isi.appendChild(bagianQris);

        document.getElementById('modal-bayar').hidden = false;
        kabarKembalian();
    }

    function kabarKembalian() {
        var el = document.getElementById('bayar-kembali');
        if (!el || !bayarKontek) { return; }
        var uang = parseFloat((document.getElementById('bayar-uang') || {}).value) || 0;
        el.textContent = rp(Math.max(0, uang - bayarKontek.total));
    }

    function hargaDariTeks(teks) {
        return parseFloat(String(teks || '0').replace(/[^0-9]/g, '')) || 0;
    }

    function konfirmasiBayar() {
        if (!bayarKontek) { return; }
        var uang = bayarKontek.metode === 'TUNAI'
            ? (parseFloat((document.getElementById('bayar-uang') || {}).value) || bayarKontek.total)
            : bayarKontek.total;
        fetch('api.php?action=bayar', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: bayarKontek.id, metode: bayarKontek.metode, dibayar: uang })
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.ok) { kabar(d.error || 'Pembayaran gagal.', 'err', 6000); return; }
            document.getElementById('modal-bayar').hidden = true;
            tampilkanStruk(d.pesanan, true);
            muatMasuk(true);
        }).catch(function () { kabar('Gagal menghubungi server.', 'err'); });
    }

    /* ---------------- Modal struk ---------------- */

    function tampilkanStruk(p, tawaranCetak) {
        var isi = document.getElementById('modal-struk-isi');
        isi.innerHTML = '';
        var t = document.createElement('div');
        t.className = 'info-baris';
        t.innerHTML = '<span>Nomor</span><strong>' + p.kode + '</strong>';
        isi.appendChild(t);
        var m = document.createElement('div');
        m.className = 'info-baris';
        m.innerHTML = '<span>Meja</span><strong>' + p.meja + '</strong>';
        isi.appendChild(m);
        var tot = document.createElement('div');
        tot.className = 'info-baris';
        tot.innerHTML = '<span>Total</span><strong>' + p.total + '</strong>';
        isi.appendChild(tot);
        var by = document.createElement('div');
        by.className = 'info-baris';
        by.innerHTML = '<span>Pembayaran</span><strong>' + p.metode_label + '</strong>';
        isi.appendChild(by);
        if (parseFloat(String(p.kembali).replace(/[^0-9]/g, '')) > 0) {
            var kb = document.createElement('div');
            kb.className = 'info-baris';
            kb.innerHTML = '<span>Kembalian</span><strong>' + p.kembali + '</strong>';
            isi.appendChild(kb);
        }
        var tautan = document.createElement('p');
        tautan.className = 'kartu-sub';
        tautan.style.marginTop = '10px';
        tautan.textContent = 'Struk dapat dicetak atau disimpan sebagai PDF. Pelanggan juga bisa membuka struk dari HP-nya.';
        isi.appendChild(tautan);

        var cetak = document.getElementById('struk-cetak');
        cetak.onclick = function () {
            window.open('struk.php?p=' + encodeURIComponent(p.kode) + '&k=' + encodeURIComponent(p.token) + '&cetak=1', '_blank', 'noopener');
        };
        document.getElementById('struk-tutup').onclick = function () {
            document.getElementById('modal-struk').hidden = true;
        };
        document.getElementById('modal-struk').hidden = false;
        if (tawaranCetak) { /* dialog cetak dibuka hanya setelah tombol ditekan (aturan browser) */ }
    }

    /* ---------------- Pemasangan ---------------- */

    document.getElementById('cari-produk').addEventListener('input', function (e) {
        cari = e.target.value;
        gambarProduk();
    });
    document.getElementById('kategori-bar').addEventListener('click', function (e) {
        var b = e.target.closest('.kategori-chip');
        if (!b) { return; }
        kategoriAktif = parseInt(b.dataset.kategori, 10) || 0;
        document.querySelectorAll('.kategori-chip').forEach(function (x) { x.classList.toggle('is-aktif', x === b); });
        gambarProduk();
    });
    document.getElementById('cari-pelanggan').addEventListener('input', cariPelanggan);
    document.getElementById('diskon-persen').addEventListener('input', gambarKeranjang);
    document.getElementById('uang-diterima').addEventListener('input', hitungKembalian);
    document.getElementById('metode-pilih').addEventListener('click', function (e) {
        var b = e.target.closest('.pembayaran-opsi');
        if (!b) { return; }
        metode = b.dataset.metode;
        gambarMetode();
    });
    document.getElementById('btn-bayar').addEventListener('click', function () { kirimPesanan(true); });
    document.getElementById('btn-simpan').addEventListener('click', function () { kirimPesanan(false); });
    document.getElementById('btn-kosongkan').addEventListener('click', function () {
        if (Object.keys(keranjang).length && !window.confirm('Kosongkan keranjang?')) { return; }
        kosongkan();
    });
    document.getElementById('modal-tutup').addEventListener('click', function () {
        document.getElementById('modal-bayar').hidden = true;
    });
    document.getElementById('modal-konfirmasi').addEventListener('click', konfirmasiBayar);
    document.getElementById('modal-bayar').addEventListener('input', function (e) {
        if (e.target.id === 'bayar-uang') { kabarKembalian(); }
    });

    gambarProduk();
    gambarKeranjang();
    gambarMetode();
    gambarMasuk();
    pantau();

    fetch('api.php?action=kasir_cek', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) { stampTerakhir = d.stamp; } })
        .catch(function () { });
})();
