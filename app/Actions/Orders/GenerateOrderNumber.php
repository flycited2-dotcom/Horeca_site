<?php

namespace App\Actions\Orders;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issues order numbers like HR-260916-0042: a daily counter by the Moscow date (TZ §10.3).
 *
 * The counter row is locked with SELECT … FOR UPDATE, so concurrent checkouts
 * never receive the same number.
 */
final class GenerateOrderNumber
{
    private const int MAX_PER_DAY = 9999;

    public function handle(?CarbonInterface $at = null): string
    {
        $moment = ($at ?? now())->copy()->setTimezone(config('app.timezone'));
        $day = $moment->toDateString();

        $next = DB::transaction(function () use ($day): int {
            DB::table('order_counters')->insertOrIgnore(['date' => $day, 'last_number' => 0]);

            $current = (int) DB::table('order_counters')->where('date', $day)->lockForUpdate()->value('last_number');
            $next = $current + 1;

            if ($next > self::MAX_PER_DAY) {
                throw new RuntimeException('Order number limit of '.self::MAX_PER_DAY." per day is reached for {$day}.");
            }

            DB::table('order_counters')->where('date', $day)->update(['last_number' => $next]);

            return $next;
        });

        return sprintf('HR-%s-%04d', $moment->format('ymd'), $next);
    }
}
