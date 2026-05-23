<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE er_programs MODIFY coach_id BIGINT UNSIGNED NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE er_programs ALTER COLUMN coach_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('UPDATE er_programs SET coach_id = (SELECT id FROM coaches LIMIT 1) WHERE coach_id IS NULL');
            DB::statement('ALTER TABLE er_programs MODIFY coach_id BIGINT UNSIGNED NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('UPDATE er_programs SET coach_id = (SELECT id FROM coaches LIMIT 1) WHERE coach_id IS NULL');
            DB::statement('ALTER TABLE er_programs ALTER COLUMN coach_id SET NOT NULL');
        }
    }
};