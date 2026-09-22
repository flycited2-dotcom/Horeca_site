<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Where the cart of the current visitor lives (TZ §10.1). A signed-in customer has one cart
 * on every device. A guest's cart is found by a token in a cookie that lives 30 days — the
 * session of the site lives only hours, the guest cart must not. The token is what
 * carts.session_id holds; it survives signing in, so the guest cart can be merged.
 */
final class CartStore
{
    public const string COOKIE = 'cart';

    public const int LIFETIME_DAYS = 30;

    public function current(?User $user): ?Cart
    {
        if ($user !== null) {
            return Cart::query()->where('user_id', $user->id)->latest('id')->first();
        }

        return $this->guest();
    }

    /**
     * The cart of the guest behind this browser, while it has not expired.
     */
    public function guest(): ?Cart
    {
        $token = request()->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Cart::query()
            ->whereNull('user_id')
            ->where('session_id', $token)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * The cart to put a product into: every change extends its life by 30 days.
     */
    public function currentOrCreate(?User $user): Cart
    {
        $cart = $this->current($user);

        if ($cart !== null) {
            $cart->forceFill(['expires_at' => $this->expiry()])->save();

            if ($user === null) {
                $this->remember((string) $cart->session_id);
            }

            return $cart;
        }

        if ($user !== null) {
            return Cart::query()->create(['user_id' => $user->id, 'expires_at' => $this->expiry()]);
        }

        $token = Str::random(40);
        $this->remember($token);

        return Cart::query()->create(['session_id' => $token, 'expires_at' => $this->expiry()]);
    }

    /**
     * The guest cart has been merged into the customer's: the browser forgets it.
     */
    public function forgetGuest(): void
    {
        request()->cookies->remove(self::COOKIE);
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    private function remember(string $token): void
    {
        // The rest of this request must find the cart too, not only the next one.
        request()->cookies->set(self::COOKIE, $token);
        Cookie::queue(self::COOKIE, $token, self::LIFETIME_DAYS * 24 * 60);
    }

    private function expiry(): CarbonInterface
    {
        return now()->addDays(self::LIFETIME_DAYS);
    }
}
