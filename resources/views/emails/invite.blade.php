<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $company['name'] }} — Your account invitation</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f7; font-family: 'Plus Jakarta Sans', 'Inter', 'Segoe UI', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f7; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,0.04), 0 16px 40px -16px rgba(16,24,40,0.14);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#0b1014; background-image: radial-gradient(420px 200px at 12% 20%, rgba(34,197,94,0.25), transparent 60%), radial-gradient(380px 220px at 92% 90%, rgba(16,185,129,0.18), transparent 62%); padding:32px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width:44px; height:44px; background-color:#16a34a; border-radius:12px; text-align:center; vertical-align:middle; font-size:20px; font-weight:800; color:#ffffff; line-height:44px;">
                                                    B
                                                </td>
                                                <td style="padding-left:12px;">
                                                    <p style="margin:0; font-size:20px; font-weight:800; letter-spacing:-0.02em; color:#ffffff;">Biiteek</p>
                                                    <p style="margin:2px 0 0; font-size:11px; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:#86efac;">Gain Digital Advantage</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:28px;">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:#4ade80;">Management System</p>
                                        <h1 style="margin:10px 0 0; font-size:28px; line-height:1.15; letter-spacing:-0.025em; font-weight:800; color:#ffffff;">
                                            Hello {{ $name ?? 'there' }},
                                        </h1>
                                        <p style="margin:12px 0 0; font-size:15px; line-height:1.65; color:#c3cbd0;">
                                            You have been invited to join the <strong style="color:#ffffff;">{{ $company['name'] }}</strong> Management System.
                                            Your account is ready — below are your temporary sign-in details.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Credentials -->
                    <tr>
                        <td style="padding:36px 40px 8px;">
                            <p style="margin:0 0 14px; font-size:12px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#61707c;">
                                Your temporary credentials
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8f8; border:1px solid #eef0f1; border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid #eef0f1;">
                                        <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:#8b98a1;">Username</p>
                                        <p style="margin:6px 0 0; font-family: 'JetBrains Mono', 'Consolas', monospace; font-size:16px; font-weight:600; color:#141a1f;">{{ $username }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:#8b98a1;">Temporary password</p>
                                        <p style="margin:6px 0 0; display:inline-block; padding:8px 14px; background-color:#141a1f; border-radius:8px; font-family: 'JetBrains Mono', 'Consolas', monospace; font-size:16px; font-weight:600; letter-spacing:0.04em; color:#4ade80;">
                                            {{ $temporaryPassword }}
                                        </p>
                                    </td>
                                </tr>
                                @if(!empty($roleNames))
                                <tr>
                                    <td style="padding:14px 20px; border-top:1px solid #eef0f1;">
                                        <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:#8b98a1;">Access</p>
                                        <p style="margin:6px 0 0; font-size:14px; font-weight:600; color:#37414a;">{{ $roleNames }}</p>
                                    </td>
                                </tr>
                                @endif
                            </table>

                            <!-- CTA -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $loginUrl }}" style="display:inline-block; padding:15px 36px; background-color:#16a34a; color:#ffffff; font-size:15px; font-weight:700; letter-spacing:-0.01em; text-decoration:none; border-radius:10px; box-shadow:0 6px 16px -6px rgba(22,163,74,0.5);">
                                            Sign in to your account
                                        </a>
                                        <p style="margin:14px 0 0; font-size:12px; color:#8b98a1;">
                                            or copy this link: <a href="{{ $loginUrl }}" style="color:#16a34a; font-weight:600; text-decoration:none;">{{ $loginUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Steps -->
                    <tr>
                        <td style="padding:28px 40px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdf4; border:1px solid #dcfce7; border-radius:12px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 12px; font-size:13px; font-weight:700; color:#14532d;">
                                            What happens next
                                        </p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="width:26px; vertical-align:top; padding-bottom:10px;">
                                                    <span style="display:inline-block; width:22px; height:22px; line-height:22px; text-align:center; background-color:#16a34a; color:#ffffff; font-size:12px; font-weight:700; border-radius:999px;">1</span>
                                                </td>
                                                <td style="vertical-align:top; padding-bottom:10px; font-size:13px; line-height:1.6; color:#166534;">
                                                    Sign in with the username and temporary password above.
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="width:26px; vertical-align:top; padding-bottom:10px;">
                                                    <span style="display:inline-block; width:22px; height:22px; line-height:22px; text-align:center; background-color:#16a34a; color:#ffffff; font-size:12px; font-weight:700; border-radius:999px;">2</span>
                                                </td>
                                                <td style="vertical-align:top; padding-bottom:10px; font-size:13px; line-height:1.6; color:#166534;">
                                                    <strong>You will be asked to set a new password</strong> immediately — the temporary password works only once.
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="width:26px; vertical-align:top;">
                                                    <span style="display:inline-block; width:22px; height:22px; line-height:22px; text-align:center; background-color:#16a34a; color:#ffffff; font-size:12px; font-weight:700; border-radius:999px;">3</span>
                                                </td>
                                                <td style="vertical-align:top; font-size:13px; line-height:1.6; color:#166534;">
                                                    You are in — your dashboard, requisitions and documents are ready.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Security note -->
                    <tr>
                        <td style="padding:20px 40px 36px;">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#8b98a1;">
                                If you were not expecting this invitation, please ignore this email or contact your administrator.
                                Keep your credentials private — accounts are personal and must never be shared.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f7f8f8; border-top:1px solid #eef0f1; padding:22px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <p style="margin:0; font-size:12px; font-weight:700; color:#37414a;">{{ $company['name'] }}</p>
                                        <p style="margin:4px 0 0; font-size:11px; color:#8b98a1;">Innovation, Security, Digital Excellence</p>
                                    </td>
                                    <td style="text-align:right;">
                                        <p style="margin:0; font-size:11px; color:#8b98a1;">
                                            {{ $company['email'] ?? '' }}
                                        </p>
                                        <p style="margin:4px 0 0; font-size:11px; color:#8b98a1;">
                                            This is an automated message from the Management System.
                                        </p>
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
