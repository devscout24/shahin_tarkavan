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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('iso_code', 10)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['country_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('address')->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('country_id')->constrained('cities')->nullOnDelete();
        });

        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country')->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('country_id')->constrained('cities')->nullOnDelete();
        });

        Schema::table('coaches', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country')->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('country_id')->constrained('cities')->nullOnDelete();
        });

        Schema::table('club_profiles', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country')->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('country_id')->constrained('cities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::table('coaches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
