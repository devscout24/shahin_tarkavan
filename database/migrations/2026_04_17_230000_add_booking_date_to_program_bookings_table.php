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
        if (! Schema::hasTable('program_bookings')) {
            return;
        }

        Schema::table('program_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('program_bookings', 'booking_date')) {
                $table->date('booking_date')->nullable()->after('booking_time_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('program_bookings') || ! Schema::hasColumn('program_bookings', 'booking_date')) {
            return;
        }

        Schema::table('program_bookings', function (Blueprint $table) {
            $table->dropColumn('booking_date');
        });
    }
};
