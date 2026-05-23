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
        if (! Schema::hasTable('er_program_times')) {
            return;
        }

        Schema::table('er_program_times', function (Blueprint $table) {
            if (! Schema::hasColumn('er_program_times', 'slot_date')) {
                $table->date('slot_date')->nullable()->after('time');
            }

            if (! Schema::hasColumn('er_program_times', 'start_time')) {
                $table->time('start_time')->nullable()->after('slot_date');
            }

            if (! Schema::hasColumn('er_program_times', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }

            if (! Schema::hasColumn('er_program_times', 'is_available')) {
                $table->boolean('is_available')->default(true)->after('end_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('er_program_times')) {
            return;
        }

        Schema::table('er_program_times', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('er_program_times', 'is_available')) {
                $columns[] = 'is_available';
            }

            if (Schema::hasColumn('er_program_times', 'end_time')) {
                $columns[] = 'end_time';
            }

            if (Schema::hasColumn('er_program_times', 'start_time')) {
                $columns[] = 'start_time';
            }

            if (Schema::hasColumn('er_program_times', 'slot_date')) {
                $columns[] = 'slot_date';
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
