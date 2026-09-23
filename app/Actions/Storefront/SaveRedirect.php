<?php

namespace App\Actions\Storefront;

use App\Models\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Редирект, который менеджер завёл руками (ТЗ §12, §14): со старого адреса сайта на новый.
 * Адреса приводятся к одному виду — путь от корня без хвостового «/» и без адреса сайта.
 * Цепочек не бывает: если новый адрес сам ведёт дальше, редирект ведёт сразу в конец, а
 * редиректы, которые вели на старый адрес, перенаправляются туда же. Петля не сохраняется.
 */
final class SaveRedirect
{
    public function handle(?Redirect $redirect, string $from, string $to, int $status): Redirect
    {
        $from = self::path($from);
        $to = self::target($to);

        $taken = Redirect::query()
            ->where('from_path', $from)
            ->when($redirect !== null, fn ($query) => $query->whereKeyNot($redirect->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['data.from_path' => __('admin.redirect.taken')]);
        }

        $next = Redirect::query()
            ->where('from_path', $to)
            ->when($redirect !== null, fn ($query) => $query->whereKeyNot($redirect->id))
            ->value('to_path');

        if (is_string($next)) {
            $to = $next;
        }

        if ($to === $from) {
            throw ValidationException::withMessages(['data.to_path' => __('admin.redirect.loop')]);
        }

        return DB::transaction(function () use ($redirect, $from, $to, $status): Redirect {
            $redirect ??= new Redirect;
            $redirect->fill(['from_path' => $from, 'to_path' => $to, 'status_code' => $status])->save();

            Redirect::query()->where('to_path', $from)->whereKeyNot($redirect->id)->update(['to_path' => $to]);

            return $redirect;
        });
    }

    /**
     * «https://gastrosnab.ru/Old/» → «/Old»: the path the request will have.
     */
    public static function path(string $value): string
    {
        $value = trim($value);
        $path = (string) (parse_url($value, PHP_URL_PATH) ?? '/');

        return '/'.trim($path, '/');
    }

    /**
     * An address of this site becomes a path; another site stays as it is.
     */
    public static function target(string $value): string
    {
        $value = trim($value);
        $host = parse_url($value, PHP_URL_HOST);

        if (is_string($host) && $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return $value;
        }

        $query = parse_url($value, PHP_URL_QUERY);

        return self::path($value).(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
