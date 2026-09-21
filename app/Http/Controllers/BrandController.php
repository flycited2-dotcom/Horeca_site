<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\Catalog\CatalogQuery;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Бренды (ТЗ §8): список названий по буквам — логотипов у поставщика нет — и листинг
 * бренда с фильтрами (App\Livewire\BrandListing).
 */
class BrandController extends Controller
{
    public function index(CatalogQuery $catalog): View
    {
        $brands = $catalog->brandDirectory();

        return view('brands.index', [
            'groups' => collect($brands)->groupBy(fn (array $brand): string => $this->letter($brand['name'])),
            'brandsCount' => count($brands),
            'productsCount' => array_sum(array_column($brands, 'products_count')),
        ]);
    }

    public function show(Brand $brand): View
    {
        if (! $brand->is_active) {
            throw new NotFoundHttpException;
        }

        return view('brands.show', ['brand' => $brand]);
    }

    /**
     * The heading a brand goes under: its first letter, digits and signs together.
     */
    private function letter(string $name): string
    {
        $first = mb_strtoupper(mb_substr(trim($name), 0, 1));

        return preg_match('/^\p{L}$/u', $first) === 1 ? $first : '0–9';
    }
}
