@props(['title', 'heading'])

{{--
    Рамка письма (ТЗ §13): палитра сайта (§9), стили только строчные — почтовые клиенты не
    читают внешние таблицы стилей. Ширина 600, на телефоне тянется по экрану.
--}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:24px 12px; background-color:#f4f6f7; font-family:Manrope,'Helvetica Neue',Arial,sans-serif; font-size:15px; line-height:1.5; color:#15191d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background-color:#ffffff; border:1px solid #d3d9de; border-radius:4px;">
    <tr>
        <td style="padding:24px 24px 4px 24px;">
            <p style="margin:0 0 6px 0; font-size:13px; font-weight:700; color:#15191d;">{{ config('app.name') }}</p>
            <h1 style="margin:0; font-size:22px; line-height:1.2; color:#15191d;">{{ $heading }}</h1>
        </td>
    </tr>
    <tr>
        <td style="padding:12px 24px 24px 24px;">
            {{ $slot }}
        </td>
    </tr>
</table>
</body>
</html>
