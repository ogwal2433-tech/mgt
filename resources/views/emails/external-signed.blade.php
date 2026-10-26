<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $company['name'] }} — Signed copy</title>
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
                            <p style="margin:0; font-size:12px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#16a34a;">✓ Submitted successfully</p>
                            <h1 style="margin:10px 0 0; font-size:25px; line-height:1.2; letter-spacing:-0.025em; font-weight:800; color:#141a1f;">
                                Thank you, {{ $name }}!
                            </h1>
                            <p style="margin:14px 0 0; font-size:15px; line-height:1.7; color:#47535d;">
                                Your signed requisition form <strong style="color:#141a1f;">{{ $requisition->requisition_number }}</strong>
                                has been received. The completed form is attached to this email for your records.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px; background-color:#f7f8f8; border:1px solid #eef0f1; border-radius:12px;">
                                <tr>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#8b98a1; width:42%;">Estimated total</td>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:13px; font-weight:600; color:#232b32;">{{ number_format((float) $requisition->total_amount, 2) }} {{ $requisition->currency }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#8b98a1;">Actual total</td>
                                    <td style="padding:12px 18px; border-bottom:1px solid #eef0f1; font-size:13px; font-weight:600; color:#232b32;">{{ number_format((float) $requisition->actual_total, 2) }} {{ $requisition->currency }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 18px; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#8b98a1;">Unspent funds</td>
                                    <td style="padding:12px 18px; font-size:13px; font-weight:600; color:#232b32;">{{ number_format((float) $requisition->unspent_funds, 2) }} {{ $requisition->currency }}</td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0; font-size:13px; line-height:1.7; color:#47535d;">
                                Our team will process your requisition and the mode of payment you selected
                                (<strong>{{ ucwords(str_replace('_', ' ', (string) $requisition->mode_of_payment)) }}</strong>).
                                You will be contacted if anything else is needed.
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
