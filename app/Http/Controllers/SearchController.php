<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Страница поиска (ТЗ §8.4, макет — экран 8). Выдача с фильтрами — компонент
 * App\Livewire\SearchListing: состояние живёт в адресе, как в листинге категории.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = mb_substr(trim((string) $request->query('q', '')), 0, 200);

        return view('search.index', ['query' => $query]);
    }
}
