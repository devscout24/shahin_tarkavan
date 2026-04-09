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
        Schema::create('er_program_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('er_program_id')->constrained('er_programs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('payment_status', ['paid', 'pending', 'failed'])->default('paid');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->unique(['er_program_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('er_program_purchases');
    }
};
