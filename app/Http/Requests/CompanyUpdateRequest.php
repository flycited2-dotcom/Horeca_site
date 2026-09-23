<?php

namespace App\Http\Requests;

use App\Enums\CompanySegment;
use App\Rules\Inn;
use App\Rules\RussianPhone;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Реквизиты компании в кабинете (ТЗ §11, «Компания»): то же, что в заявке на опт, и банковские
 * реквизиты для счёта. Пробелы в числовых реквизитах убираются — их часто копируют из карточки
 * предприятия с разрядами.
 */
class CompanyUpdateRequest extends FormRequest
{
    /**
     * Numeric requisites and how many digits each has.
     */
    public const array DIGITS = ['kpp' => 9, 'bik' => 9, 'account' => 20, 'corr_account' => 20];

    /**
     * Only a customer with a company edits it, and only their own (CompanyPolicy, TZ §15.5).
     */
    public function authorize(): bool
    {
        $company = $this->user()?->company;

        return $company !== null && $this->user()->can('update', $company);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $digits = [];

        foreach (self::DIGITS as $field => $count) {
            $digits[$field] = ['nullable', 'string', "digits:{$count}"];
        }

        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['required', 'string', new Inn],
            'ogrn' => ['nullable', 'string', 'regex:/^(\d{13}|\d{15})$/'],
            'segment' => ['required', Rule::enum(CompanySegment::class)],
            'city' => ['required', 'string', 'max:150'],
            'legal_address' => ['nullable', 'string', 'max:500'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', new RussianPhone],
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            ...$digits,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = ['ogrn.regex' => __('shop.account.company.ogrn_digits')];

        foreach (self::DIGITS as $field => $count) {
            $messages["{$field}.digits"] = __('shop.account.company.digits', ['count' => $count]);
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(__('shop.account.company.fields'))
            ->map(fn (string $label, string $field): string => in_array($field, ['inn', 'kpp', 'ogrn', 'bik'], true) ? $label : mb_strtolower($label))
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (['inn', 'ogrn', ...array_keys(self::DIGITS)] as $field) {
            $value = $this->input($field);
            $clean[$field] = is_string($value) ? (preg_replace('/\s+/', '', $value) ?: null) : $value;
        }

        $email = $this->input('email');
        $phone = $this->input('phone');

        $this->merge([
            ...$clean,
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'phone' => Phone::normalize(is_string($phone) ? $phone : null) ?? $phone,
        ]);
    }
}
