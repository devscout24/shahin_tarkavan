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
        Schema::create('stripe_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // তোমার user (coach/client)

                $table->string('stripe_account_id'); // acct_xxx ⭐ IMPORTANT

                $table->boolean('details_submitted')->default(false);
                $table->boolean('charges_enabled')->default(false);
                $table->boolean('payouts_enabled')->default(false);

                $table->string('onboarding_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_sets');
    }
};
