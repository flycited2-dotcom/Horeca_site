<?php

namespace App\Http\Requests\Cart;

use App\Services\Cart\CartRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Количество товара в корзине (ТЗ §10.1): целое от 1 до 9999. Поле можно не передавать —
 * кнопка «В корзину» в листинге кладёт одну штуку. Ноль на странице корзины удаляет
 * позицию с «Вернуть», поэтому здесь он допустим.
 */
class CartQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:0', 'max:'.CartRules::MAX_QUANTITY],
        ];
    }

    public function quantity(): int
    {
        return (int) ($this->validated('quantity') ?? 1);
    }
}
