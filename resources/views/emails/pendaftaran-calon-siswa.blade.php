<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akun Pendaftaran Calon Siswa</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;color:#111827;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px 28px;background:#0a2342;color:#ffffff;">
                            <p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#c7d2fe;">{{ $schoolName }}</p>
                            <h1 style="margin:6px 0 0;font-size:22px;line-height:1.25;">Akun Pendaftaran Calon Siswa</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="margin:0 0 12px;">Halo <strong>{{ $user->name }}</strong>,</p>
                            <p style="margin:0 0 18px;">
                                Akun pendaftaran Anda sudah dibuat dengan nomor
                                <strong>{{ $calon->nomor_pendaftaran }}</strong>. Setelah masuk, lengkapi data administrasi
                                dan unggah dokumen supaya pendaftaran Anda tercatat lengkap.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                                <tr>
                                    <td style="border-radius:8px;background:#123d76;">
                                        <a href="{{ $tautanMasuk }}" style="display:inline-block;padding:12px 20px;color:#ffffff;text-decoration:none;font-weight:700;">
                                            Masuk ke Akun
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 22px;color:#4b5563;font-size:12px;">
                                Tautan di atas hanya bisa dipakai satu kali dan berlaku {{ $berlakuHari }} hari.
                            </p>

                            <p style="margin:0 0 18px;">
                                <strong>Langkah berikutnya:</strong> buka menu <em>Data Administrasi Pendaftaran</em> untuk
                                mengisi NIK, KK, data orang tua, alamat, serta mengunggah Kartu Keluarga dan ijazah.
                            </p>

                            <p style="margin:0 0 10px;font-weight:700;">
                                Bila tautan sudah kedaluwarsa, masuk manual dengan akun berikut
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;width:42%;color:#4b5563;font-size:12px;">Email Login</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;font-weight:700;">{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;color:#4b5563;font-size:12px;">Kata Sandi Sementara</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;font-weight:700;letter-spacing:.04em;">{{ $password }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 14px;color:#4b5563;font-size:12px;">Nomor Pendaftaran</td>
                                    <td style="padding:10px 14px;font-weight:700;">{{ $calon->nomor_pendaftaran }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px;color:#4b5563;">
                                Halaman masuk: <a href="{{ route('filament.siswa.auth.login') }}" style="color:#123d76;">{{ route('filament.siswa.auth.login') }}</a>
                            </p>
                            <p style="margin:0 0 16px;color:#4b5563;">
                                Halaman status pendaftaran:<br>
                                <a href="{{ \App\Filament\Siswa\Pages\StatusPendaftaran::getUrl(panel: 'siswa') }}" style="color:#123d76;">{{ \App\Filament\Siswa\Pages\StatusPendaftaran::getUrl(panel: 'siswa') }}</a>
                            </p>
                            <p style="margin:0;color:#6b7280;font-size:12px;">
                                Jangan bagikan nama pengguna atau kata sandi ini kepada orang lain. Jika Anda tidak
                                merasa mendaftar, abaikan email ini atau hubungi admin sekolah.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;background:#f9fafb;color:#6b7280;font-size:12px;">
                            Email ini dikirim otomatis oleh sistem {{ $schoolName }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
