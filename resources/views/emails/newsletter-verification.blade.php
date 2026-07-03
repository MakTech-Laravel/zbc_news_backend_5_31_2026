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
                        'headline' => 'Confirm your subscription',
                        'previewText' => null,
                    ])

                    <tr>
                        <td class="content-pad" style="padding:28px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
                                <tr>
                                    <td style="padding:24px 20px;">
                                        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;color:#374151;">
                                            Thanks for subscribing to {{ $siteName }}. Please confirm your email address to start receiving our newsletter.
                                        </p>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 24px;">
                                            <tr>
                                                <td align="center">
                                                    <a href="{{ $verifyUrl }}" class="cta-button" style="display:inline-block;padding:14px 28px;background-color:#51a2ff;color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;border-radius:8px;">
                                                        Verify subscription
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f8fafc" style="width:100%;margin:0;background-color:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                                            <tr>
                                                <td style="padding:16px;">
                                                    <p style="margin:0 0 8px;font-size:13px;line-height:1.5;color:#99a1af;text-transform:uppercase;letter-spacing:0.06em;font-weight:600;">
                                                        Button not working?
                                                    </p>
                                                    <p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;word-break:break-all;">
                                                        Copy and paste this link into your browser:<br>
                                                        <a href="{{ $verifyUrl }}" style="color:#2563eb;text-decoration:underline;">{{ $verifyUrl }}</a>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 0;font-size:14px;line-height:1.6;color:#99a1af;">
                                If you did not subscribe to our newsletter, you can safely ignore this email.
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
