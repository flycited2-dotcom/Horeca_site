<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use App\Services\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Проверка почты магазина (ТЗ §17.6): отправляет одно письмо сразу, не через очередь, и
 * говорит, взял ли его почтовый сервер. С MAIL_MAILER=log письмо только пишется в журнал —
 * команда честно об этом сообщает, а не рапортует «отправлено».
 */
final class SendTestMailCommand extends Command
{
    protected $signature = 'mail:test {email : Куда отправить проверочное письмо}';

    protected $description = 'Отправить проверочное письмо и показать, как настроена почта (ТЗ §17.6)';

    public function handle(Settings $settings): int
    {
        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error(__('backup.mail_test.invalid', ['email' => $email]));

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $site = trim((string) $settings->get('site.name')) ?: (string) config('app.name');

        try {
            Mail::to($email)->send(new TestMail($site, $from, $mailer));
        } catch (Throwable $exception) {
            $this->error(__('backup.mail_test.failed', ['reason' => $exception->getMessage()]));

            return self::FAILURE;
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn(__('backup.mail_test.logged', ['mailer' => $mailer]));

            return self::SUCCESS;
        }

        $this->info(__('backup.mail_test.sent', [
            'email' => $email,
            'from' => $from,
            'mailer' => $mailer,
            'host' => config('mail.mailers.'.$mailer.'.host') ?? '—',
        ]));

        return self::SUCCESS;
    }
}
