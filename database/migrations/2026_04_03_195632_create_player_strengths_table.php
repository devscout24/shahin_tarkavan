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
        Schema::create('player_strengths', function (Blueprint $table) {
            $table->id();

                $table->enum('strength_type', ['technical','tactical', 'mental', 'attacking', 'defending', 'physical', 'aerial']);
                $table->string('strength_name');

                // Relation to athlete_profiles
                $table->unsignedBigInteger('player_profile_id');

                // Forign Key
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
        Schema::dropIfExists('player_strengths');
    }
};
