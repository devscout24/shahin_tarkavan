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
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name')->nullable();
            $table->date('dob');
            $table->enum('gender', ['male', 'female']);
            $table->enum('status', ['pending', 'approve', 'cancel'])->default('pending');
            $table->string('nationality');
            $table->string('email');
            $table->string('sports');
            $table->string('coach_profile_pic')->nullable();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('coaching_title')->nullable();

            $table->unsignedBigInteger('current_role')->nullable();
            $table->string('years_of_experience')->nullable();
            $table->string('highest_education')->nullable();

            $table->longText('coaching_education')->nullable();
            $table->longText('coaching_philosophy')->nullable();

            $table->boolean('player_centric_approach')->default(false);
            $table->boolean('data_driving_training')->default(false);

            $table->enum('privacy_settings', ['public', 'players_and_teams', 'private'])
                ->default('public');
             
                $table->string('city')->nullable();
                $table->string('country')->nullable();
        
            $table->foreign('current_role')->references('id')->on('coach_positions')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};