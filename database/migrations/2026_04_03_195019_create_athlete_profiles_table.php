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
        Schema::create('athlete_profiles', function (Blueprint $table) {
               $table->id();

                $table->string('name');
                $table->string('last_name')->nullable();
                $table->timestamp('dob');
                $table->enum('gender', ['male', 'female']);
                $table->string('nationality');
                $table->string('email')->nullable();

                $table->string('sports')->nullable();
                $table->integer('jersey_number')->nullable();
                $table->string('dominant_foot')->nullable();

                $table->string('club_team')->nullable();

                // Relations
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();

                $table->string('image')->nullable();

                $table->unsignedBigInteger('primary_position')->nullable();
                $table->unsignedBigInteger('secondary_position')->nullable();

                $table->text('athlete_biography')->nullable();

                $table->enum('privacy_settings', ['public', 'coach_and_team', 'private','only_player'])
                    ->default('public');

                // Stats
                $table->integer('total_played_games')->default(0);
                $table->integer('goals')->default(0);
                $table->integer('assist')->default(0);
                $table->integer('yellow_cards')->default(0);
                $table->integer('red_cards')->default(0);
                 $table->enum('status', ['active', 'block','invite','cancel'])->default('active');
                // Goalkeeper stats
                $table->integer('clean_sheets')->default(0);
                $table->integer('total_saves')->default(0);
                $table->boolean('is_blocked')->default(false);
                $table->string('city')->nullable();
                $table->string('country')->nullable();

                $table->string('preview')->nullable();
                
                $table->string('facebook_link')->nullable();
                $table->string('twitter_link')->nullable();
                $table->string('instagram_link')->nullable();
                $table->string('tiktok_link')->nullable();   
                $table->string('whatsapp_link')->nullable(); 
                
                // Foreign Keys
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->foreign('primary_position')
                    ->references('id')
                    ->on('player_positions')
                    ->onDelete('set null');

                $table->foreign('secondary_position')

                    ->references('id')
                    ->on('player_positions')
                    ->onDelete('set null');




            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('athlete_profiles');
    }
};