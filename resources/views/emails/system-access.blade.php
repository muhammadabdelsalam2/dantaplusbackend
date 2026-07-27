<!doctype html>
<html>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:Arial, Helvetica, sans-serif; color:#111827;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; background-color:#F5F7FB; margin:0; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; max-width:640px; background-color:#FFFFFF; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(17,24,39,0.08);">
                <tr>
                    <td style="background-color:#6C5CE7; padding:28px 32px; text-align:left;">
                        <div style="font-size:28px; line-height:34px; font-weight:700; color:#FFFFFF; letter-spacing:0;">Denta+</div>
                        <div style="font-size:14px; line-height:22px; color:#EDEBFF; margin-top:4px;">Your account access is ready</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 12px; font-size:18px; line-height:28px; font-weight:700; color:#111827;">Hello {{ $recipientName }},</p>
                        <p style="margin:0 0 24px; font-size:15px; line-height:24px; color:#4B5563;">
                            Your Denta+ account has been created successfully. Use the credentials below to sign in.
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; background-color:#F3F4F6; border:1px solid #E5E7EB; border-radius:12px; margin:0 0 24px;">
                            <tr>
                                <td style="padding:18px 20px;">
                                    <div style="font-size:13px; line-height:18px; color:#6B7280; font-weight:700; text-transform:uppercase; margin-bottom:12px;">Credentials</div>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;">
                                        <tr>
                                            <td style="padding:10px 0; font-size:14px; line-height:22px; color:#6B7280; width:110px;">Email</td>
                                            <td style="padding:10px 0; font-size:15px; line-height:22px; color:#111827; font-weight:700; word-break:break-word;">{{ $email }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px 0; font-size:14px; line-height:22px; color:#6B7280; width:110px; border-top:1px solid #E5E7EB;">Password</td>
                                            <td style="padding:10px 0; font-size:15px; line-height:22px; color:#111827; font-weight:700; border-top:1px solid #E5E7EB; word-break:break-word;">{{ $plainPassword }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                            <tr>
                                <td style="padding:0 10px 10px 0;">
                                    <a href="{{ $systemLink }}" style="display:inline-block; background-color:#6C5CE7; color:#FFFFFF; text-decoration:none; font-size:15px; line-height:20px; font-weight:700; padding:13px 22px; border-radius:8px;">Open Denta+</a>
                                </td>
                                @if($dashboardLink)
                                    <td style="padding:0 0 10px 0;">
                                        <a href="{{ $dashboardLink }}" style="display:inline-block; background-color:#FFFFFF; color:#6C5CE7; text-decoration:none; font-size:15px; line-height:20px; font-weight:700; padding:12px 20px; border-radius:8px; border:1px solid #6C5CE7;">Open Dashboard</a>
                                    </td>
                                @endif
                            </tr>
                        </table>

                        <p style="margin:0 0 24px; font-size:13px; line-height:21px; color:#6B7280; word-break:break-word;">
                            System link: <a href="{{ $systemLink }}" style="color:#6C5CE7; text-decoration:none;">{{ $systemLink }}</a><br>
                            @if($dashboardLink)
                                Dashboard link: <a href="{{ $dashboardLink }}" style="color:#6C5CE7; text-decoration:none;">{{ $dashboardLink }}</a><br>
                            @endif
                        </p>

                        @if($subscription)
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; border:1px solid #E5E7EB; border-radius:12px; margin:0 0 24px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:13px; line-height:18px; color:#6B7280; font-weight:700; text-transform:uppercase; margin-bottom:12px;">Subscription</div>
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;">
                                            <tr>
                                                <td style="padding:8px 0; font-size:14px; line-height:22px; color:#6B7280;">Plan</td>
                                                <td align="right" style="padding:8px 0; font-size:15px; line-height:22px; color:#111827; font-weight:700;">{{ $subscription['plan'] ?? '' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:8px 0; font-size:14px; line-height:22px; color:#6B7280; border-top:1px solid #E5E7EB;">Max users</td>
                                                <td align="right" style="padding:8px 0; font-size:15px; line-height:22px; color:#111827; font-weight:700; border-top:1px solid #E5E7EB;">{{ $subscription['max_users'] ?? '' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div style="background-color:#FFF7ED; border:1px solid #FDBA74; color:#9A3412; border-radius:10px; padding:14px 16px; font-size:14px; line-height:22px; margin:0;">
                            Please sign in and change your password after your first login.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 32px; text-align:center; background-color:#F9FAFB; border-top:1px solid #E5E7EB;">
                        <div style="font-size:12px; line-height:18px; color:#6B7280;">&copy; {{ date('Y') }} Denta+ — All rights reserved.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
