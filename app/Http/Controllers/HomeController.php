<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogQuery;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(CatalogQuery $catalog): View
    {
        return view('home.index', [
            'categories' => $catalog->homeRootCategories(),
        ]);
    }
}
