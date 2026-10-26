<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $company['name'] }} — Requisition form</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f7; font-family: 'Plus Jakarta Sans', 'Inter', 'Segoe UI', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f7; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,0.04), 0 16px 40px -16px rgba(16,24,40,0.14);">

                    <tr>
                        <td style="background-color:#0b1014; background-image: radial-gradient(420px 200px at 12% 20%, rgba(34,197,94,0.25), transparent 60%); padding:28px 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="width:40px; height:40px; background-color:#16a34a; border-radius:11px; text-align:center; vertical-align:middle; font-size:18px; font-weight:800; color:#ffffff; line-height:40px;">B</td>
                                    <td style="padding-left:12px;">
                                        <p style="margin:0; font-size:18px; font-weight:800; letter-spacing:-0.02em; color:#ffffff;">Biiteek</p>
                                        <p style="margin:2px 0 0; font-size:10px; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:#86efac;">Reliable, Fast &amp; Secure</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:36px 40px 12px;">
                            <p style="margin:0; font-size:12px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#61707c;">Requisition / Accountability form</p>
                            <h1 style="margin:10px 0 0; font-size:25px; line-height:1.2; letter-spacing:-0.025em; font-weight:800; color:#141a1f;">
                                Hello {{ $name }},
                            </h1>
                            <p style="margin:14px 0 0; font-size:15px; line-height:1.7; color:#47535d;">
                                <strong style="color:#141a1f;">{{ $company['name'] }}</strong> has prepared requisition form
                                <strong style="color:#141a1f;">{{ $requisition->requisition_number }}</strong> for you.
                                Please open the form below, confirm the details, fill in the actual costs where required,
                                <strong>sign it digitally</strong> and submit it back to us.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px; background-color:#f7f8f8; border:1px solid #eef0f1; border-radius:12px;">
                                <tr>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#8b98a1; width:42%;">Form number</td>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:13px; font-weight:600; color:#232b32;">{{ $requisition->requisition_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#8b98a1;">Estimated total</td>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:13px; font-weight:600; color:#232b32;">{{ number_format((float) $requisition->total_amount, 2) }} {{ $requisition->currency }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#8b98a1;">Link valid until</td>
                                    <td style="padding:12px 18px; font-size:13px; font-weight:600; color:#232b32;">{{ $requisition->token_expires_at?->format('d M Y, H:i') }}</td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:26px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $link }}" style="display:inline-block; padding:15px 36px; background-color:#16a34a; color:#ffffff; font-size:15px; font-weight:700; letter-spacing:-0.01em; text-decoration:none; border-radius:10px; box-shadow:0 6px 16px -6px rgba(22,163,74,0.5);">
                                            Open form &amp; sign
                                        </a>
                                        <p style="margin:14px 0 0; font-size:12px; color:#8b98a1;">No account needed — the link opens your form directly.</p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:26px;">
                                <tr>
                                    <td style="padding:16px 20px; background-color:#f0fdf4; border:1px solid #dcfce7; border-radius:12px;">
                                        <p style="margin:0; font-size:12px; line-height:1.8; color:#166534;">
                                            <strong>What to do:</strong><br>
                                            1. Open the form and check the item details.<br>
                                            2. Enter the actual cost for each item.<br>
                                            3. Choose your preferred mode of payment.<br>
                                            4. Sign in the signature box and submit — a signed copy is emailed back to you immediately.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0; font-size:12px; color:#8b98a1;">
                                Link not working? Copy this into your browser:<br>
                                <a href="{{ $link }}" style="color:#16a34a; font-weight:600; text-decoration:none; word-break:break-all;">{{ $link }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 40px 32px;">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#8b98a1;">
                                If you were not expecting this form, please ignore this email or contact your Biiteek representative.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f7f8f8; border-top:1px solid #eef0f1; padding:20px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <p style="margin:0; font-size:12px; font-weight:700; color:#37414a;">{{ $company['name'] }}</p>
                                        <p style="margin:4px 0 0; font-size:11px; color:#8b98a1;">Reliable, Fast &amp; Secure</p>
                                    </td>
                                    <td style="text-align:right;">
                                        <p style="margin:0; font-size:11px; color:#8b98a1;">{{ $company['email'] ?? '' }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
