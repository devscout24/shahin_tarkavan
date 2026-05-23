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
            if (! Schema::hasColumn('commissions', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('applies_to')->index();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commissions') || ! Schema::hasColumn('commissions', 'user_id')) {
            return;
        }

        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
