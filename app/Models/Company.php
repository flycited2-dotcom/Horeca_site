<?php

namespace App\Models;

use App\Enums\CompanySegment;
use App\Enums\CompanyStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status, price tier and approval fields change only through moderation.
 */
#[Fillable([
    'legal_name', 'brand_name', 'inn', 'kpp', 'ogrn', 'legal_address', 'delivery_address', 'city',
    'bank_name', 'bik', 'account', 'corr_account', 'contact_person', 'phone', 'email', 'segment', 'comment',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment' => CompanySegment::class,
            'status' => CompanyStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<PriceTier, $this>
     */
    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isApproved(): bool
    {
        return $this->status === CompanyStatus::Approved;
    }
}
