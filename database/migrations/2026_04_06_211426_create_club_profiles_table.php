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
        Schema::create('club_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('club_name');
            $table->string('club_logo')->nullable();
            $table->string('email')->nullable();
            $table->string('sports')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
             $table->longText('club_description')->nullable();

               $table->string('facebook_link')->nullable();
                $table->string('twitter_link')->nullable();
                $table->string('instagram_link')->nullable();
                $table->string('tiktok_link')->nullable();   
                $table->string('whatsapp_link')->nullable(); 

            
            $table->enum("privacy_settings",["public","private",'players','coach_and_players'])->default('public');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_profiles');
    }
};