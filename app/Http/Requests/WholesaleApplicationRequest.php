<?php

namespace App\Http\Requests;

use App\Enums\CompanySegment;
use App\Http\Requests\Concerns\GuardsAgainstBots;
use App\Rules\Inn;
use App\Rules\RussianPhone;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Заявка на опт (ТЗ §11): ИНН с проверкой контрольных цифр, юр. название, тип заведения,
 * город, контактное лицо, телефон, почта, пароль и комментарий, согласие на обработку ПДн.
 * Гость заводит этим кабинет — пароль обязателен, почта и телефон не должны принадлежать
 * другому кабинету. Вошедшему клиенту пароль не нужен: заявка привязывается к его кабинету.
 */
class WholesaleApplicationRequest extends FormRequest
{
    use GuardsAgainstBots;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guest = $this->user() === null;

        return [
            'inn' => ['required', 'string', new Inn],
            'legal_name' => ['required', 'string', 'max:255'],
            'segment' => ['required', Rule::enum(CompanySegment::class)],
            'city' => ['required', 'string', 'max:150'],
            'contact_person' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:150', ...($guest ? [Rule::unique('users', 'email')] : [])],
            'phone' => ['required', 'string', new RussianPhone, ...($guest ? [Rule::unique('users', 'phone')] : [])],
            'password' => $guest ? ['required', 'string', 'max:255', 'confirmed', Password::defaults()] : ['exclude'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('shop.wholesale.email_taken'),
            'phone.unique' => __('shop.wholesale.phone_taken'),
            'consent.accepted' => __('shop.checkout.errors.consent'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'inn' => 'ИНН',
            'legal_name' => mb_strtolower(__('shop.wholesale.fields.legal_name')),
            'segment' => mb_strtolower(__('admin.company.segment')),
            'city' => mb_strtolower(__('shop.wholesale.fields.city')),
            'contact_person' => mb_strtolower(__('admin.company.contact_person')),
            'email' => mb_strtolower(__('admin.company.email')),
            'phone' => mb_strtolower(__('shop.wholesale.fields.phone')),
            'password' => mb_strtolower(__('shop.auth.register.password')),
            'comment' => mb_strtolower(__('shop.wholesale.fields.comment')),
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [$this->botCheck(__('shop.auth.register.too_fast'))];
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (string $key): mixed => is_string($this->input($key)) ? trim($this->input($key)) : $this->input($key);

        $this->merge([
            'inn' => is_string($this->input('inn')) ? preg_replace('/\s+/', '', $this->input('inn')) : $this->input('inn'),
            'legal_name' => $trim('legal_name'),
            'city' => $trim('city'),
            'contact_person' => $trim('contact_person'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'phone' => Phone::normalize(is_string($this->input('phone')) ? $this->input('phone') : null) ?? $this->input('phone'),
        ]);
    }
}
