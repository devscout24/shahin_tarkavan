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
        Schema::create('endorses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_strength_id');
            $table->unsignedBigInteger('athlete_profile_id');
            $table->integer('strength_count');
            $table->unsignedBigInteger('endorced_by');
            $table->timestamps();

            // Foreign Keys
            $table->foreign('player_strength_id')->references('id')->on('player_strengths')->onDelete('cascade');
            $table->foreign('athlete_profile_id')->references('id')->on('athlete_profiles')->onDelete('cascade');
            $table->foreign('endorced_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endorses');
    }
};
