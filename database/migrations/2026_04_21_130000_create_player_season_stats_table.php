<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_season_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_profile_id')->constrained('athlete_profiles')->cascadeOnDelete();
            $table->unsignedSmallInteger('season_year');
            $table->unsignedInteger('total_played_games')->default(0);
            $table->unsignedInteger('goals')->default(0);
            $table->unsignedInteger('assist')->default(0);
            $table->unsignedInteger('yellow_cards')->default(0);
            $table->unsignedInteger('red_cards')->default(0);
            $table->unsignedInteger('clean_sheets')->default(0);
            $table->unsignedInteger('total_saves')->default(0);
            $table->unsignedInteger('penalty_saves')->default(0);
            $table->timestamps();

            $table->unique(['athlete_profile_id', 'season_year'], 'season_stats_unique');
            $table->index(['season_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_season_stats');
    }
};
