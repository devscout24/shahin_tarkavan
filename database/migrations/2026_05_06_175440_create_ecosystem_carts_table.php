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
        Schema::create('ecosystem_carts', function (Blueprint $table) {
            $table->id();
                $table->unsignedBigInteger('ecosystem_id');
                $table->foreign('ecosystem_id')->references('id')->on('ecosystems')->onDelete('cascade');
                $table->string('title');
                $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecosystem_carts');
    }
};
