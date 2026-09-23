{{-- Письмо о сбое импорта (ТЗ §13): что сломалось и кнопка в прогон в админке. --}}
<x-mail.frame :title="__('import.mail.failed_subject', ['profile' => $run->profile->name])" :heading="__('import.mail.failed_greeting')">
    <p style="margin:0 0 12px 0;">{{ __('import.mail.failed_intro', ['run' => $run->id, 'profile' => $run->profile->name]) }}</p>
    <p style="margin:0 0 16px 0; padding:12px; background-color:#f4f6f7; border-left:3px solid #c2410c; color:#a3350b;">
        {{ __('import.mail.failed_reason', ['error' => $reason]) }}
    </p>
    <p style="margin:0;">
        <a href="{{ $url }}" style="display:inline-block; padding:12px 20px; border-radius:6px; background-color:#0072b0; color:#ffffff; font-weight:500; text-decoration:none;">{{ __('import.mail.failed_action') }}</a>
    </p>
</x-mail.frame>
