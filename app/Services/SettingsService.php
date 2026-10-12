<?php
namespace App\Services;

use App\Models\Business;
use App\Models\BusinessSetting;

/** Business-level settings (single business today; business_id keeps multi-brand/multi-business ready). */
class SettingsService
{
    public static function businessId(): ?int { return Business::value('id'); }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = BusinessSetting::where('business_id', self::businessId())->where('key', $key)->first();
        if (! $row || $row->value === null) return $default;
        return match ($row->type) {
            'int' => (int) $row->value,
            'decimal' => (float) $row->value,
            'bool' => (bool) $row->value,
            default => $row->value,
        };
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        BusinessSetting::updateOrCreate(
            ['business_id' => self::businessId(), 'key' => $key],
            ['group' => $group, 'type' => $type, 'value' => $value === null ? null : (string) $value]
        );
    }
}
