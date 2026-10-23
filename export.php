
<?php
session_start();

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function berhenti($pesan)
{
    http_response_code(400);
    exit(htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8'));
}

// Validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    berhenti('Metode request tidak valid.');
}

// Validasi token CSRF
if (
    empty($_SESSION['csrf_token']) ||
    empty($_POST['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {
    berhenti('Token tidak valid. Silakan ulangi proses.');
}

// Pastikan laporan tersedia
if (
    empty($_SESSION['laporan_email']) ||
    !is_array($_SESSION['laporan_email'])
) {
    berhenti('Data laporan tidak ditemukan. Silakan kirim email terlebih dahulu.');
}

$laporan = $_SESSION['laporan_email'];

// Buat workbook Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Pengiriman');

// Header
$sheet->fromArray(
    ['Nama Perusahaan', 'Alamat', 'Email', 'Status','Grup','Waktu'],
    null,
    'A1'
);

// Isi data
$baris = 2;

foreach ($laporan as $data) {
    $sheet->fromArray([
        $data['nama_perusahaan'] ?? '',
        $data['alamat'] ?? '',
        $data['email'] ?? '',
        $data['status'] ?? '',
        $data['grup'] ?? '',
        $data['waktu_pengiriman'] ?? ''

    ], null, 'A' . $baris);

    $baris++;
}

// Format header
$sheet->getStyle('A1:F1')->getFont()->setBold(true);

// Lebar kolom otomatis
foreach (range('A', 'F') as $kolom) {
    $sheet->getColumnDimension($kolom)->setAutoSize(true);
}

// Aktifkan filter
$sheet->setAutoFilter(
    'A1:F' . ($baris - 1)
);

// Pastikan tidak ada output sebelum file Excel dikirim
if (headers_sent($file, $line)) {
    exit("Output sudah dikirim di $file baris $line");
}

// Buang output buffer agar file XLSX tidak tercampur HTML
while (ob_get_level() > 0) {
    ob_end_clean();
}

$namaFile = 'pengiriman_email_' . date('Ymd_His') . '.xlsx';

try {
    $writer = new Xlsx($spreadsheet);

    // Buat file Excel ke memori terlebih dahulu
    ob_start();
    $writer->save('php://output');
    $excelData = ob_get_clean();

    if (
        $excelData === false ||
        strlen($excelData) < 4 ||
        substr($excelData, 0, 2) !== 'PK'
    ) {
        throw new RuntimeException(
            'Data Excel tidak valid.'
        );
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Content-Length: ' . strlen($excelData));
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo $excelData;

} catch (Throwable $e) {
    error_log('Export Excel gagal: ' . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo 'Gagal membuat file Excel. Periksa log PHP.';
}

$spreadsheet->disconnectWorksheets();
exit;