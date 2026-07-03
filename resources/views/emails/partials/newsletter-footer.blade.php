<tr>
    <td class="footer-pad" bgcolor="#fafbfc" style="padding:20px 24px 24px;border-top:1px solid #eef2f7;background-color:#fafbfc;border-radius:0 0 16px 16px;">
        @if (!empty($showUnsubscribe))
            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#6b7280;text-align:center;">
                <a href="{{ $preferencesUrl }}" style="color:#2563eb;text-decoration:underline;">Manage preferences</a>
                &nbsp;&middot;&nbsp;
                <a href="{{ $unsubscribeUrl }}" style="color:#2563eb;text-decoration:underline;">Unsubscribe</a>
            </p>
        @endif
        <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#99a1af;text-align:center;">
            Sent by {{ $siteName }}
        </p>
        <p style="margin:0;font-size:12px;line-height:1.5;color:#b4bac5;text-align:center;">
            You are receiving this email because you subscribed to our newsletter.
        </p>
    </td>
</tr>
