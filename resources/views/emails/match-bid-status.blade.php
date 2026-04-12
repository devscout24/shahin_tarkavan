<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Bid Status</title>
</head>
<body style="margin:0; padding:0; background:#0b1220; font-family:Segoe UI, Arial, sans-serif; color:#101828;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:linear-gradient(180deg,#08111f 0%,#111827 100%); padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="max-width:680px; width:100%; background:#ffffff; border-radius:20px; overflow:hidden; border:1px solid #e5e7eb; box-shadow:0 18px 42px rgba(2,6,23,0.24);">
                    <tr>
                        <td style="padding:28px 30px; background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%); color:#fff;">
                            <div style="font-size:12px; letter-spacing:1.4px; text-transform:uppercase; color:#cbd5e1; margin-bottom:10px;">Match Bid Update</div>
                            <h1 style="margin:0; font-size:26px; line-height:1.3;">{{ $headline }}</h1>
                            <p style="margin:12px 0 0; font-size:14px; line-height:1.7; color:#dbe4f0;">{{ $bodyMessage }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 30px 0;">
                            <span style="display:inline-block; padding:8px 14px; border-radius:999px; font-size:12px; font-weight:700; background:{{ $statusBg }}; color:{{ $statusColor }}; text-transform:uppercase; letter-spacing:0.8px;">
                                Status: {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 30px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; border-collapse:separate; border-spacing:0;">
                                <tr>
                                    <td style="padding:14px 16px; width:36%; background:#f8fafc; font-size:13px; font-weight:600; color:#475467;">Match Date</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#111827;">{{ $bid->match?->available_date ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; font-size:13px; font-weight:600; color:#475467;">Location</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#111827;">{{ $bid->match?->location ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; font-size:13px; font-weight:600; color:#475467;">Your Club</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#111827;">{{ $bid->requestedClub?->club_name ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; font-size:13px; font-weight:600; color:#475467;">Opponent Club</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#111827;">{{ $bid->createdClub?->club_name ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; background:#f8fafc; font-size:13px; font-weight:600; color:#475467;">Match Team</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#111827;">{{ $bid->match?->clubTeam?->name ?: 'N/A' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 30px 0;">
                            <div style="border-radius:14px; background:#f8fafc; border:1px solid #e5e7eb; padding:16px 18px;">
                                <div style="font-size:12px; letter-spacing:0.8px; text-transform:uppercase; color:#6b7280; font-weight:700; margin-bottom:8px;">Message</div>
                                <div style="font-size:14px; line-height:1.75; color:#111827;">{{ $bodyMessage }}</div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 30px 30px;">
                            <div style="font-size:12px; line-height:1.7; color:#6b7280; background:#fcfcfd; border:1px dashed #d0d5dd; border-radius:12px; padding:14px 16px;">
                                This is an automated notification from Tarkaven. If you have questions, contact the platform administrator.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
