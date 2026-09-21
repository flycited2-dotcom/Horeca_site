<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StyleguideController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('/catalog/{category:slug}', [CatalogController::class, 'show'])->name('category');
Route::get('/product/{product:slug}', ProductController::class)->name('product');
Route::get('/brands', [BrandController::class, 'index'])->name('brands');
Route::get('/brands/{brand:slug}', [BrandController::class, 'show'])->name('brand');
Route::get('/search', SearchController::class)->name('search');

Route::get('/compare', [CompareController::class, 'index'])->name('compare');
Route::delete('/compare', [CompareController::class, 'clear'])->name('compare.clear');
Route::post('/compare/{product}', [CompareController::class, 'store'])->whereNumber('product')->name('compare.add');
Route::delete('/compare/{product}', [CompareController::class, 'destroy'])->whereNumber('product')->name('compare.remove');

// Сверка компонентов с макетами; вне локальной разработки отвечает 404.
Route::get('/styleguide', StyleguideController::class)->name('styleguide');

// Редирект, статическая страница по slug, затем 404 (ТЗ §8). Последним: отвечает только на то, что не взял ни один маршрут.
Route::fallback(FallbackController::class);
