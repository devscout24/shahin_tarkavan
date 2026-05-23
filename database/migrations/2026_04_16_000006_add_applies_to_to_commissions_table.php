<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commissions')) {
            return;
        }

        Schema::table('commissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('commissions', 'applies_to')) {
                $table->enum('applies_to', ['all', 'coach', 'club'])
                    ->default('all')
                    ->after('name')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commissions') || ! Schema::hasColumn('commissions', 'applies_to')) {
            return;
        }

        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropColumn('applies_to');
        });
    }
};
