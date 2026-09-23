<?php

namespace App\Actions\Companies;

use App\Actions\Auth\RegisterCustomer;
use App\Events\WholesaleApplied;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Заявка на опт (ТЗ §11, сценарий 3 из §2): компания в статусе «на проверке» и кабинет,
 * связанный с ней. Гость получает новый кабинет — вход сразу, цены розничные до одобрения;
 * вошедший клиент — компанию к своему кабинету. В MVP у компании один кабинет, у кабинета
 * одна компания. Уведомления — после фиксации транзакции (§13).
 */
final class ApplyForWholesale
{
    public function __construct(private readonly RegisterCustomer $register) {}

    /**
     * @param  array{inn: string, legal_name: string, segment: string, city: string, contact_person: string, email: string, phone: string, comment?: ?string, password?: string}  $data
     */
    public function handle(array $data, ?User $user): Company
    {
        if ($user?->company_id !== null) {
            throw ValidationException::withMessages(['form' => __('shop.wholesale.already')]);
        }

        $company = DB::transaction(function () use ($data, $user): Company {
            $user ??= $this->register->handle([
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => (string) ($data['password'] ?? ''),
            ]);

            $company = Company::query()->create([
                'inn' => $data['inn'],
                'legal_name' => $data['legal_name'],
                'segment' => $data['segment'],
                'city' => $data['city'],
                'contact_person' => $data['contact_person'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'],
                'comment' => filled($data['comment'] ?? null) ? trim((string) $data['comment']) : null,
            ]);

            $user->company()->associate($company)->save();

            return $company;
        });

        WholesaleApplied::dispatch($company);

        return $company;
    }
}
