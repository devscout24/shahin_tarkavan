<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_recruitments', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('recruitment_type');
        });
    }

    public function down(): void
    {
        Schema::table('club_recruitments', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
