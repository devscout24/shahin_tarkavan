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
        Schema::create('club_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_team_id');
            $table->timestamp('available_date');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('opponent_club_id')->nullable();
            $table->string("location")->nullable();
            $table->string("field_opportunity")->nullable();
            $table->decimal("upto_age", 10, 2)->nullable();
            $table->foreign('club_team_id')->references('id')->on('club_teams')->onDelete('cascade');
            $table->foreign('opponent_club_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_matches');
    }
};
