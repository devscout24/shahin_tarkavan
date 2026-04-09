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
        Schema::create('team_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('player_id')->nullable();

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('coach_id')->nullable();

            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('child_id')->nullable();

             $table->foreign('club_id')->references('id')->on('users')->onDelete('cascade');
             $table->foreign('child_id')->references('id')->on('athlete_profiles')->onDelete('cascade');
             $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
             $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('club_teams')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_players');
    }
};

