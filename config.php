
<?php

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_CHAT_ID', '');

// Baca akun SMTP dari file JSON
$fileSMTP = __DIR__ . '/smtp_accounts.json';

if (!is_file($fileSMTP)) {
    throw new RuntimeException('File smtp_accounts.json tidak ditemukan.');
}

$jsonSMTP = file_get_contents($fileSMTP);

$akunSMTP = json_decode($jsonSMTP, true);

if (!is_array($akunSMTP) || empty($akunSMTP)) {
    throw new RuntimeException('Konfigurasi SMTP JSON tidak valid.');
}

// Validasi data akun
foreach ($akunSMTP as $id => $akun) {
    if (
        !is_array($akun) ||
        empty($akun['nama']) ||
        empty($akun['email']) ||
        empty($akun['password']) ||
        !filter_var($akun['email'], FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException(
            'Data akun SMTP tidak valid: ' . $id
        );
    }
}
