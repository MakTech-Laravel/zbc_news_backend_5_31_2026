<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subjectLine }}</title>
    <style type="text/css">
        body,
        table,
        td,
        p,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
            mso-table-lspace: 0;
            mso-table-rspace: 0;
        }

        .wrapper {
            width: 100% !important;
            max-width: 100% !important;
        }

        .container {
            width: 100% !important;
            max-width: 560px !important;
        }

        @media only screen and (max-width: 600px) {
            .outer-pad {
                padding: 16px 12px !important;
            }

            .header-pad {
                padding: 20px 16px !important;
            }

            .content-pad {
                padding: 20px 16px !important;
            }

            .footer-pad {
                padding: 16px 16px 20px !important;
            }

            .heading {
                font-size: 20px !important;
            }

            .cta-button {
                display: block !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f7fb" style="width:100%;max-width:100%;background-color:#f4f7fb;">
        <tr>
            <td class="outer-pad" align="center" style="padding:24px 16px;">
                <table role="presentation" class="container" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;max-width:560px;background-color:#ffffff;border-radius:16px;border:1px solid #e8edf3;">
                    <tr>
                        <td class="header-pad" bgcolor="#51a2ff" style="background-color:#51a2ff;padding:28px 24px;">
                            <p style="margin:0;font-size:12px;line-height:1.4;letter-spacing:0.08em;text-transform:uppercase;color:#e8f3ff;">
                                {{ $siteName }}
                            </p>
                            <h1 class="heading" style="margin:8px 0 0;font-size:24px;line-height:1.3;font-weight:700;color:#ffffff;">
                                Confirm your subscription
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-pad" style="padding:28px 24px;">
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#374151;">
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

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f8fafc" style="width:100%;margin:0 0 24px;background-color:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 8px;font-size:13px;line-height:1.5;color:#99a1af;text-transform:uppercase;letter-spacing:0.06em;font-weight:600;">
                                            Button not working?
                                        </p>
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;word-break:break-all;">
                                            Copy and paste this link into your browser:<br>
                                            <a href="{{ $verifyUrl }}" style="color:#51a2ff;text-decoration:underline;">{{ $verifyUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0;font-size:14px;line-height:1.6;color:#99a1af;">
                                If you did not subscribe to our newsletter, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="footer-pad" bgcolor="#fafbfc" style="padding:20px 24px 24px;border-top:1px solid #eef2f7;background-color:#fafbfc;">
                            <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#99a1af;">
                                Sent by {{ $siteName }}
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#b4bac5;">
                                This is an automated message. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
