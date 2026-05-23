<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'audience',
        'status',
    ];

    public static function activeOptionsForAudience(string|array $audience)
    {
        $audiences = array_values(array_filter((array) $audience));

        return static::query()
            ->where('status', 'active')
            ->when(! empty($audiences), function ($query) use ($audiences): void {
                $query->whereIn('audience', $audiences);
            })
            ->orderBy('name')
            ->get();
    }

    public static function activeNamesForAudience(string|array $audience): array
    {
        return static::activeOptionsForAudience($audience)
            ->pluck('name')
            ->values()
            ->all();
    }

    public static function resolveIdForAudience(string $audience, int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $option = static::query()
                ->where('id', (int) $value)
                ->where('audience', $audience)
                ->first();

            return $option?->id;
        }

        return static::query()
            ->where('audience', $audience)
            ->where('name', $value)
            ->value('id');
    }

    public static function resolveNameForAudience(string $audience, int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return static::query()
                ->where('id', (int) $value)
                ->where('audience', $audience)
                ->value('name');
        }

        return static::query()
            ->where('audience', $audience)
            ->where('name', $value)
            ->value('name');
    }
}
