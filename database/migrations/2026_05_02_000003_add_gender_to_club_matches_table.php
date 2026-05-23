<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_matches', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('field_opportunity');
        });
    }

    public function down(): void
    {
        Schema::table('club_matches', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
