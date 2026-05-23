<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table): void {
            $table->integer('total_played_time')->default(0)->after('total_played_games');
        });

        Schema::table('player_season_stats', function (Blueprint $table): void {
            $table->unsignedInteger('total_played_time')->default(0)->after('total_played_games');
        });
    }

    public function down(): void
    {
        Schema::table('player_season_stats', function (Blueprint $table): void {
            $table->dropColumn('total_played_time');
        });

        Schema::table('athlete_profiles', function (Blueprint $table): void {
            $table->dropColumn('total_played_time');
        });
    }
};
