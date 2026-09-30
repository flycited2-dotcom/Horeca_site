{{-- Проверочное письмо `php artisan mail:test` (ТЗ §17.6): в рамке настоящих писем. --}}
<x-mail.frame :title="__('backup.mail_test.subject', ['site' => $site])" :heading="__('backup.mail_test.subject', ['site' => $site])">
    <p style="margin:0;">{{ __('backup.mail_test.body', ['site' => $site, 'from' => $sender, 'mailer' => $transport]) }}</p>
</x-mail.frame>
