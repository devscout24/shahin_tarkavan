<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group_name',
        'key',
        'value',
    ];

    public static function getValue(string $group, string $key, ?string $default = null): ?string
    {
        $cacheKey = "settings:value:{$group}:{$key}";

        return Cache::rememberForever($cacheKey, function () use ($group, $key, $default): ?string {
            return static::query()
                ->where('group_name', $group)
                ->where('key', $key)
                ->value('value') ?? $default;
        });
    }

    public static function setValue(string $group, string $key, ?string $value): self
    {
        $setting = static::query()->updateOrCreate(
            ['group_name' => $group, 'key' => $key],
            ['value' => $value]
        );

        Cache::forget("settings:value:{$group}:{$key}");
        Cache::forget("settings:group:{$group}");

        return $setting;
    }

    public static function getGroup(string $group): array
    {
        $cacheKey = "settings:group:{$group}";

        return Cache::rememberForever($cacheKey, function () use ($group): array {
            return static::query()
                ->where('group_name', $group)
                ->pluck('value', 'key')
                ->toArray();
        });
    }
}
