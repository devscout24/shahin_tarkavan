<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('audience', ['player', 'coach'])->default('coach');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['audience', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_options');
    }
};
