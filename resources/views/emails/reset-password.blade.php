<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $company['name'] }} — Reset your password</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f7; font-family: 'Plus Jakarta Sans', 'Inter', 'Segoe UI', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f7; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,0.04), 0 16px 40px -16px rgba(16,24,40,0.14);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#0b1014; background-image: radial-gradient(420px 200px at 12% 20%, rgba(34,197,94,0.25), transparent 60%); padding:28px 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="width:40px; height:40px; background-color:#16a34a; border-radius:11px; text-align:center; vertical-align:middle; font-size:18px; font-weight:800; color:#ffffff; line-height:40px;">
                                        B
                                    </td>
                                    <td style="padding-left:12px;">
                                        <p style="margin:0; font-size:18px; font-weight:800; letter-spacing:-0.02em; color:#ffffff;">Biiteek</p>
                                        <p style="margin:2px 0 0; font-size:10px; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:#86efac;">Gain Digital Advantage</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:36px 40px 12px;">
                            <p style="margin:0; font-size:12px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#61707c;">
                                Account security
                            </p>
                            <h1 style="margin:10px 0 0; font-size:26px; line-height:1.2; letter-spacing:-0.025em; font-weight:800; color:#141a1f;">
                                Hello {{ $name ?? 'there' }},
                            </h1>
                            <p style="margin:14px 0 0; font-size:15px; line-height:1.7; color:#47535d;">
                                We received a request to reset the password for your <strong style="color:#141a1f;">{{ $company['name'] }}</strong>
                                Management System account. Tap the button below to choose a new password.
                            </p>

                            <!-- CTA -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $actionUrl }}" style="display:inline-block; padding:15px 36px; background-color:#16a34a; color:#ffffff; font-size:15px; font-weight:700; letter-spacing:-0.01em; text-decoration:none; border-radius:10px; box-shadow:0 6px 16px -6px rgba(22,163,74,0.5);">
                                            Reset my password
                                        </a>
                                        <p style="margin:14px 0 0; font-size:12px; color:#8b98a1;">
                                            This link can be used once and expires in {{ $expiresIn }} minutes.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security note -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                                <tr>
                                    <td style="padding:16px 20px; background-color:#f7f8f8; border:1px solid #eef0f1; border-radius:12px;">
                                        <p style="margin:0; font-size:12px; line-height:1.7; color:#61707c;">
                                            If you did not request a password reset, you can safely ignore this email — your password
                                            will remain unchanged. Never share this link with anyone.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0; font-size:12px; color:#8b98a1;">
                                Having trouble with the button? Copy this link into your browser:<br>
                                <a href="{{ $actionUrl }}" style="color:#16a34a; font-weight:600; text-decoration:none; word-break:break-all;">{{ $actionUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 40px 32px;">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#8b98a1;">
                                This is an automated message from the {{ $company['name'] }} Management System.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f7f8f8; border-top:1px solid #eef0f1; padding:20px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <p style="margin:0; font-size:12px; font-weight:700; color:#37414a;">{{ $company['name'] }}</p>
                                        <p style="margin:4px 0 0; font-size:11px; color:#8b98a1;">Innovation, Security, Digital Excellence</p>
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
