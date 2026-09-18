{{--
    Письмо о сбое импорта (ТЗ §13). Стили только строчные: почтовые клиенты
    не поддерживают внешние таблицы стилей. Палитра — ТЗ §9.
--}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('import.mail.failed_subject', ['profile' => $run->profile->name]) }}</title>
</head>
<body style="margin:0; padding:24px 12px; background-color:#f4f6f7; font-family:'Helvetica Neue',Arial,sans-serif; font-size:16px; line-height:1.5; color:#15191d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background-color:#ffffff; border:1px solid #d3d9de; border-radius:4px;">
    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <p style="margin:0 0 4px 0; font-size:13px; color:#5b6670;">{{ config('app.name') }}</p>
            <h1 style="margin:0; font-size:22px; line-height:1.15; color:#b42318;">{{ __('import.mail.failed_greeting') }}</h1>
        </td>
    </tr>
    <tr>
        <td style="padding:8px 24px 0 24px;">
            <p style="margin:0 0 12px 0;">{{ __('import.mail.failed_intro', ['run' => $run->id, 'profile' => $run->profile->name]) }}</p>
            <p style="margin:0 0 16px 0; padding:12px; background-color:#f4f6f7; border-left:3px solid #b42318; color:#15191d;">
                {{ __('import.mail.failed_reason', ['error' => $reason]) }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:0 24px 24px 24px;">
            <a href="{{ $url }}" style="display:inline-block; padding:10px 18px; background-color:#0b5fff; color:#ffffff; text-decoration:none; border-radius:6px;">
                {{ __('import.mail.failed_action') }}
            </a>
        </td>
    </tr>
</table>
</body>
</html>
