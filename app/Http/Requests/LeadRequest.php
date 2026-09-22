<?php

namespace App\Http\Requests;

use App\Enums\LeadType;
use App\Http\Requests\Concerns\GuardsAgainstBots;
use App\Rules\RussianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Короткая заявка с витрины (ТЗ §5: leads, §10.4): «Купить в 1 клик», запрос цены
 * и срока, подбор аналога, «Не нашли». Нужен только телефон и согласие на обработку
 * ПДн; антиспам тот же, что у оформления. Ошибки без скриптов собираются в свой набор
 * «lead»: каркас показывает их уведомлением.
 */
class LeadRequest extends FormRequest
{
    use GuardsAgainstBots;

    protected $errorBag = 'lead';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LeadType::class)],
            'name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', new RussianPhone],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => __('shop.checkout.errors.consent'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('shop.leads.fields');
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [$this->botCheck(__('shop.leads.too_fast'))];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
