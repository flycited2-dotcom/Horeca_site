<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Регистрация покупателя на витрине (ТЗ §2.1, §8): кабинет с ролью «клиент», без компании.
 * Оптовые цены открываются отдельной заявкой на опт (§11). Телефон уже приведён к
 * виду «+7 978 123-45-67».
 */
final class RegisterCustomer
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'],
            'password' => $data['password'],
        ]);
        $user->role = UserRole::Customer;
        $user->save();

        event(new Registered($user));

        return $user;
    }
}
