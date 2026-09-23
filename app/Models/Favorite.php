<?php

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest's favorites live as long as the guest's session (TZ §5): the token that ties them
 * to the guest is kept in the session. Rows of guests gone for longer are pruned daily.
 */
#[Fillable(['user_id', 'session_id', 'product_id'])]
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * @return Builder<Favorite>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', now()->subMinutes((int) config('session.lifetime'))->subDay());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
