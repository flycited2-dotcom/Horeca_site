<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\Settings\Settings;
use App\View\ManagerContacts;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Сводка личного кабинета (ТЗ §11; макет — экран 7 в объёме MVP): статус компании и оптовых
 * цен, контакты менеджера, быстрые действия и последние 5 заявок с оплатой и отгрузкой.
 */
final class AccountController extends Controller
{
    public const int LATEST_ORDERS = 5;

    public function __invoke(Request $request, Settings $settings): View
    {
        /** @var User $user */
        $user = $request->user();

        $orders = self::ordersOf($user)->limit(self::LATEST_ORDERS)->get();

        return view('account.index', [
            'user' => $user,
            'company' => $user->company()->with('priceTier:id,name')->first(),
            'orders' => $orders,
            'ordersCount' => $orders->count() < self::LATEST_ORDERS ? $orders->count() : $user->orders()->count(),
            'contacts' => ManagerContacts::from($settings),
            'showTier' => $settings->boolean('pricing.show_tier_name', false),
        ]);
    }

    /**
     * The customer's orders for a list, newest first: the columns of the table and the
     * items for the «Состав» column with one query each (TZ, CLAUDE.md: no N+1).
     *
     * @return HasMany<Order, User>
     */
    public static function ordersOf(User $user): HasMany
    {
        return $user->orders()
            ->select(['id', 'number', 'user_id', 'status', 'payment_method', 'invoice_path', 'paid_at', 'total', 'created_at'])
            ->with(['items' => fn (HasMany $items) => $items->select(['id', 'order_id', 'name'])->orderBy('id')])
            ->latest('id');
    }
}
