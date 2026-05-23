<?php

namespace App\Support;

class AgeGroup
{
    public const LABELS = [
        'U3-U8',
        'U9-U12',
        'U13-U17',
        'U18-U21',
        'U21-U30',
        '30+',
    ];

    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function resolveFromAge(?int $age): ?string
    {
        if ($age === null || $age < 0) {
            return null;
        }

        if ($age <= 8) {
            return 'U3-U8';
        }

        if ($age <= 12) {
            return 'U9-U12';
        }

        if ($age <= 17) {
            return 'U13-U17';
        }

        if ($age <= 21) {
            return 'U18-U21';
        }

        if ($age <= 30) {
            return 'U21-U30';
        }

        return '30+';
    }

    public static function normalize(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/\s+/', '', $value) ?? '';

        if ($value === '') {
            return null;
        }

        if (in_array($value, self::LABELS, true)) {
            return $value;
        }

        if (str_contains($value, 'SENIOR')) {
            return '30+';
        }

        if (preg_match('/^(?:U)?(\d+)\+$/', $value, $matches)) {
            return self::resolveFromAge((int) $matches[1]) ?? '30+';
        }

        if (preg_match_all('/\d+/', $value, $matches)) {
            $numbers = array_values(array_unique(array_map('intval', $matches[0])));
            sort($numbers);

            if (count($numbers) === 1) {
                return self::resolveFromAge($numbers[0]);
            }

            $min = $numbers[0];
            $max = $numbers[array_key_last($numbers)];

            if ($max <= 8) {
                return 'U3-U8';
            }

            if ($min >= 9 && $max <= 12) {
                return 'U9-U12';
            }

            if ($min >= 13 && $max <= 17) {
                return 'U13-U17';
            }

            if ($min >= 18 && $max <= 21) {
                return 'U18-U21';
            }

            if ($min >= 21 && $max <= 30) {
                return 'U21-U30';
            }

            if ($min >= 30) {
                return '30+';
            }
        }

        return null;
    }

    public static function ageMatchesLabel(?int $age, string $label): bool
    {
        if ($age === null) {
            return false;
        }

        return match ($label) {
            'U3-U8' => $age >= 3 && $age <= 8,
            'U9-U12' => $age >= 9 && $age <= 12,
            'U13-U17' => $age >= 13 && $age <= 17,
            'U18-U21' => $age >= 18 && $age <= 21,
            'U21-U30' => $age >= 21 && $age <= 30,
            '30+' => $age >= 30,
            default => false,
        };
    }

    public static function matchesFilter(?string $filter, array $item): bool
    {
        $target = self::normalize($filter);

        if (! $target) {
            return false;
        }

        foreach (['age', 'upto_age'] as $key) {
            if (isset($item[$key]) && is_numeric($item[$key]) && self::ageMatchesLabel((int) $item[$key], $target)) {
                return true;
            }
        }

        $itemLabel = self::normalize($item['age_group'] ?? null);

        return $itemLabel === $target;
    }
}
