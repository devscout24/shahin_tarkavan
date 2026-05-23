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
        Schema::create('club_recruitments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('player_position')->nullable();
            $table->unsignedBigInteger('coach_position_id')->nullable();
            $table->unsignedBigInteger('club_team_id')->nullable();
            $table->foreign('club_team_id')->references('id')->on('club_teams')->onDelete('set null');
            $table->foreign('club_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('experience')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->longText('description')->nullable();
            $table->integer('upto_age')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('recruitment_type', ['player', 'coach'])->default('player');

            $table->foreign('player_position')->references('id')->on('player_positions')->onDelete('cascade');
            $table->foreign('coach_position_id')->references('id')->on('coach_positions')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_recruitments');
    }
};
