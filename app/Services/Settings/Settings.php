<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Support\Percent;
use InvalidArgumentException;

/**
 * Reads site settings (TZ §5.5), each key once per request or queued job.
 */
final class Settings
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = Setting::query()->where('key', $key)->first()?->value;
        }

        return $this->values[$key] ?? $default;
    }

    public function integer(string $key, int $default): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key, bool $default): bool
    {
        $value = $this->get($key);

        return is_bool($value) ? $value : $default;
    }

    /**
     * A percentage stored as 10, "10" or "10.5". null when it is not set or not a percentage:
     * a wrong value never becomes a discount.
     */
    public function percent(string $key): ?Percent
    {
        $value = $this->get($key);

        if (! is_int($value) && ! is_string($value) && ! is_float($value)) {
            return null;
        }

        // JSON gives 10.5 as a float; its shortest decimal form is exact for two digits.
        $decimal = is_float($value) ? rtrim(rtrim(sprintf('%.2F', $value), '0'), '.') : trim((string) $value);

        try {
            return Percent::fromDecimal($decimal);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
