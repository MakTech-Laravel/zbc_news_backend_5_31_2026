<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply from {{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;background:#1d4ed8;color:#ffffff;">
                            <h1 style="margin:0;font-size:20px;line-height:1.4;">{{ $siteName }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                Hello {{ $recipientName }},
                            </p>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                                Thank you for contacting {{ $siteName }}. Here is our reply to your inquiry
                                @if (!empty($originalSubject))
                                    regarding <strong>{{ $originalSubject }}</strong>
                                @endif
                                :
                            </p>
                            <div style="margin:0 0 20px;padding:16px;background:#f8fafc;border-left:4px solid #1d4ed8;border-radius:8px;">
                                <p style="margin:0;white-space:pre-wrap;font-size:15px;line-height:1.7;">{{ $replyBody }}</p>
                            </div>
                            @if (!empty($originalMessage))
                                <p style="margin:0 0 8px;font-size:13px;font-weight:bold;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">
                                    Your original message
                                </p>
                                <div style="margin:0;padding:14px;background:#f9fafb;border-radius:8px;">
                                    <p style="margin:0;white-space:pre-wrap;font-size:14px;line-height:1.6;color:#4b5563;">{{ $originalMessage }}</p>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f8fafc;font-size:12px;line-height:1.6;color:#6b7280;">
                            This email was sent in response to your contact form submission at {{ $siteName }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
