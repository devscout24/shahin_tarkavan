<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment Status Update</title>
</head>
<body style="margin:0; padding:0; background:#f2f5fb; font-family:Segoe UI, Arial, sans-serif; color:#101828;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f2f5fb; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; width:100%; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e4e7ec; box-shadow:0 8px 28px rgba(16,24,40,0.08);">
                    <tr>
                        <td style="padding:26px 28px; background:linear-gradient(135deg,#0b1220 0%,#18253f 100%); color:#ffffff;">
                            <div style="font-size:12px; letter-spacing:1.2px; text-transform:uppercase; color:#c7d7ff; margin-bottom:8px;">Recruitment Update</div>
                            <h1 style="margin:0; font-size:24px; line-height:1.3; font-weight:700;">{{ $statusHeadline }}</h1>
                            <p style="margin:10px 0 0; font-size:14px; line-height:1.6; color:#d9e4ff;">
                                Hello {{ $applicantName ?: 'Applicant' }}, below is the latest update on your recruitment application.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 28px 8px;">
                            <span style="display:inline-block; padding:8px 14px; border-radius:999px; font-size:13px; font-weight:700; background:{{ $statusBg }}; color:{{ $statusColor }}; border:1px solid rgba(16,24,40,0.06);">
                                Status: {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 28px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:separate; border-spacing:0; border:1px solid #eaecf0; border-radius:12px; overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 16px; width:40%; background:#f8fafc; color:#475467; font-size:13px; font-weight:600; border-bottom:1px solid #eaecf0;">Applicant Name</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#101828; border-bottom:1px solid #eaecf0;">{{ $applicantName ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#475467; font-size:13px; font-weight:600; border-bottom:1px solid #eaecf0;">Team</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#101828; border-bottom:1px solid #eaecf0;">{{ $teamName ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#475467; font-size:13px; font-weight:600; border-bottom:1px solid #eaecf0;">Tryout Date</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#101828; border-bottom:1px solid #eaecf0;">{{ $tryoutDate ?: 'To be announced' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; color:#475467; font-size:13px; font-weight:600;">Club</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#101828;">{{ $clubName ?: 'Tarkaven Club' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 8px;">
                            <p style="margin:0; font-size:15px; line-height:1.8; color:#344054;">
                                {{ $actionMessage }}
                            </p>
                        </td>
                    </tr>

                    @if($isScheduled)
                    <tr>
                        <td style="padding:6px 28px 8px;">
                            <div style="padding:14px 16px; border-radius:10px; border:1px solid #bfd4ff; background:#f2f7ff;">
                                <div style="font-size:12px; color:#1f4db8; text-transform:uppercase; letter-spacing:0.8px; font-weight:700; margin-bottom:6px;">Scheduled Tryout</div>
                                <div style="font-size:15px; color:#101828; font-weight:600; line-height:1.6;">
                                    {{ $tryoutDate ?: 'Date will be shared soon' }}
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td style="padding:22px 28px 28px;">
                            <div style="padding:14px 16px; border-radius:10px; border:1px dashed #d0d5dd; background:#fcfcfd; font-size:12px; line-height:1.7; color:#667085;">
                                This is an automated email from Tarkaven. If you need support, please contact the platform administrator.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
