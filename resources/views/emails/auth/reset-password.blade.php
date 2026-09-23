{{-- Ссылка на новый пароль (ТЗ §8, §13): кнопка, срок действия ссылки и что делать, если просили не вы. --}}
<x-mail.frame :title="__('notifications.password.subject')" :heading="__('notifications.password.heading')">
    <p style="margin:0 0 20px 0;">{{ __('notifications.password.intro', ['name' => $name]) }}</p>

    <p style="margin:0 0 20px 0;">
        <a href="{{ $url }}" style="display:inline-block; padding:12px 20px; border-radius:6px; background-color:#0072b0; color:#ffffff; font-weight:500; text-decoration:none;">{{ __('notifications.password.button') }}</a>
    </p>

    <p style="margin:0 0 8px 0; color:#5d686f; font-size:14px;">{{ __('notifications.password.expires', ['minutes' => $minutes]) }}</p>
    <p style="margin:0 0 16px 0; color:#5d686f; font-size:14px; word-break:break-all;"><a href="{{ $url }}" style="color:#0072b0;">{{ $url }}</a></p>

    <p style="margin:0; padding-top:16px; border-top:1px solid #e7ebee; color:#5d686f; font-size:14px;">{{ __('notifications.password.ignore') }}</p>
</x-mail.frame>
