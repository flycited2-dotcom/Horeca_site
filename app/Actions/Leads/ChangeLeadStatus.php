<?php

namespace App\Actions\Leads;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;

/**
 * Быстрая смена статуса лида из таблицы (ТЗ §12): кто взял лид в работу, за тем он
 * и закрепляется.
 */
final class ChangeLeadStatus
{
    public function handle(Lead $lead, LeadStatus $status, User $manager): Lead
    {
        $lead->status = $status;
        $lead->manager_id ??= $manager->id;
        $lead->save();

        return $lead;
    }
}
