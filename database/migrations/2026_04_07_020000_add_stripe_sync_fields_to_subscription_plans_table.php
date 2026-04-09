<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'stripe_product_id')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->string('stripe_product_id')->nullable()->after('trial_days');
            });
        }

        if (!Schema::hasColumn('subscription_plans', 'stripe_price_id')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
            });
        }

        if (!Schema::hasColumn('subscription_plans', 'is_stripe_synced')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->boolean('is_stripe_synced')->default(false)->after('stripe_price_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'is_stripe_synced')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('is_stripe_synced');
            });
        }

        if (Schema::hasColumn('subscription_plans', 'stripe_price_id')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('stripe_price_id');
            });
        }

        if (Schema::hasColumn('subscription_plans', 'stripe_product_id')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('stripe_product_id');
            });
        }
    }
};
