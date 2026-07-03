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
                        'headline' => $title,
                        'previewText' => $previewText ?? null,
                    ])

                    <tr>
                        <td class="content-pad" style="padding:28px 24px;">
                            @if (!empty($subscriberName))
                                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#6b7280;">
                                    Hello {{ $subscriberName }},
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
                                <tr>
                                    <td style="padding:24px 20px;">
                                        <div class="newsletter-content" style="font-family:Arial,Helvetica,sans-serif;">
                                            {!! $content !!}
                                        </div>
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
