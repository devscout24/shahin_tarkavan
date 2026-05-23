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
        Schema::create('player_voting_syatems', function (Blueprint $table) {
            $table->id();
            $table->boolean('voted')->default(false);
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('vote_for_player_id');
            $table->unsignedBigInteger('coach_id')->nullable();
            $table->enum('vote_type', ['provencial', 'professional'])->default('provencial');
            $table->foreign('player_id')->references('id')->on('athlete_profiles')->onDelete('cascade');
            $table->foreign('coach_id')->references('id')->on('coaches')->onDelete('cascade');
            $table->foreign('vote_for_player_id')->references('id')->on('athlete_profiles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_voting_syatems');
    }
};
