<?php

namespace App\Actions\Storefront;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Адрес, которого на витрине больше нет, ведёт туда, куда указывает таблица redirects
 * (ТЗ §6.6, §8, §14): старый адрес товара, раздела или бренда после ручной правки или
 * адрес прежнего сайта. Переход считается в hits, строка запроса сохраняется.
 *
 * Вызывается из Route::fallback и из обработчика 404: старый адрес товара совпадает
 * с маршрутом товара, и до fallback такой запрос не доходит.
 */
final class FollowRedirect
{
    /**
     * Set on the request once the table was asked: the fallback route and the 404 handler
     * do not look twice.
     */
    private const string CHECKED = 'redirect_checked';

    private const array CODES = [301, 302, 307, 308];

    public function handle(Request $request): ?RedirectResponse
    {
        if (! $request->isMethodSafe() || $request->attributes->get(self::CHECKED) === true) {
            return null;
        }

        $request->attributes->set(self::CHECKED, true);

        $path = '/'.ltrim($request->path(), '/');

        $redirect = Redirect::query()
            ->whereIn('from_path', array_unique([$path, rawurldecode($path)]))
            ->first();

        if ($redirect === null) {
            return null;
        }

        Redirect::query()->whereKey($redirect->id)->increment('hits');

        $target = $redirect->to_path;
        $query = $request->getQueryString();

        if ($query !== null && ! str_contains($target, '?')) {
            $target .= '?'.$query;
        }

        return redirect($target, in_array($redirect->status_code, self::CODES, true) ? $redirect->status_code : 301);
    }
}
