<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Заказ списком» и прайс с оптовыми ценами — только клиентам одобренной компании (ТЗ §11).
 * Остальных ведёт к заявке на опт: там видно, как её подать или на каком она этапе.
 */
final class EnsureWholesaleApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasApprovedCompany()) {
            return redirect()->route('wholesale')->with('notice', ['text' => __('shop.bulk.wholesale_only')]);
        }

        return $next($request);
    }
}
