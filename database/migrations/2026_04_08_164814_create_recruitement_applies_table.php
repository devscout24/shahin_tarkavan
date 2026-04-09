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
        Schema::create('recruitement_applies', function (Blueprint $table) {
            $table->id();
             $table->unsignedBigInteger('recruitment_id');
                    $table->unsignedBigInteger('team_id');


                    $table->unsignedBigInteger('user_id');


                    $table->unsignedBigInteger('child_id')->nullable();

                    $table->unsignedBigInteger('club_id');

                    $table->enum('type', ['player', 'coach', 'parent'])->default('player');
                    $table->enum('status', ['pending', 'accepted', 'rejected', 'scheduled'])->default('pending');



                    // Foreign Keys
                    $table->foreign('recruitment_id')
                        ->references('id')
                        ->on('club_recruitments')
                        ->onDelete('cascade');

                    $table->foreign('team_id')
                        ->references('id')
                        ->on('club_teams')
                        ->onDelete('cascade');

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');

                    $table->foreign('child_id')
                        ->references('id')
                        ->on('athlete_profiles')
                        ->onDelete('cascade');

                    $table->foreign('club_id')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recruitement_applies');
    }
};

