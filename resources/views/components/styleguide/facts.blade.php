@props(['rows'])

{{-- Список «ключ → значение» стайлгайда; значения-коды — моноширинным. --}}
<dl {{ $attributes->class('flex flex-col') }}>
    @foreach ($rows as [$key, $value, $mono])
        <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-t border-line-soft py-2 text-base">
            <dt class="text-steel-500">{{ $key }}</dt>
            <dd @class(['font-medium', 'font-mono' => $mono])>{{ $value }}</dd>
        </div>
    @endforeach
</dl>
