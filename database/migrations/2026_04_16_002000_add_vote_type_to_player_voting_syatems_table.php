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
        if (! Schema::hasTable('player_voting_syatems')) {
            return;
        }

        Schema::table('player_voting_syatems', function (Blueprint $table) {
            if (! Schema::hasColumn('player_voting_syatems', 'vote_type')) {
                $table->enum('vote_type', ['provencial', 'professional'])->default('provencial')->after('coach_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('player_voting_syatems') || ! Schema::hasColumn('player_voting_syatems', 'vote_type')) {
            return;
        }

        Schema::table('player_voting_syatems', function (Blueprint $table) {
            $table->dropColumn('vote_type');
        });
    }
};
