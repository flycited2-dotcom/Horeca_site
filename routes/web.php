<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StyleguideController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('/catalog/{category:slug}', [CatalogController::class, 'show'])->name('category');
Route::get('/product/{product:slug}', ProductController::class)->name('product');
Route::get('/search', SearchController::class)->name('search');

// Сверка компонентов с макетами; вне локальной разработки отвечает 404.
Route::get('/styleguide', StyleguideController::class)->name('styleguide');
