<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Rules\Inn;
use App\Rules\RussianPhone;
use App\Services\Settings\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Форма оформления заявки (ТЗ §10.2, §10.4): контакты, получение, оплата, согласие на
 * обработку ПДн. Антиспам — скрытое поле-ловушка и метка времени открытия формы: заявка
 * быстрее трёх секунд после открытия не принимается. Ошибка каждого поля объясняет, что
 * исправить (макет, экраны 11 и 12).
 */
class CheckoutRequest extends FormRequest
{
    /**
     * The trap field: people never see it, robots fill it.
     */
    public const string HONEYPOT = 'website';

    public const int MIN_SECONDS = 3;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return list<PaymentMethod>
     */
    public static function paymentMethods(Settings $settings): array
    {
        $methods = [PaymentMethod::Invoice, PaymentMethod::Cash];

        if ($settings->boolean('payments.online_enabled', false)) {
            $methods[] = PaymentMethod::Online;
        }

        return $methods;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $delivery = (string) $this->input('delivery_method');

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', new RussianPhone],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'is_legal_entity' => ['boolean'],
            'inn' => [Rule::requiredIf($this->boolean('is_legal_entity')), 'nullable', new Inn],
            'company_name' => [Rule::requiredIf($this->boolean('is_legal_entity')), 'nullable', 'string', 'max:255'],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'delivery_city' => [Rule::requiredIf($delivery === DeliveryMethod::TransportCompany->value), 'nullable', 'string', 'max:150'],
            'tk_name' => [Rule::requiredIf($delivery === DeliveryMethod::TransportCompany->value), 'nullable', Rule::in(__('shop.checkout.carriers'))],
            'delivery_address' => [Rule::requiredIf($delivery === DeliveryMethod::CourierCity->value), 'nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, self::paymentMethods(app(Settings::class))))],
            'comment' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => __('shop.checkout.errors.consent'),
            'idempotency_key.*' => __('shop.checkout.errors.form'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('shop.checkout.fields');
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (filled($this->input(self::HONEYPOT)) || ! $this->openedLongEnoughAgo()) {
                    $validator->errors()->add('form', __('shop.checkout.errors.too_fast'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'inn' => is_string($this->input('inn')) ? preg_replace('/\s+/', '', $this->input('inn')) : $this->input('inn'),
            'is_legal_entity' => $this->boolean('is_legal_entity'),
        ]);
    }

    /**
     * The form carries the moment it was opened, encrypted: a robot sends it in a blink.
     */
    private function openedLongEnoughAgo(): bool
    {
        try {
            $opened = (int) Crypt::decryptString((string) $this->input('started'));
        } catch (DecryptException) {
            return false;
        }

        return now()->getTimestamp() - $opened >= self::MIN_SECONDS;
    }
}
