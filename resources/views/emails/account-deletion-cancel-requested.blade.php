<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f7fb" style="width:100%;background-color:#f4f7fb;">
        <tr>
            <td align="center" style="padding:24px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:12px;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:28px 24px;">
                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#111827;">
                                Cancellation request received
                            </h1>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                @if(!empty($userName))
                                    Hi {{ $userName }},
                                @else
                                    Hello,
                                @endif
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                We received your request to cancel the deletion of your {{ $siteName }} account.
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                An administrator will review your request. Your account stays disabled until an admin restores it. Permanent deletion is paused while the request is under review.
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                You will receive another email when your account is restored.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
