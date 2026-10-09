<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Reset your Beachrex password', [], $locale) }}</title>
</head>
<body style="margin:0;padding:0;background:#F5F5F5;font-family:Figtree,Arial,Helvetica,sans-serif;color:#222222;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F5F5;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:8px;">
<tr><td style="padding:32px 32px 8px;">
<img src="{{ $logoUrl }}" width="180" alt="Beachrex" style="display:block;border:0;width:180px;height:auto;">
</td></tr>
<tr><td style="padding:16px 32px 0;font-size:16px;line-height:24px;">
<p style="margin:0 0 16px;font-size:20px;font-weight:bold;color:#1A3C68;">{{ __('Hello!', [], $locale) }}</p>
<p style="margin:0 0 24px;">{{ __('We received a request to reset the password for your Beachrex account.', [], $locale) }}</p>
<p style="margin:0 0 24px;"><a href="{{ $url }}" style="display:inline-block;background:#1A3C68;color:#FFFFFF;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:6px;">{{ __('Set a new password', [], $locale) }}</a></p>
<p style="margin:0 0 16px;color:#4D4D4D;">{{ __('This link expires in :count minutes.', ['count' => $minutes], $locale) }}</p>
<p style="margin:0 0 16px;color:#4D4D4D;">{{ __('If you did not request a password reset, you can ignore this email. Your password stays the same.', [], $locale) }}</p>
<p style="margin:0 0 24px;">{{ __('The Beachrex team', [], $locale) }}</p>
</td></tr>
<tr><td style="padding:16px 32px 32px;border-top:1px solid #E7E7E7;font-size:13px;line-height:20px;color:#4D4D4D;">
{{ __('If the button does not work, copy this link into your browser:', [], $locale) }}<br>
<a href="{{ $url }}" style="color:#0069AB;word-break:break-all;">{{ $url }}</a>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
