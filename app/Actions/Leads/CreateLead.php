<?php

namespace App\Actions\Leads;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Events\LeadCreated;
use App\Models\Lead;
use App\Support\Phone;

/**
 * Сохраняет короткую заявку с витрины (ТЗ §5, §13): телефон в едином виде, UTM-метки
 * перехода и IP; менеджерам — сообщение в Telegram по событию LeadCreated.
 */
final class CreateLead
{
    /**
     * @param  array<string, mixed>  $data  the validated lead form
     * @param  array{ip?: ?string, utm?: ?array<string, string>}  $meta
     */
    public function handle(array $data, array $meta = []): Lead
    {
        $lead = Lead::query()->create([
            'type' => LeadType::from((string) $data['type']),
            'name' => filled($data['name'] ?? null) ? $data['name'] : null,
            'phone' => Phone::normalize((string) $data['phone']),
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            'product_id' => $data['product_id'] ?? null,
            'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
            'utm' => $meta['utm'] ?? null,
            'ip' => $meta['ip'] ?? null,
        ]);

        // The column defaults to «new»; the model learns it without another query.
        $lead->setAttribute('status', LeadStatus::New);

        LeadCreated::dispatch($lead);

        return $lead;
    }
}
