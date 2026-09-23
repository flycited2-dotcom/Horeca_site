<?php

namespace App\Http\Controllers;

use App\Actions\Orders\AttachInvoice;
use App\Actions\Orders\RepeatOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\Settings\Settings;
use App\View\ManagerContacts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Заявки в кабинете (ТЗ §8: /account/orders; §11): список с оплатой и отгрузкой, заявка
 * с позициями и историей статусов, «Повторить заказ» и счёт PDF. Чужую заявку не открыть —
 * OrderPolicy (§15.5); удалённую администратором клиент не видит.
 */
final class AccountOrderController extends Controller
{
    public const int PER_PAGE = 20;

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.orders', [
            'user' => $user,
            'company' => $user->company()->first(['id', 'legal_name', 'inn', 'status']),
            'orders' => AccountController::ordersOf($user)->paginate(self::PER_PAGE)->withQueryString(),
        ]);
    }

    public function show(Request $request, Order $order, Settings $settings): View
    {
        Gate::authorize('view', $order);

        /** @var User $user */
        $user = $request->user();

        $order->load([
            'items' => fn ($items) => $items->orderBy('id'),
            'items.product:id,slug,is_visible',
            'statusLogs' => fn ($logs) => $logs->orderBy('id'),
        ]);

        return view('account.order', [
            'user' => $user,
            'company' => $user->company()->first(['id', 'legal_name', 'inn', 'status']),
            'order' => $order,
            'contacts' => ManagerContacts::from($settings),
        ]);
    }

    public function repeat(Request $request, Order $order, RepeatOrder $repeat): RedirectResponse
    {
        Gate::authorize('repeat', $order);

        /** @var User $user */
        $user = $request->user();

        $result = $repeat->handle($order, $user);

        $notice = $result->added > 0
            ? __('shop.account.repeat.added', ['number' => $order->number, 'count' => $result->added])
            : __('shop.account.repeat.none_added', ['number' => $order->number]);

        return redirect()->route('cart')
            ->with('notice', ['text' => $notice])
            ->with('repeat', ['number' => $order->number, 'skipped' => $result->skipped]);
    }

    /**
     * The invoice from the private disk (TZ §15.7): only for whoever may see the order.
     */
    public function invoice(Order $order): StreamedResponse
    {
        Gate::authorize('view', $order);

        $disk = Storage::disk(AttachInvoice::disk());

        abort_if(blank($order->invoice_path) || ! $disk->exists((string) $order->invoice_path), 404);

        return $disk->download((string) $order->invoice_path, "schet-{$order->number}.pdf");
    }
}
