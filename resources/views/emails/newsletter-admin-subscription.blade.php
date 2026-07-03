<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subjectLine }}</title>
    @include('emails.partials.newsletter-styles')
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f7fb" style="width:100%;max-width:100%;background-color:#f4f7fb;">
        <tr>
            <td class="outer-pad" align="center" style="padding:24px 16px;">
                <table role="presentation" class="container" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:16px;border:1px solid #e8edf3;overflow:hidden;">
                    @include('emails.partials.newsletter-header', [
                        'siteName' => $siteName,
                        'headline' => $verified ? 'Subscription verified' : 'New newsletter subscription',
                        'previewText' => null,
                    ])

                    <tr>
                        <td class="content-pad" style="padding:28px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
                                <tr>
                                    <td style="padding:24px 20px;">
                                        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                            @if ($verified)
                                                A subscriber has verified their email and is ready to receive newsletters.
                                            @else
                                                Someone subscribed to the newsletter. Verification is still pending.
                                            @endif
                                        </p>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f8fafc" style="width:100%;background-color:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
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

                    @include('emails.partials.newsletter-footer', [
                        'siteName' => $siteName,
                        'showUnsubscribe' => false,
                        'preferencesUrl' => '',
                        'unsubscribeUrl' => '',
                    ])
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
