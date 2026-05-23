<?php

namespace Database\Seeders;

use App\Models\SportOption;
use Illuminate\Database\Seeder;

class SportOptionSeeder extends Seeder
{
    public function run(): void
    {
        $playerSports = [
            'Football (Soccer)',
        ];

        $coachSports = [
            'Football (Soccer)',
            'Strength & Conditioning',
            'Nutrition',
            'Mindset',
            'Fitness',
            'Physiotherapy',
            'Other',
        ];

        foreach ($playerSports as $sport) {
            SportOption::query()->updateOrCreate(
                ['audience' => 'player', 'name' => $sport],
                ['status' => 'active']
            );
        }

        foreach ($coachSports as $sport) {
            SportOption::query()->updateOrCreate(
                ['audience' => 'coach', 'name' => $sport],
                ['status' => 'active']
            );
        }
    }
}
