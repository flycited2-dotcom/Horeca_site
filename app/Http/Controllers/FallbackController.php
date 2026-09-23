<?php

namespace App\Http\Controllers;

use App\Actions\Storefront\FollowRedirect;
use App\Models\Page;
use App\Services\Seo\MetaBuilder;
use App\Services\Settings\Settings;
use App\Support\StructuredData;
use App\View\StoreContacts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Адрес, для которого нет маршрута (ТЗ §8): редирект из таблицы redirects, затем
 * включённая статическая страница по slug, затем страница 404. Через fallback страницы
 * не перехватывают маршруты Filament и Livewire. Под текстом «Контактов» — контакты и
 * реквизиты из «Настроек».
 */
class FallbackController extends Controller
{
    /**
     * Pages whose questions go into FAQPage markup (TZ §14).
     */
    public const array FAQ_PAGES = ['dostavka', 'oplata'];

    public function __invoke(Request $request, FollowRedirect $redirects, MetaBuilder $meta, Settings $settings): RedirectResponse|View
    {
        $redirect = $redirects->handle($request);

        if ($redirect !== null) {
            return $redirect;
        }

        $slug = trim($request->path(), '/');

        // Текст «Оптовикам» показывается на странице заявки на опт — одна страница вместо двух (ТЗ §11).
        if ($slug === WholesaleController::BENEFITS_PAGE) {
            return redirect()->route('wholesale', status: 301);
        }

        $page = str_contains($slug, '/')
            ? null
            : Page::query()->where('slug', $slug)->where('is_active', true)->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return view('pages.show', [
            'page' => $page,
            'meta' => $meta->page($page),
            'faq' => in_array($page->slug, self::FAQ_PAGES, true) ? StructuredData::faq($page->content) : null,
            'contacts' => $page->slug === StoreContacts::PAGE ? StoreContacts::from($settings) : null,
        ]);
    }
}
