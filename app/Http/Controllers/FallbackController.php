<?php

namespace App\Http\Controllers;

use App\Actions\Storefront\FollowRedirect;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Адрес, для которого нет маршрута (ТЗ §8): редирект из таблицы redirects, затем
 * включённая статическая страница по slug, затем страница 404. Через fallback страницы
 * не перехватывают маршруты Filament и Livewire.
 */
class FallbackController extends Controller
{
    public function __invoke(Request $request, FollowRedirect $redirects): RedirectResponse|View
    {
        $redirect = $redirects->handle($request);

        if ($redirect !== null) {
            return $redirect;
        }

        $slug = trim($request->path(), '/');

        $page = str_contains($slug, '/')
            ? null
            : Page::query()->where('slug', $slug)->where('is_active', true)->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return view('pages.show', ['page' => $page]);
    }
}
