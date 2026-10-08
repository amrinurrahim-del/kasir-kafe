<?php
declare(strict_types=1);

/*
 * Pembuat berkas keluaran: struk PDF (thermal 80 mm) dan laporan PDF / Excel.
 * Dipisah dari halaman agar dapat diuji langsung dari CLI.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/pdf.php';

/* ------------------------------------------------------------------ */
/* Pengiriman berkas                                                   */
/* ------------------------------------------------------------------ */

function kirim_file(string $nama, string $mime, string $isi): void
{
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $nama . '"');
    header('Content-Length: ' . strlen($isi));
    header('Cache-Control: no-store, max-age=0');
    echo $isi;
    exit;
}

function kirim_csv(string $nama, array $header, array $rows): void
{
    $pack = static function (array $cols): string {
        $out = [];
        foreach ($cols as $c) {
            $s = (string) $c;
            $out[] = (strpos($s, ';') !== false || strpos($s, '"') !== false || strpos($s, "\n") !== false)
                ? '"' . str_replace('"', '""', $s) . '"' : $s;
        }
        return implode(';', $out) . "\r\n";
    };
    $isi = "\xEF\xBB\xBF" . $pack($header);
    foreach ($rows as $r) {
        $isi .= $pack($r);
    }
    kirim_file($nama, 'text/csv; charset=utf-8', $isi);
}

/** Berkas .xlsx asli (ZIP + XML). null bila ekstensi zip tidak tersedia. */
function xlsx_bytes(string $sheet, array $header, array $rows, array $kolomNumerik = []): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $esc = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $kolom = static function (int $i): string {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    };

    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>';
    foreach (array_keys($header) as $i) {
        $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . ($i === 0 ? 6 : 16) . '" customWidth="1"/>';
    }
    $xml .= '</cols><sheetData>';

    $baris = 1;
    $xml .= '<row r="' . $baris . '">';
    foreach (array_values($header) as $i => $judul) {
        $xml .= '<c r="' . $kolom($i) . $baris . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($judul) . '</t></is></c>';
    }
    $xml .= '</row>';

    foreach ($rows as $r) {
        $baris++;
        $xml .= '<row r="' . $baris . '">';
        foreach (array_values($r) as $i => $v) {
            $ref = $kolom($i) . $baris;
            if (isset($kolomNumerik[$i]) && is_numeric($v)) {
                $xml .= '<c r="' . $ref . '"><v>' . (0 + $v) . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $esc((string) $v) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData></worksheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    if ($tmp === false) {
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        return null;
    }
    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . $esc(substr($sheet, 0, 28)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
    $zip->close();
    $isi = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $isi;
}

/* ------------------------------------------------------------------ */
/* Struk (PDF thermal 80 mm)                                           */
/* ------------------------------------------------------------------ */

/**
 * Struk ukuran kertas thermal 80 mm (226.77 pt), tinggi mengikuti isi.
 */
function struk_pdf(array $d): string
{
    $lebar = 226.77;                       /* 80 mm */
    $tepi  = 10.0;
    $isiW  = $lebar - $tepi * 2;

    $tinggi = 150.0 + count($d['items']) * 16.0;
    if ((float) $d['diskon'] > 0) {
        $tinggi += 12;
    }
    if ((float) $d['service'] > 0) {
        $tinggi += 12;
    }
    if ((float) $d['pajak'] > 0) {
        $tinggi += 12;
    }
    $tinggi += 90;

    $pdf = new SimplePdf($lebar, $tinggi);
    $pdf->addPage();

    $gelap  = [40, 28, 20];
    $abu    = [120, 105, 92];
    $coklat = [122, 74, 42];

    $y = 18.0;
    $tengah = $lebar / 2;

    $pdf->text($tengah, $y, nama_kafe(), 13, true, $gelap, 'center', 0);
    $y += 13;
    if (setting('alamat_kafe') !== '') {
        foreach (explode("\n", wordwrap(setting('alamat_kafe'), 40, "\n")) as $baris) {
            $pdf->text($tengah, $y, $baris, 7.5, false, $abu, 'center', 0);
            $y += 9;
        }
    }
    if (setting('telepon_kafe') !== '') {
        $pdf->text($tengah, $y, 'Telp ' . setting('telepon_kafe'), 7.5, false, $abu, 'center', 0);
        $y += 10;
    }
    $y += 4;
    $pdf->line($tepi, $y, $lebar - $tepi, $y, $abu, 0.6);
    $y += 12;
    $pdf->text($tengah, $y, $d['struk_judul'], 9.5, true, $gelap, 'center', 0);
    $y += 14;

    $barisInfo = [
        ['No. Pesanan', $d['kode']],
        ['Waktu', tgl_label(hari_ini(), true) . ' ' . $d['waktu']],
        ['Meja', $d['meja']],
        ['Tipe', $d['sumber'] === SUMBER_PELANGGAN ? 'Pesan mandiri (QR)' : 'Kasir'],
    ];
    if ($d['nama'] !== '') {
        $barisInfo[] = ['Pelanggan', $d['nama']];
    }
    $barisInfo[] = ['Pembayaran', $d['metode_label'] . ' (' . ($d['status_bayar'] === BAYAR_LUNAS ? 'LUNAS' : 'BELUM DIBAYAR') . ')'];
    if ($d['kasir'] !== '') {
        $barisInfo[] = ['Kasir', $d['kasir']];
    }
    foreach ($barisInfo as $info) {
        $pdf->text($tepi, $y, $info[0], 7.5, false, $abu, 'left', $isiW);
        $pdf->text($tepi, $y, $pdf->fit((string) $info[1], 7.5, $isiW * 0.62 - 4), 7.5, false, $gelap, 'right', $isiW);
        $y += 10.5;
    }
    $y += 3;
    $pdf->line($tepi, $y, $lebar - $tepi, $y, $abu, 0.6);
    $y += 12;

    foreach ($d['items'] as $it) {
        $pdf->text($tepi, $y, $pdf->fit((string) $it['nama'], 8, $isiW), 8, true, $gelap, 'left', $isiW);
        $y += 10;
        $kiri  = angka_qty($it['qty']) . ' x ' . $it['harga'];
        $kanan = (string) $it['subtotal'];
        $pdf->text($tepi, $y, $pdf->fit($kiri, 7.5, $isiW * 0.6 - 4), 7.5, false, $abu, 'left', $isiW);
        $pdf->text($tepi, $y, $kanan, 7.5, false, $gelap, 'right', $isiW);
        $y += 10;
        if ((string) $it['catatan'] !== '') {
            $pdf->text($tepi + 6, $y, $pdf->fit('* ' . $it['catatan'], 7, $isiW - 6), 7, false, $coklat, 'left', $isiW);
            $y += 9;
        }
        $y += 2;
    }

    $y += 2;
    $pdf->line($tepi, $y, $lebar - $tepi, $y, $abu, 0.6);
    $y += 11;

    $ruas = [['Subtotal', $d['subtotal']]];
    if ((float) $d['diskon'] > 0) {
        $ruas[] = ['Diskon', '-' . $d['diskon']];
    }
    if ((float) $d['service'] > 0) {
        $ruas[] = ['Service', $d['service']];
    }
    if ((float) $d['pajak'] > 0) {
        $ruas[] = ['Pajak', $d['pajak']];
    }
    foreach ($ruas as $r) {
        $pdf->text($tepi, $y, $r[0], 8, false, $gelap, 'left', $isiW);
        $pdf->text($tepi, $y, $r[1], 8, false, $gelap, 'right', $isiW);
        $y += 11;
    }
    $y += 3;
    $pdf->rect($tepi, $y - 9, $isiW, 16, [246, 236, 222]);
    $pdf->text($tepi + 3, $y + 2, 'TOTAL', 9.5, true, $gelap, 'left', $isiW - 6);
    $pdf->text($tepi + 3, $y + 2, (string) $d['total'], 9.5, true, $coklat, 'right', $isiW - 6);
    $y += 20;
    if ((float) $d['dibayar'] > 0) {
        $pdf->text($tepi, $y, 'Diterima', 8, false, $gelap, 'left', $isiW);
        $pdf->text($tepi, $y, (string) $d['dibayar'], 8, false, $gelap, 'right', $isiW);
        $y += 11;
        $pdf->text($tepi, $y, 'Kembalian', 8, false, $gelap, 'left', $isiW);
        $pdf->text($tepi, $y, (string) $d['kembali'], 8, false, $gelap, 'right', $isiW);
        $y += 11;
    }
    if ((float) $d['poin'] > 0) {
        $pdf->text($tepi, $y, 'Poin didapat', 8, false, $coklat, 'left', $isiW);
        $pdf->text($tepi, $y, angka_qty($d['poin']) . ' poin', 8, false, $coklat, 'right', $isiW);
        $y += 11;
    }
    if ($d['status_bayar'] !== BAYAR_LUNAS) {
        $y += 4;
        $pdf->rect($tepi, $y - 9, $isiW, 16, [252, 240, 224]);
        $pdf->text($tengah, $y + 2, 'BELUM DIBAYAR — silakan bayar di kasir', 8, true, [150, 80, 20], 'center', 0);
        $y += 20;
    }

    $y += 6;
    $pdf->line($tepi, $y, $lebar - $tepi, $y, $abu, 0.5);
    $y += 12;
    if ((string) $d['struk_catatan'] !== '') {
        foreach (explode("\n", wordwrap((string) $d['struk_catatan'], 42, "\n")) as $baris) {
            $pdf->text($tengah, $y, $baris, 7.5, false, $gelap, 'center', 0);
            $y += 9.5;
        }
    }
    $y += 4;
    $pdf->text($tengah, $y, 'Dicetak ' . date('d/m/Y H:i'), 6.5, false, $abu, 'center', 0);

    return $pdf->output();
}

/* ------------------------------------------------------------------ */
/* Laporan penjualan (PDF A4)                                          */
/* ------------------------------------------------------------------ */

function laporan_pdf(string $dari, string $sampai, array $rows, array $ringkas, array $produk, array $opt = []): string
{
    $pdf = new SimplePdf();
    $lebar = $pdf->width();
    $mx = 34.0;
    $isiW = $lebar - $mx * 2;

    $coklat = [122, 74, 42];
    $gelap  = [40, 28, 20];
    $abu    = [120, 105, 92];
    $cream  = [246, 238, 226];

    $halaman = 1;
    $pdf->addPage();

    $kepala = function (SimplePdf $pdf, int $halaman) use ($mx, $isiW, $coklat, $gelap, $abu, $dari, $sampai, $ringkas): float {
        $y = 34.0;
        $pdf->text($mx, $y, nama_kafe(), 15, true, $gelap);
        $y += 15;
        $pdf->text($mx, $y, (string) setting('alamat_kafe'), 8, false, $abu);
        $y += 16;
        $pdf->rect($mx, $y - 11, $isiW, 28, $coklat);
        $pdf->text($mx + 10, $y + 7, 'LAPORAN PENJUALAN', 12, true, [255, 255, 255]);
        $pdf->text($mx + $isiW - 10, $y + 7, tgl_label($dari) . ' s/d ' . tgl_label($sampai), 8.5, false, [255, 255, 255], 'right', 0);
        $y += 34;
        $ket = 'Transaksi lunas: ' . (int) $ringkas['transaksi']
             . '   |   Total penjualan: ' . rupiah($ringkas['total'])
             . '   |   Rata-rata/transaksi: ' . rupiah($ringkas['rata']);
        $pdf->text($mx, $y, $ket, 8.5, false, $gelap);
        $y += 7;
        $pdf->text($mx, $y, 'Dicetak: ' . date('d/m/Y H:i') . '  ·  Halaman ' . $halaman, 7.5, false, $abu);
        $y += 14;
        return $y;
    };
    $y = $kepala($pdf, $halaman);
    $batas = $pdf->height() - 56;

    /* --- Ringkasan per metode & kategori --- */
    $pdf->text($mx, $y, 'Ringkasan', 11, true, $gelap);
    $y += 14;
    foreach ($ringkas['per_metode'] as $m => $v) {
        $pdf->text($mx + 4, $y, 'Pembayaran ' . (LABEL_METODE[$m] ?? $m), 8.5, false, $gelap);
        $pdf->text($mx + $isiW, $y, rupiah($v), 8.5, true, $gelap, 'right', 0);
        $y += 12;
    }
    foreach ($produk['kategori'] as $k) {
        $pdf->text($mx + 4, $y, 'Kategori ' . $k['nama'], 8.5, false, $gelap);
        $pdf->text($mx + $isiW, $y, rupiah($k['total']) . '  (' . angka_qty($k['qty']) . ' item)', 8.5, false, $gelap, 'right', 0);
        $y += 12;
    }
    $y += 10;

    $kol = [['Tanggal', 58, 'left'], ['Kode', 44, 'left'], ['Meja', 52, 'left'], ['Pelanggan', 96, 'left'],
            ['Bayar', 52, 'left'], ['Kasir', 62, 'left'], ['Item', 34, 'right'], ['Total', 60, 'right']];
    $gambarHeader = function (float $y) use ($pdf, $kol, $mx, $cream, $coklat): float {
        $pdf->rect($mx, $y, array_sum(array_column($kol, 1)), 15, $cream);
        $x = $mx;
        foreach ($kol as [$judul, $w, $align]) {
            $pdf->text($x + 3, $y + 10, (string) $judul, 7.6, true, $coklat, $align === 'right' ? 'right' : 'left', $w - 6);
            $x += $w;
        }
        return $y + 15;
    };
    $pdf->text($mx, $y, 'Daftar Transaksi', 11, true, $gelap);
    $y += 12;
    $y = $gambarHeader($y);

    $no = 0;
    foreach ($rows as $r) {
        if ($y > $batas) {
            $halaman++;
            $pdf->addPage();
            $y = $kepala($pdf, $halaman);
            $y = $gambarHeader($y);
        }
        $no++;
        $jmlItem = 0;
        foreach (pesanan_items((int) $r['id']) as $it) {
            $jmlItem += (float) $it['qty'];
        }
        $nilai = [
            tgl_label((string) $r['tanggal'], false),
            (string) $r['kode'],
            pesanan_label_meja($r),
            $r['nama_pelanggan'] !== '' ? (string) $r['nama_pelanggan'] : '-',
            (string) $r['status_bayar'] === BAYAR_LUNAS ? (LABEL_METODE[(string) $r['metode_bayar']] ?? '') : 'BELUM',
            nama_pengguna((string) ($r['bayar_oleh'] !== '' ? $r['bayar_oleh'] : $r['dibuat_oleh'])),
            angka_qty($jmlItem),
            rupiah($r['total'], false),
        ];
        $pdf->rect($mx, $y, array_sum(array_column($kol, 1)), 14, $no % 2 === 0 ? [252, 249, 244] : [255, 255, 255], [236, 226, 212], 0.4);
        $x = $mx;
        foreach ($kol as $i => [$judul, $w, $align]) {
            $t = $pdf->fit($nilai[$i], 7.4, $w - 6);
            $pdf->text($x + 3, $y + 9.5, $t, 7.4, false, $gelap, $align === 'right' ? 'right' : 'left', $w - 6);
            $x += $w;
        }
        $y += 14;
    }
    if (!$rows) {
        $pdf->text($mx + 4, $y + 12, 'Belum ada transaksi lunas pada periode ini.', 9, false, $abu);
        $y += 24;
    }

    /* --- Produk terlaris --- */
    if (!empty($produk['produk'])) {
        if ($y > $batas - 60) {
            $halaman++;
            $pdf->addPage();
            $y = $kepala($pdf, $halaman);
        }
        $y += 16;
        $pdf->text($mx, $y, 'Produk Terjual', 11, true, $gelap);
        $y += 14;
        $pdf->rect($mx, $y, $isiW, 15, $cream);
        $pdf->text($mx + 4, $y + 10, 'Produk', 7.6, true, $coklat);
        $pdf->text($mx + 300, $y + 10, 'Jumlah', 7.6, true, $coklat, 'right', 60);
        $pdf->text($mx + $isiW - 4, $y + 10, 'Nilai', 7.6, true, $coklat, 'right', 80);
        $y += 15;
        foreach (array_slice($produk['produk'], 0, 30) as $p) {
            $pdf->line($mx, $y, $mx + $isiW, $y, [236, 226, 212], 0.4);
            $pdf->text($mx + 4, $y + 9.5, $pdf->fit($p['nama'], 8, 260), 8, false, $gelap);
            $pdf->text($mx + 360, $y + 9.5, angka_qty($p['qty']), 8, false, $gelap, 'right', 60);
            $pdf->text($mx + $isiW - 4, $y + 9.5, rupiah($p['total'], false), 8, false, $gelap, 'right', 80);
            $y += 13;
        }
    }

    return $pdf->output();
}
