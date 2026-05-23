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
        Schema::create('trusted_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('rating')->default('5.0');
            $table->text('review_text');
            $table->string('user_name');
            $table->string('user_designation');
            $table->string('user_image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_reviews');
    }
};