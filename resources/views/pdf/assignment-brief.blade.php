<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $assignment->title }}</title>
</head>
<body style="font-family: 'Helvetica', 'Arial', sans-serif; color: #141a1f; margin: 0; padding: 0;">

    <!-- Header -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0b1014;">
        <tr>
            <td style="padding: 26px 40px;">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 38px; height: 38px; background-color: #16a34a; border-radius: 8px; text-align: center; vertical-align: middle; color: #ffffff; font-size: 17px; font-weight: bold;">
                            B
                        </td>
                        <td style="padding-left: 12px;">
                            <p style="margin: 0; color: #ffffff; font-size: 16px; font-weight: bold;">{{ $company['name'] ?? 'Biiteek Global' }}</p>
                            <p style="margin: 2px 0 0; color: #86efac; font-size: 9px; letter-spacing: 2px; text-transform: uppercase;">Assignment Brief</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 32px 40px 8px;">
                <p style="margin: 0; color: #16a34a; font-size: 10px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;">Assignment</p>
                <h1 style="margin: 8px 0 0; font-size: 22px; line-height: 1.3; color: #141a1f;">{{ $assignment->title }}</h1>
            </td>
        </tr>

        <!-- Meta -->
        <tr>
            <td style="padding: 16px 40px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb;">
                    <tr>
                        <td style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb; background-color: #f7f8f8; width: 32%; font-size: 10px; font-weight: bold; color: #8b98a1; text-transform: uppercase; letter-spacing: 1px;">Reference</td>
                        <td style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb; font-size: 12px; color: #232b32;">{{ $assignment->reference_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb; background-color: #f7f8f8; font-size: 10px; font-weight: bold; color: #8b98a1; text-transform: uppercase; letter-spacing: 1px;">Issued by</td>
                        <td style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb; font-size: 12px; color: #232b32;">{{ $assignment->creator?->displayName() ?? $company['name'] ?? 'Biiteek Global' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 16px; background-color: #fef3c7; font-size: 10px; font-weight: bold; color: #92400e; text-transform: uppercase; letter-spacing: 1px;">Due date</td>
                        <td style="padding: 10px 16px; background-color: #fef3c7; font-size: 13px; font-weight: bold; color: #78350f;">{{ $assignment->due_at?->format('D d M Y, H:i') }} &mdash; submit before this time</td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 24px 40px 0;">
                <p style="margin: 0 0 10px; font-size: 11px; font-weight: bold; color: #8b98a1; text-transform: uppercase; letter-spacing: 1px;">Assignment details</p>
                <div style="font-size: 12px; line-height: 1.75; color: #47535d;">{!! nl2br(e($assignment->body)) !!}</div>
            </td>
        </tr>

        @if($assignment->parts->isNotEmpty())
        <!-- Parts -->
        <tr>
            <td style="padding: 26px 40px 0;">
                <p style="margin: 0 0 10px; font-size: 11px; font-weight: bold; color: #8b98a1; text-transform: uppercase; letter-spacing: 1px;">What to submit</p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb;">
                    @foreach($assignment->parts as $i => $part)
                    <tr>
                        <td style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb; width: 40px; text-align: center; background-color: #f7f8f8; font-size: 12px; font-weight: bold; color: #16a34a;">{{ $i + 1 }}</td>
                        <td style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb; font-size: 12px; color: #232b32;">{{ $part->title }}</td>
                    </tr>
                    @endforeach
                </table>
            </td>
        </tr>
        @endif

        <!-- Requirements -->
        <tr>
            <td style="padding: 26px 40px 0;">
                <p style="margin: 0 0 10px; font-size: 11px; font-weight: bold; color: #8b98a1; text-transform: uppercase; letter-spacing: 1px;">Submission rules</p>
                <ul style="margin: 0; padding-left: 18px; font-size: 12px; line-height: 1.9; color: #47535d;">
                    <li>Deliverable files: {{ $assignment->require_deliverables ? 'REQUIRED — at least one document must be uploaded' : 'optional' }}</li>
                    <li>A digital signature is required on every submission</li>
                    <li>Submissions are blocked after the due date shown above</li>
                    <li>Upload your deliverables on the secure assignment page linked in your invitation email</li>
                </ul>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="margin: 0; font-size: 10px; color: #8b98a1;">
                    Generated by the {{ $company['name'] ?? 'Biiteek Global' }} Management System on {{ now()->format('d M Y, H:i') }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
