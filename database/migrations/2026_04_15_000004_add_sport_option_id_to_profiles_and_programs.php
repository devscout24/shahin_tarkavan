<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultOptions = [
            ['name' => 'Football (Soccer)', 'audience' => 'player', 'status' => 'active'],
            ['name' => 'Football (Soccer)', 'audience' => 'coach', 'status' => 'active'],
            ['name' => 'Strength & Conditioning', 'audience' => 'coach', 'status' => 'active'],
            ['name' => 'Nutrition', 'audience' => 'coach', 'status' => 'active'],
            ['name' => 'Mindset', 'audience' => 'coach', 'status' => 'active'],
            ['name' => 'Fitness', 'audience' => 'coach', 'status' => 'active'],
            ['name' => 'Physiotherapy', 'audience' => 'coach', 'status' => 'active'],
            ['name' => 'Other', 'audience' => 'coach', 'status' => 'active'],
        ];

        foreach ($defaultOptions as $option) {
            DB::table('sport_options')->updateOrInsert(
                ['audience' => $option['audience'], 'name' => $option['name']],
                ['status' => $option['status'], 'updated_at' => now(), 'created_at' => now()]
            );
        }

        Schema::table('athlete_profiles', function (Blueprint $table): void {
            $table->foreignId('sport_option_id')
                ->nullable()
                ->after('sports')
                ->constrained('sport_options')
                ->nullOnDelete();
        });

        Schema::table('coaches', function (Blueprint $table): void {
            $table->foreignId('sport_option_id')
                ->nullable()
                ->after('sports')
                ->constrained('sport_options')
                ->nullOnDelete();
        });

        Schema::table('club_profiles', function (Blueprint $table): void {
            $table->foreignId('sport_option_id')
                ->nullable()
                ->after('sports')
                ->constrained('sport_options')
                ->nullOnDelete();
        });

        Schema::table('er_programs', function (Blueprint $table): void {
            $table->foreignId('sport_option_id')
                ->nullable()
                ->after('sport')
                ->constrained('sport_options')
                ->nullOnDelete();
        });

        $playerSportId = DB::table('sport_options')
            ->where('audience', 'player')
            ->where('name', 'Football (Soccer)')
            ->value('id');

        $coachSportIds = DB::table('sport_options')
            ->where('audience', 'coach')
            ->pluck('id', 'name')
            ->all();

        if ($playerSportId) {
            DB::table('athlete_profiles')
                ->whereNull('sport_option_id')
                ->update(['sport_option_id' => $playerSportId]);
        }

        foreach ($coachSportIds as $name => $id) {
            DB::table('coaches')
                ->whereNull('sport_option_id')
                ->where('sports', $name)
                ->update(['sport_option_id' => $id]);

            DB::table('club_profiles')
                ->whereNull('sport_option_id')
                ->where('sports', $name)
                ->update(['sport_option_id' => $id]);

            DB::table('er_programs')
                ->whereNull('sport_option_id')
                ->where('sport', $name)
                ->update(['sport_option_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('er_programs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sport_option_id');
        });

        Schema::table('club_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sport_option_id');
        });

        Schema::table('coaches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sport_option_id');
        });

        Schema::table('athlete_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sport_option_id');
        });
    }
};
