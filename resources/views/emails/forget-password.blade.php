<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password OTP</title>
</head>
<body style="margin:0; padding:0; background:#f2f5fb; font-family:Segoe UI, Arial, sans-serif; color:#101828;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f2f5fb; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; width:100%; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e4e7ec; box-shadow:0 8px 28px rgba(16,24,40,0.08);">
                    <tr>
                        <td style="padding:26px 28px; background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 100%); color:#ffffff;">
                            <div style="font-size:12px; letter-spacing:1.2px; text-transform:uppercase; color:#dbeafe; margin-bottom:8px;">Password Reset</div>
                            <h1 style="margin:0; font-size:24px; line-height:1.3; font-weight:700;">Your OTP Code</h1>
                            <p style="margin:10px 0 0; font-size:14px; line-height:1.6; color:#dbeafe;">
                                Hello {{ $user->name ?: 'User' }}, use the OTP below to continue resetting your password.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px; text-align:center;">
                            <div style="display:inline-block; min-width:220px; padding:18px 24px; border-radius:16px; background:#eff6ff; border:1px solid #bfdbfe;">
                                <div style="font-size:12px; letter-spacing:0.8px; text-transform:uppercase; color:#2563eb; font-weight:700; margin-bottom:10px;">OTP Code</div>
                                <div style="font-size:34px; font-weight:800; letter-spacing:6px; color:#0f172a;">{{ $otp }}</div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #eaecf0; border-radius:12px; overflow:hidden; border-collapse:separate; border-spacing:0;">
                                <tr>
                                    <td style="padding:14px 16px; width:40%; background:#f8fafc; color:#475467; font-size:13px; font-weight:600; border-bottom:1px solid #eaecf0;">Email</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#101828; border-bottom:1px solid #eaecf0;">{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#475467; font-size:13px; font-weight:600;">Expires At</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#101828;">{{ $expiresAt }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 0;">
                            <p style="margin:0; font-size:15px; line-height:1.8; color:#344054;">
                                If you did not request this reset, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 28px 28px;">
                            <div style="padding:14px 16px; border-radius:10px; border:1px dashed #d0d5dd; background:#fcfcfd; font-size:12px; line-height:1.7; color:#667085;">
                                This OTP is generated automatically by Tarkaven and is valid for a limited time.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
