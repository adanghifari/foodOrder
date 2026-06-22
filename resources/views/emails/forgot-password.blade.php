<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode Verifikasi Lupa Password - KedaiKlik</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1f2937;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f5; padding: 40px 10px;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 500px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #d9a066, #a0522d); padding: 30px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;">KedaiKlik</h1>
                            <p style="color: #fce8d5; margin: 5px 0 0 0; font-size: 14px;">Atur Ulang Password Anda</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.5; color: #374151;">Halo {{ $username }},</p>
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.5; color: #4b5563;">Kami menerima permintaan untuk mereset password akun KedaiKlik Anda. Silakan gunakan kode verifikasi (OTP) di bawah ini untuk melanjutkan:</p>
                            
                            <!-- OTP Box -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td align="center" style="background-color: #fdf8f5; border: 2px dashed #d9a066; border-radius: 8px; padding: 15px;">
                                        <span style="font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #a0522d; font-family: monospace;">{{ $otp }}</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5; color: #6b7280; text-align: center;">Kode OTP ini berlaku selama <strong>15 menit</strong>. Jangan bagikan kode ini kepada siapa pun.</p>
                            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #9ca3af; text-align: center;">Jika Anda tidak meminta perubahan ini, Anda dapat mengabaikan email ini dengan aman.</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; border-top: 1px solid #f3f4f6; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {{ date('Y') }} KedaiKlik. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
