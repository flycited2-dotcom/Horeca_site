@props(['filled' => false])

{{-- Сердце «Избранного»: контур — товар можно добавить, заливка — он уже в избранном. --}}
<svg {{ $attributes->class('shrink-0') }} viewBox="0 0 24 24" fill="{{ $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7.5-4.6-7.5-10A4.5 4.5 0 0 1 12 7.2 4.5 4.5 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10Z"/></svg>
