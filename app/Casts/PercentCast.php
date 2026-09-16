<?php

namespace App\Casts;

use App\Support\Percent;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Maps a decimal(5,2) column to Percent and back.
 *
 * @implements CastsAttributes<Percent|null, Percent|null>
 */
class PercentCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Percent
    {
        if ($value === null) {
            return null;
        }

        return Percent::fromDecimal((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Percent) {
            throw new InvalidArgumentException("Attribute [{$key}] accepts only ".Percent::class.' or null.');
        }

        return $value->toDecimal();
    }
}
