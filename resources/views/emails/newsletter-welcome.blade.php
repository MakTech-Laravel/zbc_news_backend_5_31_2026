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
                        'headline' => 'Welcome to our newsletter',
                        'previewText' => null,
                    ])

                    <tr>
                        <td class="content-pad" style="padding:28px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
                                <tr>
                                    <td style="padding:24px 20px;">
                                        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                            @if(!empty($subscriberName))
                                                Hi {{ $subscriberName }},
                                            @else
                                                Hello,
                                            @endif
                                        </p>
                                        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;color:#374151;">
                                            Your subscription to {{ $siteName }} is confirmed. You will now receive our latest news and updates.
                                        </p>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 8px;">
                                            <tr>
                                                <td align="center">
                                                    <a href="{{ $homeUrl }}" class="cta-button" style="display:inline-block;padding:14px 28px;background-color:#51a2ff;color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;border-radius:8px;">
                                                        Visit {{ $siteName }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @include('emails.partials.newsletter-footer', [
                        'siteName' => $siteName,
                        'showUnsubscribe' => true,
                        'preferencesUrl' => $preferencesUrl,
                        'unsubscribeUrl' => $unsubscribeUrl,
                    ])
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
