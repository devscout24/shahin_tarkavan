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
        Schema::create('program_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id');
            $table->unsignedBigInteger('athlete_profile_id');
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('booking_time_id');
            $table->enum('status', ['pending', 'confirmed', 'cancelled','completed','refund'])->default('pending');
            $table->foreign('program_id')->references('id')->on('er_programs')->onDelete('cascade');
            $table->foreign('athlete_profile_id')->references('id')->on('athlete_profiles')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('booking_time_id')->references('id')->on('er_program_times')->onDelete('cascade');


                $table->string('stripe_payment_intent_id')->nullable();
                $table->string('stripe_session_id')->nullable();
                $table->string('stripe_intend_id')->nullable();

                $table->decimal('amount', 10, 2)->nullable();
                $table->decimal('tax', 5, 2)->nullable();
                $table->decimal('after_commission_amount', 10, 2)->nullable();
                $table->decimal('commission_amount', 10, 2)->nullable();
                $table->decimal('discount', 10, 2)->nullable();
                $table->string('currency')->default('usd');

                $table->enum('payment_status', [
                    'pending',
                    'paid',
                    'failed',
                    'refunded'
                ])->default('pending');




            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_bookings');
    }
};
