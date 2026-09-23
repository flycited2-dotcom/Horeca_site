<?php

namespace App\Actions\Companies;

use App\Enums\CompanyStatus;
use App\Events\CompanyApproved;
use App\Models\Company;
use App\Models\PriceTier;
use App\Models\User;

/**
 * Модерация заявки на опт (ТЗ §11, §12): менеджер назначает ценовую группу и одобряет
 * компанию — клиенту уходит письмо «Оптовые цены открыты» (§13), цены на витрине сразу
 * считаются по группе (§7). Одобренной компании этим же действием меняют группу: письмо
 * уходит только при первом одобрении.
 */
final class ApproveCompany
{
    public function handle(Company $company, PriceTier $tier, User $manager, ?string $comment = null): Company
    {
        $newlyApproved = $company->status !== CompanyStatus::Approved;

        $company->status = CompanyStatus::Approved;
        $company->price_tier_id = $tier->id;

        if ($newlyApproved) {
            $company->approved_at = now();
            $company->approved_by = $manager->id;
        }

        if (filled($comment)) {
            $company->manager_comment = trim((string) $comment);
        }

        $company->save();

        if ($newlyApproved) {
            CompanyApproved::dispatch($company);
        }

        return $company;
    }
}
