<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:24px 16px;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:16px;border:1px solid #e8edf3;">
        <tr>
            <td style="background-color:#51a2ff;padding:24px;border-radius:16px 16px 0 0;">
                <p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#e8f3ff;">{{ $siteName }}</p>
                <h1 style="margin:8px 0 0;font-size:22px;line-height:1.3;color:#ffffff;">
                    {{ $verified ? 'Subscription verified' : 'New newsletter subscription' }}
                </h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#374151;">
                    @if ($verified)
                        A subscriber has verified their email and is ready to receive newsletters.
                    @else
                        Someone subscribed to the newsletter. Verification is still pending.
                    @endif
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                    <tr>
                        <td style="padding:16px;font-size:14px;line-height:1.7;color:#4b5563;">
                            <strong>Email:</strong> {{ $subscriber->email }}<br>
                            @if ($subscriber->name)
                                <strong>Name:</strong> {{ $subscriber->name }}<br>
                            @endif
                            <strong>Source:</strong> {{ $subscriber->source ?? 'website' }}<br>
                            <strong>Categories:</strong> {{ $categories }}<br>
                            <strong>Status:</strong> {{ $subscriber->status }}
                        </td>
                    </tr>
                </table>

                <p style="margin:24px 0 0;text-align:center;">
                    <a href="{{ $adminUrl }}" style="display:inline-block;padding:12px 24px;background-color:#51a2ff;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;border-radius:8px;">
                        Manage subscribers
                    </a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
