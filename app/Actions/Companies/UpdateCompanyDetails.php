<?php

namespace App\Actions\Companies;

use App\Enums\CompanyStatus;
use App\Events\WholesaleApplied;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

/**
 * Реквизиты компании из кабинета (ТЗ §11, «Компания»). Смена ИНН или юр. названия — уже
 * другая организация: одобренная или отклонённая компания возвращается на проверку, цены
 * до повторного одобрения розничные (§7), менеджеры получают заявку снова (§13).
 * Заблокированная компания ИНН и название сама не меняет — только через менеджера.
 */
final class UpdateCompanyDetails
{
    /**
     * The fields that identify the organization: a change needs a new check.
     */
    public const array KEY_FIELDS = ['inn', 'legal_name'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Company $company, array $data): Company
    {
        $company->fill($data);

        $keyChanged = $company->isDirty(self::KEY_FIELDS);

        if ($keyChanged && $company->status === CompanyStatus::Blocked) {
            throw ValidationException::withMessages(['inn' => __('shop.account.company.blocked')]);
        }

        $recheck = $keyChanged && in_array($company->status, [CompanyStatus::Approved, CompanyStatus::Rejected], true);

        if ($recheck) {
            $company->status = CompanyStatus::Pending;
        }

        $company->save();

        if ($recheck) {
            WholesaleApplied::dispatch($company, true);
        }

        return $company;
    }
}
