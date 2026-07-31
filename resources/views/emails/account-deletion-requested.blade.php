<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
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
                                Account deletion request received
                            </h1>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                @if(!empty($userName))
                                    Hi {{ $userName }},
                                @else
                                    Hello,
                                @endif
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                We received your request to delete your {{ $siteName }} account. Your account is disabled immediately and you have been signed out.
                            </p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                You have a <strong>{{ $graceDays }}-day grace period</strong>. If you do not submit a cancellation request, your account and personal data will be permanently deleted or anonymized on
                                <strong>{{ $finalDeletionDate }}</strong>.
                            </p>
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.7;color:#374151;">
                                To keep your account, use the button below to send a <strong>cancellation request to an administrator</strong>. An admin must review and restore your account before you can sign in again. If you submit a cancel request, your account will not be permanently deleted until an admin reviews it.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $cancelUrl }}" style="display:inline-block;padding:14px 24px;background-color:#51a2ff;color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;border-radius:8px;">
                                            Request cancellation (admin review)
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                If the button does not work, open this link:<br>
                                <a href="{{ $cancelUrl }}" style="color:#2563eb;word-break:break-all;">{{ $cancelUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
