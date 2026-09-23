<?php

use App\Http\Controllers\AccountCompanyController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountOrderController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StyleguideController;
use App\Http\Controllers\WholesaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('/catalog/{category:slug}', [CatalogController::class, 'show'])->name('category');
Route::get('/product/{product:slug}', ProductController::class)->name('product');
Route::get('/brands', [BrandController::class, 'index'])->name('brands');
Route::get('/brands/{brand:slug}', [BrandController::class, 'show'])->name('brand');
Route::get('/search', SearchController::class)->name('search');

Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::post('/cart/{product}', [CartController::class, 'store'])->whereNumber('product')->name('cart.add');
Route::patch('/cart/{product}', [CartController::class, 'update'])->whereNumber('product')->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->whereNumber('product')->name('cart.remove');
Route::post('/cart/{product}/restore', [CartController::class, 'restore'])->whereNumber('product')->name('cart.restore');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/success/{number}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');

// Вход, регистрация и восстановление пароля покупателя (ТЗ §8, §15). Сотрудники входят в /manage.
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,60')->name('register.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:10,1')->name('password.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Личный кабинет (ТЗ §8, §11): сводка, заявки с повтором и счётом, реквизиты компании.
Route::middleware('auth')->prefix('account')->group(function (): void {
    Route::get('/', AccountController::class)->name('account');
    Route::get('/orders', [AccountOrderController::class, 'index'])->name('account.orders');
    Route::get('/orders/{order:number}', [AccountOrderController::class, 'show'])->name('account.order');
    Route::post('/orders/{order:number}/repeat', [AccountOrderController::class, 'repeat'])->middleware('throttle:30,1')->name('account.order.repeat');
    Route::get('/orders/{order:number}/invoice', [AccountOrderController::class, 'invoice'])->name('account.order.invoice');
    Route::get('/company', [AccountCompanyController::class, 'edit'])->name('account.company');
    Route::put('/company', [AccountCompanyController::class, 'update'])->middleware('throttle:10,1')->name('account.company.update');
});

// «Оптовым клиентам»: лендинг, заявка и её статус (ТЗ §11).
Route::get('/wholesale', [WholesaleController::class, 'show'])->name('wholesale');
Route::post('/wholesale', [WholesaleController::class, 'store'])->middleware('throttle:5,60')->name('wholesale.store');

Route::get('/compare', [CompareController::class, 'index'])->name('compare');
Route::delete('/compare', [CompareController::class, 'clear'])->name('compare.clear');
Route::post('/compare/{product}', [CompareController::class, 'store'])->whereNumber('product')->name('compare.add');
Route::delete('/compare/{product}', [CompareController::class, 'destroy'])->whereNumber('product')->name('compare.remove');

// Избранное (ТЗ §8, §11): гостю — до конца сессии, клиенту — в кабинете.
Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites');
Route::post('/favorites/{product}', [FavoriteController::class, 'store'])->whereNumber('product')->name('favorites.add');
Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy'])->whereNumber('product')->name('favorites.remove');

// Сверка компонентов с макетами; вне локальной разработки отвечает 404.
Route::get('/styleguide', StyleguideController::class)->name('styleguide');

// Редирект, статическая страница по slug, затем 404 (ТЗ §8). Последним: отвечает только на то, что не взял ни один маршрут.
Route::fallback(FallbackController::class);
