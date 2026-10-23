
<?php

function kirimLaporanTelegram(
    string $emailPengirim,
    int $totalEmail,
    int $berhasil,
    int $gagal,
    string $grup
): bool {

    date_default_timezone_set('Asia/Jakarta');

    $pesan = 
        "---------------------------------------------------\n"
        . "Report Pengiriman Email\n\n"
        . "Grup {$grup}\n"
        . "{$emailPengirim}\n"
        . "Total - {$totalEmail}\n"
        . "Berhasil - {$berhasil}\n"
        . "Gagal - {$gagal}\n"
        . "Bismillah, semoga pecah telor.\n"
        . date('d-m-Y H:i:s') . " Wib\n"
        ."---------------------------------------------------";

    $url = 'https://api.telegram.org/bot'
        . TELEGRAM_BOT_TOKEN
        . '/sendMessage';

    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $pesan
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $respons = curl_exec($ch);
    $error = curl_error($ch);
    $statusHTTP = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($respons === false || $statusHTTP !== 200) {
        error_log(
            'Gagal mengirim laporan Telegram: '
            . ($error ?: (string) $respons)
        );

        return false;
    }

    $hasil = json_decode($respons, true);

    return !empty($hasil['ok']);
}
