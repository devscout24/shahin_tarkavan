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
        Schema::create('stats_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('active_athletes')->default(0);
            $table->unsignedBigInteger('certified_coaches')->default(0);
            $table->unsignedBigInteger('teams')->default(0);
            $table->unsignedBigInteger('session_booked')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats_sections');
    }
};
