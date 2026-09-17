<?php

namespace App\Services\Settings;

use App\Models\Setting;

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
}
