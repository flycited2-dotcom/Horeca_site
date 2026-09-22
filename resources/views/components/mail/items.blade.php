@props(['order'])

{{-- Состав заявки в письме: артикул, наименование, количество, сумма; итог — отдельной строкой. --}}
@php
    use App\Support\Typography;
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
    <tr>
        <th align="left" style="padding:8px 8px 8px 0; border-bottom:1px solid #d3d9de; font-weight:600; color:#5d686f;">{{ __('notifications.order.item') }}</th>
        <th align="right" style="padding:8px; border-bottom:1px solid #d3d9de; font-weight:600; color:#5d686f; white-space:nowrap;">{{ __('notifications.order.quantity') }}</th>
        <th align="right" style="padding:8px 0 8px 8px; border-bottom:1px solid #d3d9de; font-weight:600; color:#5d686f;">{{ __('notifications.order.sum') }}</th>
    </tr>
    @foreach ($order->items as $item)
        <tr>
            <td style="padding:8px 8px 8px 0; border-bottom:1px solid #e7ebee; vertical-align:top;">
                {{ $item->name }}
                @if ($item->sku)
                    <br><span style="font-family:'JetBrains Mono',Consolas,monospace; font-size:12px; color:#5d686f;">{{ $item->sku }}</span>
                @endif
            </td>
            <td align="right" style="padding:8px; border-bottom:1px solid #e7ebee; vertical-align:top; white-space:nowrap;">{{ $item->qty }} {{ $item->unit }}</td>
            <td align="right" style="padding:8px 0 8px 8px; border-bottom:1px solid #e7ebee; vertical-align:top; white-space:nowrap;">{{ Typography::money($item->sum) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="2" style="padding:12px 8px 0 0; font-weight:700;">{{ __('notifications.order.total') }}</td>
        <td align="right" style="padding:12px 0 0 8px; font-weight:700; font-size:16px; white-space:nowrap;">{{ Typography::money($order->total) }}</td>
    </tr>
</table>
