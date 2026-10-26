<?php

namespace App\Support;

use App\Models\SystemSetting;

final class Settings
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $setting = SystemSetting::query()->where('key', $key)->first();
        $value = $setting?->value;

        if ($value === null) {
            return $default;
        }

        if ($setting->type === 'json') {
            $value = json_decode($value, true);
        } elseif ($setting->type === 'boolean') {
            $value = in_array($value, ['1', 'true', 'yes', 1, true], true);
        } elseif ($setting->type === 'integer') {
            $value = (int) $value;
        }

        return self::$cache[$key] = $value;
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string', ?string $description = null): void
    {
        $stored = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => is_scalar($value) ? (string) $value : null,
        };

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'value' => $stored, 'type' => $type, 'description' => $description]
        );

        unset(self::$cache[$key]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function company(): array
    {
        return [
            'name' => self::get('company.name', config('app.name')),
            'email' => self::get('company.email', config('mail.from.address')),
            'phone' => self::get('company.phone', ''),
            'address' => self::get('company.address', ''),
            'website' => self::get('company.website', ''),
            'logo_path' => self::get('company.logo_path'),
            'currency' => self::get('finance.currency', 'USD'),
            'timezone' => self::get('app.timezone', config('app.timezone')),
        ];
    }
}
