<?php

namespace App\Livewire;

use App\Services\Pricing\PriceResolver;
use App\Services\Search\ProductSearch;
use App\Services\Search\QueryNormalizer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Мгновенный поиск (ТЗ §8.4, макет — экран 5): под полем — до шести товаров с ценой
 * и наличием и до трёх разделов, от двух символов, с задержкой 250 мс. Поле остаётся
 * обычной формой поиска: Enter и кнопка открывают /search, без скриптов — тоже.
 * Выборка — в ProductSearch, здесь только состояние поля.
 */
final class InstantSearch extends Component
{
    #[Locked]
    public string $fieldId = 'site-search';

    /**
     * header — поле в шапке; sku — «Знаю артикул» на главной.
     */
    #[Locked]
    public string $variant = 'header';

    public string $query = '';

    /**
     * The customer has typed: until then the field only shows what was searched for.
     */
    public bool $typed = false;

    public function mount(string $fieldId = 'site-search', string $variant = 'header'): void
    {
        $this->fieldId = $fieldId;
        $this->variant = $variant === 'sku' ? 'sku' : 'header';

        if ($this->variant === 'header' && request()->routeIs('search')) {
            $this->query = mb_substr((string) request()->query('q', ''), 0, 200);
        }
    }

    public function updatedQuery(): void
    {
        $this->query = mb_substr($this->query, 0, 200);
        $this->typed = true;
    }

    public function render(ProductSearch $search, PriceResolver $prices): View
    {
        $text = trim($this->query);
        $result = $this->typed && mb_strlen($text) >= QueryNormalizer::MIN_LENGTH
            ? $search->instant($text, request()->user())
            : null;

        return view('livewire.instant-search', [
            'text' => $text,
            'result' => $result,
            'prices' => $result === null ? [] : $prices->forMany($result->products, request()->user()),
        ]);
    }
}
