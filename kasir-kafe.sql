PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE pengaturan (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT ""
        );
INSERT INTO pengaturan VALUES('nama_kafe','AmNuRah Cafe');
INSERT INTO pengaturan VALUES('tagline_kafe','Coffee & Bean');
INSERT INTO pengaturan VALUES('alamat_kafe','Jl. Sukamaju-Masamba, Luwu Utara');
INSERT INTO pengaturan VALUES('telepon_kafe','0813-5567-2790');
INSERT INTO pengaturan VALUES('logo_url','https://media.vibecoder.co.id/app-media/andidjemma/kasir-kafe/866eee97-9308-4427-9ae6-4fd5f2f9458b.png');
INSERT INTO pengaturan VALUES('zona_waktu','Asia/Makassar');
INSERT INTO pengaturan VALUES('mata_uang','Rp');
INSERT INTO pengaturan VALUES('pajak_persen','10');
INSERT INTO pengaturan VALUES('pajak_aktif','1');
INSERT INTO pengaturan VALUES('service_persen','0');
INSERT INTO pengaturan VALUES('service_aktif','0');
INSERT INTO pengaturan VALUES('diskon_member_persen','3');
INSERT INTO pengaturan VALUES('pembulatan','0');
INSERT INTO pengaturan VALUES('poin_aktif','1');
INSERT INTO pengaturan VALUES('poin_per_rupiah','10001');
INSERT INTO pengaturan VALUES('poin_nilai','1');
INSERT INTO pengaturan VALUES('kode_pesanan_prefix','K');
INSERT INTO pengaturan VALUES('alamat_publik','');
INSERT INTO pengaturan VALUES('qris_aktif','1');
INSERT INTO pengaturan VALUES('qris_gambar_url','https://media.vibecoder.co.id/app-media/andidjemma/kasir-kafe/1c62fe9c-2c33-4c64-b89d-58f245689946.png');
INSERT INTO pengaturan VALUES('qris_catatan','Scan QRIS di meja/kasir, lalu kasir akan menandai pembayaran.');
INSERT INTO pengaturan VALUES('tunai_aktif','1');
INSERT INTO pengaturan VALUES('printer_kertas','thermal80');
INSERT INTO pengaturan VALUES('printer_otomatis','1');
INSERT INTO pengaturan VALUES('struk_judul','STRUK PEMBAYARAN');
INSERT INTO pengaturan VALUES('struk_catatan','Terima kasih atas kunjungan Anda. Selamat menikmati!');
INSERT INTO pengaturan VALUES('struk_tampil_logo','1');
INSERT INTO pengaturan VALUES('display_judul','MENU HARI INI');
INSERT INTO pengaturan VALUES('display_footer','Selamat Menikmati Sajian Kami — Lakukan Pemesanan Melalui Kasir Kami atau Melalui Barcode Meja — Terima Kasih Atas Kunjungan Anda ! —  Harga Belum Termasuk PPN');
INSERT INTO pengaturan VALUES('display_kecepatan','30');
INSERT INTO pengaturan VALUES('display_kolom','4');
INSERT INTO pengaturan VALUES('display_panel_siap','1');
INSERT INTO pengaturan VALUES('display_tampil_harga','1');
INSERT INTO pengaturan VALUES('dapur_kolom_siap','1');
INSERT INTO pengaturan VALUES('urut_pesanan','dapur');
INSERT INTO pengaturan VALUES('tema_warna','#B4763B');
INSERT INTO pengaturan VALUES('demo_produk','1');
CREATE TABLE user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL DEFAULT "",
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "kasir",
            aktif INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT "",
            failed_count INTEGER NOT NULL DEFAULT 0,
            locked_until TEXT NULL
        );
INSERT INTO user VALUES(1,'superadmin','OWNER','$2y$10$8SkIJcSm7iKl47dYXhZd0.HDKUGJ4m.T06.PXAMCMZbESPN9POSmC','owner',1,'2026-10-04 18:14:58','2026-10-05 08:55:47',0,NULL);
INSERT INTO user VALUES(2,'kasir','KASIR 01','$2y$10$IYmOeVTCC/Ga2d5ZMLFmje5vHUkFp8Fqih/ycX1uct70opAnQcBr2','kasir',1,'2026-10-04 18:14:58','2026-10-05 08:57:09',0,NULL);
INSERT INTO user VALUES(3,'dapur','Petugas Dapur 01','$2y$10$9mI0IfjDXxzLp3/UM0JG6Ou0kBw.dzW7zChS5Ju.uIIz8KdXxhRMe','dapur',1,'2026-10-04 18:14:58','2026-10-05 08:57:38',0,NULL);
CREATE TABLE sesi (
            token_hash TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT "",
            expires_at TEXT NOT NULL DEFAULT "",
            last_seen TEXT NOT NULL DEFAULT ""
        );
INSERT INTO sesi VALUES('035939202d68a7a3af300cc14fc20b16ccf5ee907c5bf35795a606e55c744c98',1,'2026-10-05 11:51:45','2026-10-06 08:03:43','2026-10-05 20:03:43');
CREATE TABLE kategori (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1
        );
INSERT INTO kategori VALUES(1,'Kopi',1,1);
INSERT INTO kategori VALUES(2,'Non-Kopi',2,1);
INSERT INTO kategori VALUES(3,'Makanan',3,1);
INSERT INTO kategori VALUES(4,'Snack & Dessert',4,1);
CREATE TABLE produk (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT NOT NULL DEFAULT "",
            nama TEXT NOT NULL,
            kategori_id INTEGER NULL,
            harga REAL NOT NULL DEFAULT 0,
            harga_member REAL NOT NULL DEFAULT 0,
            satuan TEXT NOT NULL DEFAULT "porsi",
            deskripsi TEXT NOT NULL DEFAULT "",
            foto_url TEXT NOT NULL DEFAULT "",
            favorit INTEGER NOT NULL DEFAULT 0,
            stok INTEGER NOT NULL DEFAULT -1,
            aktif INTEGER NOT NULL DEFAULT 1,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        );
INSERT INTO produk VALUES(1,'P001','Espresso',1,18000.0,0.0,'porsi','Kopi hitam pekat, 30 ml','https://media.vibecoder.co.id/app-media/andidjemma/kasir-kafe/0c53456e-c8b4-4c5c-b29d-86e8a713c2a0.png',0,-1,1,1,'seed','2026-10-04 18:14:58','2026-10-04 21:23:39');
INSERT INTO produk VALUES(2,'P002','Americano',1,22000.0,0.0,'porsi','Espresso + air panas/es dingin','https://media.vibecoder.co.id/app-media/andidjemma/kasir-kafe/7a6592ac-c7ed-49c9-a8e0-b8ce424d9b2a.png',0,-1,1,2,'seed','2026-10-04 18:14:58','2026-10-05 11:54:00');
INSERT INTO produk VALUES(3,'P003','Kopi Susu Gula Aren',1,25000.0,0.0,'porsi','Signature kafe, manis legit','https://media.vibecoder.co.id/app-media/andidjemma/kasir-kafe/e117a61d-0564-438d-a42b-d95e47237460.png',0,-1,1,3,'seed','2026-10-04 18:14:58','2026-10-05 11:16:45');
INSERT INTO produk VALUES(4,'P004','Cappuccino',1,27000.0,0.0,'porsi','Espresso + susu berbusa','https://png.pngtree.com/png-vector/20250327/ourmid/pngtree-assorted-coffee-cups-including-cappuccino-cup-with-heart-png-image_15878581.png',1,-1,1,4,'seed','2026-10-04 18:14:58','2026-10-05 11:17:41');
INSERT INTO produk VALUES(5,'P005','Kopi Tubruk',1,15000.0,0.0,'porsi','Kopi tradisional','https://png.pngtree.com/png-vector/20230430/ourmid/pngtree-coffee-drink-afternoon-tea-png-image_6993362.png',0,-1,1,5,'seed','2026-10-04 18:14:58','2026-10-05 11:18:32');
INSERT INTO produk VALUES(6,'P006','Matcha Latte',2,28000.0,0.0,'porsi','Matcha premium + susu','https://png.pngtree.com/png-vector/20250320/ourmid/pngtree-iced-matcha-latte-with-smooth-layering-png-image_15775720.png',0,-1,1,6,'seed','2026-10-04 18:14:58','2026-10-05 11:19:22');
INSERT INTO produk VALUES(7,'P007','Chocolate',2,24000.0,0.0,'porsi','Cokelat panas/dingin','https://www.pngall.com/wp-content/uploads/15/Iced-Coffee-PNG-Pic.png',1,-1,1,7,'seed','2026-10-04 18:14:58','2026-10-05 20:03:17');
INSERT INTO produk VALUES(8,'P008','Teh Lemon',2,18000.0,0.0,'porsi','Teh + perasan lemon','https://img.pikbest.com/png-images/20260217/iced-tea-with-lemon-and-mint-in-plastic_16182474.jpg!bw700',0,-1,1,8,'seed','2026-10-04 18:14:58','2026-10-05 11:23:44');
INSERT INTO produk VALUES(9,'P009','Air Mineral',2,8000.0,0.0,'porsi','Botol 600 ml','https://png.pngtree.com/png-vector/20240913/ourmid/pngtree-mineral-water-bottles-png-image_12926881.png',0,-1,1,9,'seed','2026-10-04 18:14:58','2026-10-05 11:24:24');
INSERT INTO produk VALUES(10,'P010','Nasi Goreng Kafe',3,32000.0,0.0,'porsi','Nasi goreng spesial + telur','https://png.pngtree.com/png-clipart/20241012/original/pngtree-simple-thai-fried-rice-with-egg-for-busy-weeknights-delicious-a-png-image_16285503.png',0,-1,1,10,'seed','2026-10-04 18:14:58','2026-10-05 11:24:57');
INSERT INTO produk VALUES(11,'P011','Mie Goreng Spesial',3,30000.0,0.0,'porsi','Mie goreng + telur & sayur','https://png.pngtree.com/png-clipart/20250213/original/pngtree-fried-noodles-with-egg-png-image_20427668.png',0,-1,1,11,'seed','2026-10-04 18:14:58','2026-10-05 11:25:46');
INSERT INTO produk VALUES(12,'P012','Ayam Geprek Sambal Ijo',3,35000.0,0.0,'porsi','Ayam crispy + sambal ijo','https://png.pngtree.com/png-clipart/20230514/original/pngtree-green-sambal-crispy-fried-chicken-png-image_9161316.png',0,-1,1,12,'seed','2026-10-04 18:14:58','2026-10-05 11:26:34');
INSERT INTO produk VALUES(13,'P013','Roti Bakar Keju',3,20000.0,0.0,'porsi','Roti bakar + keju mozarella','https://img.pikbest.com/png-images/20241225/melted-cheese-grilled-sandwich-with-crispy-toast-and-savory-flavor_11300275.png!w700wp',0,-1,1,13,'seed','2026-10-04 18:14:58','2026-10-05 11:27:20');
INSERT INTO produk VALUES(14,'P014','Kentang Goreng',4,22000.0,0.0,'porsi','French fries + saus','https://png.pngtree.com/png-vector/20240722/ourmid/pngtree-a-plate-of-golden-french-fries-png-image_13126753.png',0,-1,1,14,'seed','2026-10-04 18:14:58','2026-10-05 11:27:47');
INSERT INTO produk VALUES(15,'P015','Pisang Goreng Madu',4,21000.0,0.0,'porsi','Pisang goreng + madu','https://png.pngtree.com/png-vector/20240203/ourmid/pngtree-sweet-fried-bananas-png-image_11594464.png',0,-1,1,15,'seed','2026-10-04 18:14:58','2026-10-05 11:28:19');
INSERT INTO produk VALUES(16,'P016','Cheesecake Slice',4,30000.0,0.0,'porsi','New York cheesecake','https://png.pngtree.com/png-vector/20241102/ourmid/pngtree-slice-of-strawberry-cheesecake-topped-with-fresh-berries-png-image_14223080.png',0,-1,1,16,'seed','2026-10-04 18:14:58','2026-10-05 11:29:20');
CREATE TABLE meja (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nomor TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL DEFAULT "",
            token TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1,
            urutan INTEGER NOT NULL DEFAULT 0
        );
INSERT INTO meja VALUES(1,'1','Meja 1','75f667c4aad161efa6b2',1,1);
INSERT INTO meja VALUES(2,'2','Meja 2','0eaff525d269cc91bbf5',1,2);
INSERT INTO meja VALUES(3,'3','Meja 3','b0c0ce65eb122cf39f07',1,3);
INSERT INTO meja VALUES(4,'4','Meja 4','ee56cdce9831348f7dbe',1,4);
INSERT INTO meja VALUES(5,'5','Meja 5','55b369b4245809b18cb7',1,5);
INSERT INTO meja VALUES(6,'6','Meja 6','9411ed093ca6bc43264f',1,6);
INSERT INTO meja VALUES(7,'7','Meja 7','8b2e02e346fb7e081b3a',1,7);
INSERT INTO meja VALUES(8,'8','Meja 8','989197bc715d140677ab',1,8);
INSERT INTO meja VALUES(9,'9','Meja 9','ce23b5ee1bd608bee145',1,9);
INSERT INTO meja VALUES(10,'10','Meja 10','61c9bcbcfb59263f5804',1,10);
CREATE TABLE pelanggan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            telepon TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL DEFAULT "",
            member INTEGER NOT NULL DEFAULT 1,
            poin REAL NOT NULL DEFAULT 0,
            total_belanja REAL NOT NULL DEFAULT 0,
            jumlah_kunjungan INTEGER NOT NULL DEFAULT 0,
            catatan TEXT NOT NULL DEFAULT "",
            terakhir TEXT NULL,
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        );
CREATE TABLE pesanan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT NOT NULL,
            nomor INTEGER NOT NULL DEFAULT 0,
            tanggal TEXT NOT NULL,
            meja_id INTEGER NULL,
            pelanggan_id INTEGER NULL,
            nama_pelanggan TEXT NOT NULL DEFAULT "",
            telepon TEXT NOT NULL DEFAULT "",
            sumber TEXT NOT NULL DEFAULT "kasir",
            metode_bayar TEXT NOT NULL DEFAULT "TUNAI",
            status_bayar TEXT NOT NULL DEFAULT "BELUM",
            status TEXT NOT NULL DEFAULT "BARU",
            subtotal REAL NOT NULL DEFAULT 0,
            diskon REAL NOT NULL DEFAULT 0,
            pajak REAL NOT NULL DEFAULT 0,
            service REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL DEFAULT 0,
            dibayar REAL NOT NULL DEFAULT 0,
            kembali REAL NOT NULL DEFAULT 0,
            poin_dapat REAL NOT NULL DEFAULT 0,
            catatan TEXT NOT NULL DEFAULT "",
            token TEXT NOT NULL DEFAULT "",
            kasir TEXT NOT NULL DEFAULT "",
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            bayar_oleh TEXT NOT NULL DEFAULT "",
            bayar_at TEXT NULL,
            selesai_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        );
INSERT INTO pesanan VALUES(7,'K007',7,'2026-10-04',1,NULL,'','','kasir','QRIS','LUNAS','SELESAI',66000.0,0.0,6600.0,0.0,72600.0,72600.0,0.0,0.0,'','86caf7abad8aa6e8f4ce','owner','owner','owner','2026-10-04 21:51:40','2026-10-04 22:02:00','2026-10-04 21:51:40','2026-10-04 22:02:00');
INSERT INTO pesanan VALUES(8,'K008',8,'2026-10-04',6,NULL,'WATI','082254638796','pelanggan','QRIS','LUNAS','SELESAI',129000.0,0.0,12900.0,0.0,141900.0,141900.0,0.0,0.0,'gula dipisah','7a6317e5ebf533449ffe','','','owner','2026-10-04 21:59:51','2026-10-04 22:03:08','2026-10-04 21:58:44','2026-10-04 22:03:08');
INSERT INTO pesanan VALUES(9,'K009',9,'2026-10-04',6,NULL,'WATI','08546138846494','pelanggan','TUNAI','LUNAS','SELESAI',70000.0,0.0,7000.0,0.0,77000.0,100000.0,23000.0,0.0,'','3f47d843a11ae2f870d6','','','owner','2026-10-04 22:07:17','2026-10-04 22:08:33','2026-10-04 22:06:23','2026-10-04 22:08:33');
INSERT INTO pesanan VALUES(10,'K010',10,'2026-10-04',NULL,NULL,'budi','','kasir','QRIS','LUNAS','SELESAI',81000.0,0.0,8100.0,0.0,89100.0,89100.0,0.0,0.0,'','7ca18d81df536206418e','owner','owner','owner','2026-10-04 22:10:12','2026-10-04 22:11:38','2026-10-04 22:10:12','2026-10-04 22:11:38');
INSERT INTO pesanan VALUES(11,'K001',1,'2026-10-05',4,NULL,'Parno','081355672790','pelanggan','QRIS','LUNAS','SELESAI',65000.0,0.0,6500.0,0.0,71500.0,71500.0,0.0,0.0,'','576f82d0f2d07014dc41','','','superadmin','2026-10-05 11:40:53','2026-10-05 11:42:25','2026-10-05 11:40:06','2026-10-05 11:42:25');
INSERT INTO pesanan VALUES(12,'K002',2,'2026-10-05',7,NULL,'','','kasir','QRIS','LUNAS','SELESAI',119000.0,0.0,11900.0,0.0,130900.0,130900.0,0.0,0.0,'mineral dingin','9a47d1cdaefcea6db3ba','kasir','kasir','kasir','2026-10-05 11:47:13','2026-10-05 11:52:29','2026-10-05 11:47:13','2026-10-05 11:52:29');
INSERT INTO pesanan VALUES(13,'K003',3,'2026-10-05',10,NULL,'','','kasir','QRIS','LUNAS','SELESAI',84000.0,0.0,8400.0,0.0,92400.0,92400.0,0.0,0.0,'','8a5fd15f4a52fe604814','kasir','kasir','kasir','2026-10-05 11:50:03','2026-10-05 11:52:34','2026-10-05 11:50:03','2026-10-05 11:52:34');
INSERT INTO pesanan VALUES(14,'K004',4,'2026-10-05',3,NULL,'ACCO','076391646489478','pelanggan','QRIS','LUNAS','SELESAI',104000.0,0.0,10400.0,0.0,114400.0,114400.0,0.0,0.0,'','a670a3d2ca7320cf03d7','','','superadmin','2026-10-05 19:49:03','2026-10-05 19:50:00','2026-10-05 19:48:45','2026-10-05 19:50:00');
CREATE TABLE pesanan_item (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pesanan_id INTEGER NOT NULL,
            produk_id INTEGER NULL,
            kategori_id INTEGER NULL,
            nama TEXT NOT NULL,
            harga REAL NOT NULL DEFAULT 0,
            qty REAL NOT NULL DEFAULT 1,
            catatan TEXT NOT NULL DEFAULT "",
            subtotal REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE
        );
INSERT INTO pesanan_item VALUES(14,7,2,1,'Americano',22000.0,3.0,'',66000.0);
INSERT INTO pesanan_item VALUES(15,8,1,1,'Espresso',18000.0,1.0,'',18000.0);
INSERT INTO pesanan_item VALUES(16,8,2,1,'Americano',22000.0,2.0,'',44000.0);
INSERT INTO pesanan_item VALUES(17,8,3,1,'Kopi Susu Gula Aren',25000.0,1.0,'',25000.0);
INSERT INTO pesanan_item VALUES(18,8,13,3,'Roti Bakar Keju',20000.0,1.0,'',20000.0);
INSERT INTO pesanan_item VALUES(19,8,14,4,'Kentang Goreng',22000.0,1.0,'',22000.0);
INSERT INTO pesanan_item VALUES(20,9,12,3,'Ayam Geprek Sambal Ijo',35000.0,2.0,'',70000.0);
INSERT INTO pesanan_item VALUES(21,10,4,1,'Cappuccino',27000.0,3.0,'',81000.0);
INSERT INTO pesanan_item VALUES(22,11,4,1,'Cappuccino',27000.0,1.0,'',27000.0);
INSERT INTO pesanan_item VALUES(23,11,5,1,'Kopi Tubruk',15000.0,2.0,'',30000.0);
INSERT INTO pesanan_item VALUES(24,11,9,2,'Air Mineral',8000.0,1.0,'',8000.0);
INSERT INTO pesanan_item VALUES(25,12,3,1,'Kopi Susu Gula Aren',25000.0,1.0,'',25000.0);
INSERT INTO pesanan_item VALUES(26,12,6,2,'Matcha Latte',28000.0,2.0,'',56000.0);
INSERT INTO pesanan_item VALUES(27,12,9,2,'Air Mineral',8000.0,1.0,'',8000.0);
INSERT INTO pesanan_item VALUES(28,12,11,3,'Mie Goreng Spesial',30000.0,1.0,'',30000.0);
INSERT INTO pesanan_item VALUES(29,13,2,1,'Americano',22000.0,1.0,'',22000.0);
INSERT INTO pesanan_item VALUES(30,13,10,3,'Nasi Goreng Kafe',32000.0,1.0,'',32000.0);
INSERT INTO pesanan_item VALUES(31,13,11,3,'Mie Goreng Spesial',30000.0,1.0,'',30000.0);
INSERT INTO pesanan_item VALUES(32,14,4,1,'Cappuccino',27000.0,2.0,'',54000.0);
INSERT INTO pesanan_item VALUES(33,14,8,2,'Teh Lemon',18000.0,1.0,'',18000.0);
INSERT INTO pesanan_item VALUES(34,14,10,3,'Nasi Goreng Kafe',32000.0,1.0,'',32000.0);
CREATE TABLE pesanan_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pesanan_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "",
            catatan TEXT NOT NULL DEFAULT "",
            oleh TEXT NOT NULL DEFAULT "",
            waktu TEXT NOT NULL DEFAULT ""
        );
INSERT INTO pesanan_log VALUES(39,7,'BARU','Pesanan dibuat di kasir','owner','2026-10-04 21:51:40');
INSERT INTO pesanan_log VALUES(40,7,'DITERIMA','','owner','2026-10-04 21:52:12');
INSERT INTO pesanan_log VALUES(41,7,'DISIAPKAN','','owner','2026-10-04 21:52:32');
INSERT INTO pesanan_log VALUES(42,7,'SIAP','','owner','2026-10-04 21:52:47');
INSERT INTO pesanan_log VALUES(43,8,'BARU','Pesanan dari pelanggan (QR meja)','','2026-10-04 21:58:44');
INSERT INTO pesanan_log VALUES(44,8,'BARU','Pembayaran QRIS diterima (Rp 141.900)','owner','2026-10-04 21:59:51');
INSERT INTO pesanan_log VALUES(45,8,'DITERIMA','','owner','2026-10-04 21:59:59');
INSERT INTO pesanan_log VALUES(46,8,'DISIAPKAN','','owner','2026-10-04 22:01:09');
INSERT INTO pesanan_log VALUES(47,8,'SIAP','','owner','2026-10-04 22:01:50');
INSERT INTO pesanan_log VALUES(48,7,'SELESAI','','owner','2026-10-04 22:02:00');
INSERT INTO pesanan_log VALUES(49,8,'SELESAI','','owner','2026-10-04 22:03:08');
INSERT INTO pesanan_log VALUES(50,9,'BARU','Pesanan dari pelanggan (QR meja)','','2026-10-04 22:06:23');
INSERT INTO pesanan_log VALUES(51,9,'BARU','Pembayaran Tunai di Kasir diterima (Rp 77.000)','owner','2026-10-04 22:07:17');
INSERT INTO pesanan_log VALUES(52,9,'DITERIMA','','owner','2026-10-04 22:07:36');
INSERT INTO pesanan_log VALUES(53,9,'DISIAPKAN','','owner','2026-10-04 22:08:07');
INSERT INTO pesanan_log VALUES(54,9,'SIAP','','owner','2026-10-04 22:08:19');
INSERT INTO pesanan_log VALUES(55,9,'SELESAI','','owner','2026-10-04 22:08:33');
INSERT INTO pesanan_log VALUES(56,10,'BARU','Pesanan dibuat di kasir','owner','2026-10-04 22:10:12');
INSERT INTO pesanan_log VALUES(57,10,'DITERIMA','','owner','2026-10-04 22:10:36');
INSERT INTO pesanan_log VALUES(58,10,'DISIAPKAN','','owner','2026-10-04 22:11:14');
INSERT INTO pesanan_log VALUES(59,10,'SIAP','','owner','2026-10-04 22:11:25');
INSERT INTO pesanan_log VALUES(60,10,'SELESAI','','owner','2026-10-04 22:11:38');
INSERT INTO pesanan_log VALUES(61,11,'BARU','Pesanan dari pelanggan (QR meja)','','2026-10-05 11:40:06');
INSERT INTO pesanan_log VALUES(62,11,'BARU','Pembayaran QRIS diterima (Rp 71.500)','superadmin','2026-10-05 11:40:53');
INSERT INTO pesanan_log VALUES(63,11,'DITERIMA','','superadmin','2026-10-05 11:41:00');
INSERT INTO pesanan_log VALUES(64,11,'DISIAPKAN','','superadmin','2026-10-05 11:41:29');
INSERT INTO pesanan_log VALUES(65,11,'SIAP','','superadmin','2026-10-05 11:41:49');
INSERT INTO pesanan_log VALUES(66,11,'SELESAI','','superadmin','2026-10-05 11:42:25');
INSERT INTO pesanan_log VALUES(67,12,'BARU','Pesanan dibuat di kasir','kasir','2026-10-05 11:47:13');
INSERT INTO pesanan_log VALUES(68,12,'DITERIMA','','kasir','2026-10-05 11:48:23');
INSERT INTO pesanan_log VALUES(69,13,'BARU','Pesanan dibuat di kasir','kasir','2026-10-05 11:50:03');
INSERT INTO pesanan_log VALUES(70,13,'DITERIMA','','superadmin','2026-10-05 11:51:53');
INSERT INTO pesanan_log VALUES(71,12,'DISIAPKAN','','superadmin','2026-10-05 11:52:00');
INSERT INTO pesanan_log VALUES(72,12,'SIAP','','superadmin','2026-10-05 11:52:16');
INSERT INTO pesanan_log VALUES(73,13,'DISIAPKAN','','superadmin','2026-10-05 11:52:27');
INSERT INTO pesanan_log VALUES(74,12,'SELESAI','','superadmin','2026-10-05 11:52:29');
INSERT INTO pesanan_log VALUES(75,13,'SIAP','','superadmin','2026-10-05 11:52:31');
INSERT INTO pesanan_log VALUES(76,13,'SELESAI','','superadmin','2026-10-05 11:52:34');
INSERT INTO pesanan_log VALUES(77,14,'BARU','Pesanan dari pelanggan (QR meja)','','2026-10-05 19:48:45');
INSERT INTO pesanan_log VALUES(78,14,'BARU','Pembayaran QRIS diterima (Rp 114.400)','superadmin','2026-10-05 19:49:03');
INSERT INTO pesanan_log VALUES(79,14,'DITERIMA','','superadmin','2026-10-05 19:49:10');
INSERT INTO pesanan_log VALUES(80,14,'DISIAPKAN','','superadmin','2026-10-05 19:49:18');
INSERT INTO pesanan_log VALUES(81,14,'SIAP','','superadmin','2026-10-05 19:49:56');
INSERT INTO pesanan_log VALUES(82,14,'SELESAI','','superadmin','2026-10-05 19:50:00');
CREATE TABLE nomor_counter (
            nama TEXT PRIMARY KEY,
            periode TEXT NOT NULL DEFAULT "",
            nilai INTEGER NOT NULL DEFAULT 0
        );
INSERT INTO nomor_counter VALUES('pesanan','2026-10-05',4);
CREATE TABLE log_admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            waktu TEXT NOT NULL DEFAULT "",
            oleh TEXT NOT NULL DEFAULT "",
            aksi TEXT NOT NULL DEFAULT "",
            rincian TEXT NOT NULL DEFAULT ""
        );
INSERT INTO log_admin VALUES(1,'2026-10-04 18:15:56','owner','pesanan_hapus','Pesanan K002 (Rp 18.000) dihapus');
INSERT INTO log_admin VALUES(2,'2026-10-04 18:16:29','owner','pesanan_hapus','Pesanan K001 (Rp 18.000) dihapus');
INSERT INTO log_admin VALUES(3,'2026-10-04 18:16:51','owner','produk_tambah','Produk baru — Produk UJI FOTO');
INSERT INTO log_admin VALUES(4,'2026-10-04 18:17:23','owner','produk_hapus','Produk #17 — Produk UJI FOTO');
INSERT INTO log_admin VALUES(5,'2026-10-04 18:17:33','owner','produk_tambah','Produk baru — Produk UJI FOTO');
INSERT INTO log_admin VALUES(6,'2026-10-04 18:18:05','owner','produk_hapus','Produk #18 — Produk UJI FOTO');
INSERT INTO log_admin VALUES(7,'2026-10-04 18:31:45','owner','pengaturan_identitas','Identitas & tampilan diperbarui');
INSERT INTO log_admin VALUES(8,'2026-10-04 18:33:46','owner','pengaturan_pembayaran','Setelan pembayaran diperbarui');
INSERT INTO log_admin VALUES(9,'2026-10-04 18:43:13','owner','produk_ubah','Produk #1 — Espresso');
INSERT INTO log_admin VALUES(10,'2026-10-04 20:57:23','owner','data_hapus','4 pesanan 2026-10-04 s/d 2026-10-04 dihapus');
INSERT INTO log_admin VALUES(11,'2026-10-04 20:58:26','owner','pelanggan_hapus','Bacco');
INSERT INTO log_admin VALUES(12,'2026-10-04 20:58:28','owner','pelanggan_hapus','Ucenk');
INSERT INTO log_admin VALUES(13,'2026-10-04 21:00:42','owner','pengaturan_identitas','Identitas & tampilan diperbarui');
INSERT INTO log_admin VALUES(14,'2026-10-04 21:22:18','owner','produk_ubah','Produk #2 — Americano');
INSERT INTO log_admin VALUES(15,'2026-10-04 21:23:39','owner','produk_ubah','Produk #1 — Espresso');
INSERT INTO log_admin VALUES(16,'2026-10-04 21:24:33','owner','pengaturan_identitas','Identitas & tampilan diperbarui');
INSERT INTO log_admin VALUES(17,'2026-10-04 21:31:29','owner','pengaturan_identitas','Identitas & tampilan diperbarui');
INSERT INTO log_admin VALUES(18,'2026-10-05 08:55:47','owner','user_ubah','Akun superadmin');
INSERT INTO log_admin VALUES(19,'2026-10-05 08:57:09','superadmin','user_ubah','Akun kasir');
INSERT INTO log_admin VALUES(20,'2026-10-05 08:57:38','superadmin','user_ubah','Akun dapur');
INSERT INTO log_admin VALUES(21,'2026-10-05 11:16:45','superadmin','produk_ubah','Produk #3 — Kopi Susu Gula Aren');
INSERT INTO log_admin VALUES(22,'2026-10-05 11:17:41','superadmin','produk_ubah','Produk #4 — Cappuccino');
INSERT INTO log_admin VALUES(23,'2026-10-05 11:18:32','superadmin','produk_ubah','Produk #5 — Kopi Tubruk');
INSERT INTO log_admin VALUES(24,'2026-10-05 11:19:22','superadmin','produk_ubah','Produk #6 — Matcha Latte');
INSERT INTO log_admin VALUES(25,'2026-10-05 11:22:04','superadmin','produk_ubah','Produk #7 — Chocolate');
INSERT INTO log_admin VALUES(26,'2026-10-05 11:22:44','superadmin','produk_ubah','Produk #7 — Chocolate');
INSERT INTO log_admin VALUES(27,'2026-10-05 11:23:44','superadmin','produk_ubah','Produk #8 — Teh Lemon');
INSERT INTO log_admin VALUES(28,'2026-10-05 11:24:24','superadmin','produk_ubah','Produk #9 — Air Mineral');
INSERT INTO log_admin VALUES(29,'2026-10-05 11:24:57','superadmin','produk_ubah','Produk #10 — Nasi Goreng Kafe');
INSERT INTO log_admin VALUES(30,'2026-10-05 11:25:46','superadmin','produk_ubah','Produk #11 — Mie Goreng Spesial');
INSERT INTO log_admin VALUES(31,'2026-10-05 11:26:34','superadmin','produk_ubah','Produk #12 — Ayam Geprek Sambal Ijo');
INSERT INTO log_admin VALUES(32,'2026-10-05 11:27:20','superadmin','produk_ubah','Produk #13 — Roti Bakar Keju');
INSERT INTO log_admin VALUES(33,'2026-10-05 11:27:47','superadmin','produk_ubah','Produk #14 — Kentang Goreng');
INSERT INTO log_admin VALUES(34,'2026-10-05 11:28:19','superadmin','produk_ubah','Produk #15 — Pisang Goreng Madu');
INSERT INTO log_admin VALUES(35,'2026-10-05 11:29:20','superadmin','produk_ubah','Produk #16 — Cheesecake Slice');
DELETE FROM sqlite_sequence;
INSERT INTO sqlite_sequence VALUES('user',3);
INSERT INTO sqlite_sequence VALUES('meja',10);
INSERT INTO sqlite_sequence VALUES('kategori',4);
INSERT INTO sqlite_sequence VALUES('produk',18);
INSERT INTO sqlite_sequence VALUES('pesanan',14);
INSERT INTO sqlite_sequence VALUES('pesanan_item',34);
INSERT INTO sqlite_sequence VALUES('pesanan_log',82);
INSERT INTO sqlite_sequence VALUES('log_admin',35);
INSERT INTO sqlite_sequence VALUES('pelanggan',2);
COMMIT;
