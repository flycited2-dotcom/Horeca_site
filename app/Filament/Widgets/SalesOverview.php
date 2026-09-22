<?php

namespace App\Filament\Widgets;

use App\Enums\CompanyStatus;
use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Первое, что видит менеджер (ТЗ §12): необработанные заявки крупным числом, заявки
 * за сегодня и за неделю с графиком по дням, новые лиды и компании на проверке.
 * Дни — по московскому времени.
 */
class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = Carbon::now('Europe/Moscow')->startOfDay();
        $weekStart = $today->copy()->subDays(6);

        $perDay = Order::query()
            ->where('created_at', '>=', $weekStart->copy()->setTimezone(config('app.timezone')))
            ->get(['created_at'])
            ->countBy(fn (Order $order): string => $order->created_at->copy()->setTimezone('Europe/Moscow')->toDateString());

        $week = [];

        for ($day = $weekStart->copy(); $day->lte($today); $day->addDay()) {
            $week[] = (int) ($perDay[$day->toDateString()] ?? 0);
        }

        return [
            Stat::make(__('admin.dashboard.new_orders'), (string) Order::query()->where('status', OrderStatus::New)->count())
                ->description(__('admin.dashboard.new_orders_hint'))
                ->color('danger')
                ->url(OrderResource::getUrl('index', ['activeTab' => OrderStatus::New->value])),

            Stat::make(__('admin.dashboard.orders_today'), (string) end($week))
                ->description(__('admin.dashboard.orders_week', ['count' => array_sum($week)]))
                ->chart($week)
                ->color('info'),

            Stat::make(__('admin.dashboard.new_leads'), (string) Lead::query()->where('status', LeadStatus::New)->count())
                ->color('warning')
                ->url(LeadResource::getUrl('index')),

            Stat::make(__('admin.dashboard.pending_companies'), (string) Company::query()->where('status', CompanyStatus::Pending)->count()),
        ];
    }
}
