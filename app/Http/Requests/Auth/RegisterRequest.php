<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\GuardsAgainstBots;
use App\Rules\RussianPhone;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Регистрация покупателя (ТЗ §8, §15): имя, почта, телефон, пароль и согласие на обработку
 * ПДн. Телефон хранится в одном виде и не повторяется — по нему тоже входят (§15.3).
 * Антиспам — тот же, что у заявки: поле-ловушка и слишком быстрая отправка.
 */
class RegisterRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', new RussianPhone, Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('shop.auth.register.email_taken'),
            'phone.unique' => __('shop.auth.register.phone_taken'),
            'consent.accepted' => __('shop.checkout.errors.consent'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => mb_strtolower(__('shop.auth.register.name')),
            'email' => mb_strtolower(__('shop.auth.register.email')),
            'phone' => mb_strtolower(__('shop.auth.register.phone')),
            'password' => mb_strtolower(__('shop.auth.register.password')),
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
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'phone' => Phone::normalize(is_string($this->input('phone')) ? $this->input('phone') : null) ?? $this->input('phone'),
        ]);
    }
}
