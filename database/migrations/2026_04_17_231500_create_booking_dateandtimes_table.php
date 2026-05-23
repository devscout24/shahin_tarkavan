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
        Schema::create('booking_dateandtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_booking_id')->constrained('program_bookings')->cascadeOnDelete();
            $table->foreignId('booking_time_id')->constrained('er_program_times')->cascadeOnDelete();
            $table->date('booking_date');
            $table->enum('booking_type', ['one_one', 'group'])->default('one_one');
            $table->string('time_label')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique('program_booking_id');
            $table->index(['booking_time_id', 'booking_date', 'booking_type'], 'booking_slot_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_dateandtimes');
    }
};
