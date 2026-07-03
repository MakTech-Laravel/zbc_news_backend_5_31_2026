<tr>
    <td class="header-pad" bgcolor="#51a2ff" style="background-color:#51a2ff;padding:28px 24px;border-radius:16px 16px 0 0;">
        <p style="margin:0;font-size:12px;line-height:1.4;letter-spacing:0.08em;text-transform:uppercase;color:#e8f3ff;">
            {{ $siteName }}
        </p>
        <h1 class="heading" style="margin:8px 0 0;font-size:26px;line-height:1.3;font-weight:700;color:#ffffff;">
            {{ $headline }}
        </h1>
        @if (!empty($previewText))
            <p style="margin:12px 0 0;font-size:15px;line-height:1.5;color:#e8f3ff;">
                {{ $previewText }}
            </p>
        @endif
    </td>
</tr>
