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
        Schema::create('er_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('sport');
            $table->string('program_name');
            $table->decimal('program_price', 10, 2)->default(0);
            $table->string('program_location')->nullable();
            $table->date('program_start')->nullable();
            $table->date('program_end')->nullable();
            $table->longText('about_program')->nullable();
            $table->decimal('discount_price', 10, 2)->default(0);
            $table->integer('upto_age')->nullable();
            $table->string('program_photo')->nullable();
            $table->enum('status', ['draft', 'active', 'inactive','upcoming'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('er_programs');
    }
};
