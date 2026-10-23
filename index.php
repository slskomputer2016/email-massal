
<?php
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Massal - KAP HSE</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f7;
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 15px #ddd;
        }

        h2 {
            text-align: center;
            color: #17365d;
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 18px;
        }

        input, textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        textarea {
            min-height: 380px;
            resize: vertical;
        }

        button {
            margin-top: 22px;
            width: 100%;
            padding: 13px;
            border: none;
            background: #1769aa;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #104e80;
        }

        .info {
            color: #555;
            font-size: 13px;
            line-height: 1.6;
        }

        input, textarea, select {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #ccc;
            border-radius: 5px;
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
</head>

<body>
<div class="container">

    <h2>Kirim Email Massal</h2>
    <div class="petunjuk">
        <strong>Petunjuk Umum :</strong>
        <p>
            Disesuaikan dg kemampuan server, sekali pengiriman maksimal 50 penerima (50 baris excel).
        </p>
        <p>
            Utk menghindari pemblokiran, satu email pengirim maksimal 150 kirim email/hari.
        </p>
        <p>
            Jika ada fitur/modul yang kurang mangga diinfokan ke Ketua Tim.
        </p>

    </div>

<!--     <p class="info">
        Format Excel: kolom A berisi alamat email,
        dengan judul kolom "Email" pada baris pertama.
        Kirim hanya kepada penerima yang telah menyetujui
        atau secara sah mengharapkan email Anda.
    </p> -->

    <form action="proses.php"
          method="POST"
          enctype="multipart/form-data">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <label>File Excel Daftar Email</label>
        <input type="file"
               name="file_excel"
               accept=".xlsx,.xls"
               required>
        <div class="petunjuk">
            <strong>Petunjuk Format File Excel:</strong>
            <p>
                Baris pertama adalah judul kolom dg urutan sbb :
            </p>
            <ol>
                <li>Kolom A: Nama Perusahaan</li>
                <li>Kolom B: Alamat</li>
                <li>Kolom C: Email</li>
            </ol>

            <p>
                Jika alamat perusahaan tidak ada kosongkan saja, tapi kolom alamat tetap ada.
            </p>
        </div>
        <label>Pilih Email Pengirim</label>

        <select name="akun_smtp" required>
            <?php foreach ($akunSMTP as $id => $akun): ?>
                <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(
                        $akun['email'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Grup/Kelompok</label>
        <input type="text"
               name="grup"
               maxlength="200"
               placeholder=""
               autocomplete="off" 
               required>
        <p class="info">
            BPR/Rumah Sakit/Universitas/dll.
        </p>

        <label>Subjek Email</label>
        <input type="text"
               name="subject"
               maxlength="200"
               placeholder=""
               value="Penawaran Jasa Akuntan Publik" 
               required>
        <label>Lampiran PDF</label>
        <input type="file"
               name="file_pdf"
               accept=".pdf,application/pdf"
               required>

        <p class="info">
            Maksimal ukuran file: 10 MB.
        </p>

        <label>Isi Pesan Email (Silahkan disesuaikan)</label>
<textarea name="pesan" required>
Yth. Pimpinan {{nama_perusahaan}}

{{alamat}}

Dengan hormat,

Kami dari KAP Hendro Syukron Edy bermaksud memperkenalkan layanan jasa audit laporan keuangan kepada {{nama_perusahaan}}.

Kami berharap dapat menjalin kerja sama yang baik dan memberikan layanan profesional sesuai kebutuhan perusahaan Bapak/Ibu.

Sebagai bahan pertimbangan, bersama email ini kami lampirkan proposal jasa audit dalam format PDF.

Atas perhatian dan kesempatan yang diberikan, kami ucapkan terima kasih.

Hormat kami,

KAP HSE
Jasa Akuntan Publik
</textarea>

        <div class="petunjuk">
            <strong>Petunjuk penulisan:</strong><br>
            Gunakan <code>{{nama_perusahaan}}</code> untuk menampilkan
            nama perusahaan dan <code>{{alamat}}</code> untuk menampilkan
            alamat secara otomatis berdasarkan data dari Excel. (Optional)
        </div>



        <button type="submit"
                onclick="return confirm(
                    'Apakah data - data sudah yakin benar ?'
                )">
        Bismillah, Semoga pecah telor.
        </button>

    </form>
</div>
</body>
</html>
