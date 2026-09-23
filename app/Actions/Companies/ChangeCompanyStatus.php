<?php

namespace App\Actions\Companies;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Отклонить, заблокировать или вернуть компанию на проверку (ТЗ §11, §12). Пока компания не
 * одобрена, её клиенты видят розничные цены (§7); ценовая группа сохраняется до повторного
 * одобрения. Отклонение и блокировка — только с причиной: её видят другие менеджеры.
 * Одобрение — отдельным действием ApproveCompany: ему нужна ценовая группа.
 */
final class ChangeCompanyStatus
{
    public function handle(Company $company, CompanyStatus $status, User $manager, ?string $reason = null): Company
    {
        if ($status === CompanyStatus::Approved) {
            throw new InvalidArgumentException('A company is approved with ApproveCompany: it needs a price tier.');
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        if ($reason === null && in_array($status, [CompanyStatus::Rejected, CompanyStatus::Blocked], true)) {
            throw ValidationException::withMessages(['reason' => __('validation.required', ['attribute' => mb_strtolower(__('admin.company.reason'))])]);
        }

        $company->status = $status;

        if ($reason !== null) {
            $company->manager_comment = $reason;
        }

        $company->save();

        return $company;
    }
}
