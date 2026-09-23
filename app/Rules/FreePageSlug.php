<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * Адрес страницы CMS не должен совпадать с разделом сайта (ТЗ §8): страницы открываются
 * через Route::fallback, и страница «cart» или «catalog» никогда бы не показалась — первым
 * отвечает маршрут магазина. Занятые адреса берутся из самих маршрутов.
 */
final class FreePageSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && in_array(mb_strtolower($value), self::taken(), true)) {
            $fail(__('admin.page.slug_taken'));
        }
    }

    /**
     * The first segments of the site's own addresses: «catalog», «cart», «manage», «livewire-…».
     *
     * @return list<string>
     */
    public static function taken(): array
    {
        $segments = [];

        foreach (Router::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $first = explode('/', trim($route->uri(), '/'))[0];

            if ($first !== '' && ! str_starts_with($first, '{')) {
                $segments[$first] = true;
            }
        }

        return array_keys($segments);
    }
}
