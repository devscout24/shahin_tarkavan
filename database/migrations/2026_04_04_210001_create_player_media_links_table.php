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
        Schema::create('player_media_link', function (Blueprint $table) {
            $table->id();
            $table->string('link')->nullable();
            $table->enum('status', ['reels', 'youtube', 'hudi_profile', 'image'])->default('youtube');

            $table->unsignedBigInteger('player_profile_id');

            $table->foreign('player_profile_id')
                ->references('id')
                ->on('athlete_profiles')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_media_link');
    }
};
