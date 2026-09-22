<?php

namespace App\Http\Controllers;

use App\Actions\Orders\PlaceOrder;
use App\Http\Middleware\RememberUtm;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Services\Cart\CartNotReady;
use App\Services\Cart\CartReview;
use App\Services\Settings\Settings;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Оформление заявки (ТЗ §10.2–10.4, макет — экран 12): одна страница, справа сводка.
 * Частота — 10 заявок в час с одного IP и 3 в час на один телефон (§15). «Спасибо»
 * открывается только в сессии, которая создала заказ.
 */
class CheckoutController extends Controller
{
    /**
     * Numbers of the orders this session has sent: only it may see their «Спасибо».
     */
    public const string PLACED = 'placed_orders';

    public const int PER_IP = 10;

    public const int PER_PHONE = 3;

    public function show(Request $request, CartReview $review, Settings $settings): View|RedirectResponse
    {
        $user = $request->user();
        $summary = $review->review($user);

        if (! $summary->canCheckout()) {
            return redirect()->route('cart')->with('notice', ['text' => (new CartNotReady($summary->isEmpty()))->getMessage()]);
        }

        $user?->loadMissing('company');

        return view('checkout.show', [
            'summary' => $summary,
            'user' => $user,
            'idempotencyKey' => old('idempotency_key', (string) Str::uuid()),
            'started' => Crypt::encryptString((string) now()->getTimestamp()),
            'pickup' => $settings->get('pickup.address'),
            'payments' => CheckoutRequest::paymentMethods($settings),
            'carriers' => __('shop.checkout.carriers'),
            'vat' => $settings->get('seller.vat_mode'),
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $place): RedirectResponse
    {
        $data = $request->validated();
        $repeat = Order::withTrashed()->where('idempotency_key', $data['idempotency_key'])->exists();
        $limits = ['checkout:ip:'.$request->ip() => self::PER_IP, 'checkout:phone:'.Phone::digits($data['phone']) => self::PER_PHONE];

        if (! $repeat) {
            foreach ($limits as $key => $max) {
                if (RateLimiter::tooManyAttempts($key, $max)) {
                    return redirect()->back()->withInput()->withErrors(['form' => __('shop.checkout.errors.too_many')]);
                }
            }
        }

        try {
            $order = $place->handle($data, $request->user(), [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'utm' => $request->session()->get(RememberUtm::SESSION_KEY),
            ]);
        } catch (CartNotReady $exception) {
            return redirect()->route('cart')->with('notice', ['text' => $exception->getMessage()]);
        }

        if ($order->wasRecentlyCreated) {
            foreach (array_keys($limits) as $key) {
                RateLimiter::hit($key, 3600);
            }
        }

        $placed = (array) $request->session()->get(self::PLACED, []);

        if (! in_array($order->number, $placed, true)) {
            $request->session()->push(self::PLACED, $order->number);
        }

        return redirect()->route('checkout.success', $order->number);
    }

    public function success(Request $request, string $number, Settings $settings): View
    {
        if (! in_array($number, (array) $request->session()->get(self::PLACED, []), true)) {
            throw new NotFoundHttpException;
        }

        $order = Order::query()->where('number', $number)->withCount('items')->firstOrFail();
        $settings->preload('contacts.phones', 'contacts.email', 'contacts.schedule');

        return view('checkout.success', [
            'order' => $order,
            'phones' => $settings->get('contacts.phones'),
            'email' => $settings->get('contacts.email'),
            'schedule' => $settings->get('contacts.schedule'),
        ]);
    }
}
