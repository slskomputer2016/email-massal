
<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';


use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\IOFactory;

set_time_limit(0);

function h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function berhenti($pesan)
{
    http_response_code(400);
    exit('<h3>' . h($pesan) .
         '</h3><p><a href="index.php">Kembali</a></p>');
}

// Validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    berhenti('Request tidak valid.');
}

// Validasi token CSRF
if (
    empty($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    berhenti('Form tidak valid atau sesi telah berakhir.');
}







// Ambil akun SMTP yang dipilih dari form
$idSMTP = $_POST['akun_smtp'] ?? '';

if (
    !is_string($idSMTP) ||
    !isset($akunSMTP[$idSMTP])
) {
    berhenti('Akun SMTP tidak valid. Silakan pilih akun pengirim.');
}

// Simpan akun yang dipilih
$akunTerpilih = $akunSMTP[$idSMTP];



// Validasi input
$subject = trim($_POST['subject'] ?? '');
$pesan   = trim($_POST['pesan'] ?? '');
$grup   = trim($_POST['grup'] ?? '');

if ($subject === '' || $pesan === '' || $grup === '') {
    berhenti('Subjek dan pesan wajib diisi.');
}

if (mb_strlen($subject) > 200) {
    berhenti('Subjek maksimal 200 karakter.');
}





// Validasi file Excel
if (
    !isset($_FILES['file_excel']) ||
    $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK
) {
    berhenti('File Excel gagal diunggah.');
}

$excel = $_FILES['file_excel'];

if ($excel['size'] > MAX_FILE_SIZE) {
    berhenti('Ukuran Excel melebihi batas 10 MB.');
}

$extExcel = strtolower(
    pathinfo($excel['name'], PATHINFO_EXTENSION)
);

if (!in_array($extExcel, ['xlsx', 'xls'], true)) {
    berhenti('File daftar email harus berupa XLSX atau XLS.');
}

// Validasi file PDF
if (
    !isset($_FILES['file_pdf']) ||
    $_FILES['file_pdf']['error'] !== UPLOAD_ERR_OK
) {
    berhenti('File PDF gagal diunggah.');
}

$pdf = $_FILES['file_pdf'];

if ($pdf['size'] > MAX_FILE_SIZE) {
    berhenti('Ukuran PDF melebihi batas 10 MB.');
}

if (
    strtolower(pathinfo($pdf['name'], PATHINFO_EXTENSION)) !== 'pdf'
) {
    berhenti('Lampiran harus berupa file PDF.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$pdfMime = $finfo->file($pdf['tmp_name']);

if ($pdfMime !== 'application/pdf') {
    berhenti('Isi file lampiran bukan PDF yang valid.');
}


try {
    $spreadsheet = IOFactory::load($excel['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();

    $barisTerakhir = $sheet->getHighestDataRow();
    $daftarPenerima = [];
    $emailTidakValid = 0;

    // Mulai dari baris 2 karena baris 1 adalah header
    for ($i = 2; $i <= $barisTerakhir; $i++) {

        $nama = trim(
            (string) $sheet->getCell('A' . $i)->getValue()
        );

        $alamat = trim(
            (string) $sheet->getCell('B' . $i)->getValue()
        );

        $email = trim(
            (string) $sheet->getCell('C' . $i)->getValue()
        );

        if ($email === '') {
            continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailTidakValid++;
            continue;
        }

        // Menghindari email duplikat
        $key = strtolower($email);

        $daftarPenerima[$key] = [
            'nama_perusahaan' => $nama,
            'alamat' => $alamat,
            'email' => $email
        ];
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    $daftarPenerima = array_values($daftarPenerima);

} catch (Throwable $e) {
    berhenti('File Excel tidak dapat dibaca.');
}

if (count($daftarPenerima) === 0) {
    berhenti('Tidak ditemukan alamat email yang valid.');
}





// Konfigurasi SMTP
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = SMTP_HOST;
$mail->SMTPAuth   = true;
$mail->Username   = $akunTerpilih['email'];
$mail->Password   = $akunTerpilih['password'];
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = SMTP_PORT;
$mail->CharSet    = 'UTF-8';
$mail->Timeout    = 30;

// Hasil pengiriman
$berhasil = 0;
$gagal = 0;
$hasil = [];

try {
	$mail->setFrom(
	    $akunTerpilih['email'],
	    $akunTerpilih['nama']
	);

    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body    = $pesan;
    $mail->addAttachment(
        $pdf['tmp_name'],
        basename($pdf['name']),
        PHPMailer::ENCODING_BASE64,
        'application/pdf'
    );


	foreach ($daftarPenerima as $penerima) {

	    $email = $penerima['email'];
	    $nama  = $penerima['nama_perusahaan'];
	    $alamat = $penerima['alamat'];

	    try {
	        $mail->clearAddresses();

	        // Personalisasi isi email
	        $body = str_replace(
	            ['{{nama_perusahaan}}', '{{alamat}}'],
	            [$nama, $alamat],
	            $pesan
	        );

	        $mail->addAddress($email);
	        $mail->Subject = $subject;
	        $mail->Body = $body;

	        $mail->send();

	        $berhasil++;

			$hasil[] = [
			    'nama_perusahaan' => $nama,
			    'alamat' => $alamat,
			    'email' => $email,
			    'grup' => $grup,
			    'status' => 'Berhasil',
			    'waktu_pengiriman' => date('Y-m-d H:i:s')
			];

	    } catch (Throwable $e) {
	        $gagal++;

			$hasil[] = [
			    'nama_perusahaan' => $nama,
			    'alamat' => $alamat,
			    'email' => $email,
			    'grup' => $grup,
			    'status' => 'Gagal',
			    'waktu_pengiriman' => date('Y-m-d H:i:s')
			];

	        error_log(
	            'Gagal mengirim email ke ' .
	            $email . ': ' . $mail->ErrorInfo
	        );
	    }

	    usleep(500000);
	}


} catch (Throwable $e) {
    error_log('Kesalahan konfigurasi email: ' . $e->getMessage());
    berhenti('Terjadi kesalahan SMTP. Periksa konfigurasi Gmail dan log server.');
}


/* LOG PENGIRIMAN EMAIL */
$waktu = date('Y-m-d H:i:s');
$totalEmail = count($daftarPenerima);
$log = sprintf(
    "[%s] %s - %s - %s - %d - %d - %d",
    $waktu,
    $grup,
    $akunTerpilih['email'],
    //str_replace(["\r", "\n"], ' ', $subject),
    $totalEmail,
    $berhasil,
    $gagal,
    PHP_EOL
);

$fileLog = __DIR__ . '/kirim_email.log';
$hasilLog = file_put_contents(
    $fileLog,
    $log,
    FILE_APPEND | LOCK_EX
);

if ($hasilLog === false) {
    error_log('Gagal menulis file kirim_email.log');
}


kirimLaporanTelegram(
    $akunTerpilih['email'],
    count($daftarPenerima),
    $berhasil,
    $gagal,
    $grup
);

// Simpan laporan untuk fitur export Excel
$_SESSION['laporan_email'] = $hasil;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Pengiriman Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 25px;
            background: #f4f6f8;
        }

        .container {
            max-width: 850px;
            margin: auto;
            padding: 25px;
            background: white;
            border-radius: 8px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            overflow-wrap: anywhere;
        }

        th {
            background: #eee;
        }

        .ok {
            color: #16803c;
        }

        .fail {
            color: #c62828;
        }

        a {
            display: inline-block;
            margin-top: 20px;
        }

        .petunjuk {
            margin-top: 6px;
            padding: 8px;
            background: #f2f6fa;
            border-left: 3px solid #1769aa;
            font-size: 11px;
            line-height: 1.3;
            color: #444;
        }

        .petunjuk p {
            margin: 3px 0;
        }

        .petunjuk ol {
            margin: 3px 0;
            padding-left: 20px;
        }

        .petunjuk li {
            margin: 1px 0;
        }
    </style>
    </style>
</head>
<body>
<div class="container">

    <h2>Hasil Pengiriman Email</h2>
    <div class="petunjuk">
    	<p class="ok">Grup/kelompok: <strong><?= $grup ?></strong></p>

	    <p>Total email valid dan unik:
	        <strong><?= count($daftarPenerima) ?></strong>
	    </p>
	    <p class="ok">Berhasil: <strong><?= $berhasil ?></strong></p>
	    <p class="fail">Gagal: <strong><?= $gagal ?></strong></p>
	    <p>Email tidak valid yang dilewati:
	        <strong><?= $emailTidakValid ?></strong>
	    </p>
    </div><br><br>

	<form action="export.php" method="POST">
	    <input
	        type="hidden"
	        name="csrf_token"
	        value="<?= h($_SESSION['csrf_token']) ?>"
	    >

	    <button type="submit">
	        Export Report ke Excel
	    </button>
	</form>

    <table>
        <thead>
        <tr>
            <th>No.</th>
            <th>Alamat Email</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($hasil as $no => $item): ?>
            <tr>
                <td><?= $no + 1 ?></td>
                <td><?= h($item['email']) ?></td>
                <td class="<?= $item['status'] === 'Berhasil'
                    ? 'ok' : 'fail' ?>">
                    <?= h($item['status']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php">Kembali ke Form Email</a>

</div>
</body>
</html>
