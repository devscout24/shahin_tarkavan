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
        Schema::table('program_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('program_bookings', 'payout_status')) {
                $table->string('payout_status')->default('pending')->after('payment_status');
            }

            if (! Schema::hasColumn('program_bookings', 'stripe_transfer_id')) {
                $table->string('stripe_transfer_id')->nullable()->after('stripe_payment_intent_id');
            }

            if (! Schema::hasColumn('program_bookings', 'payout_account_id')) {
                $table->string('payout_account_id')->nullable()->after('stripe_transfer_id');
            }

            if (! Schema::hasColumn('program_bookings', 'payout_amount')) {
                $table->decimal('payout_amount', 10, 2)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('program_bookings', 'payout_at')) {
                $table->timestamp('payout_at')->nullable()->after('payout_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_bookings', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('program_bookings', 'payout_at')) {
                $dropColumns[] = 'payout_at';
            }

            if (Schema::hasColumn('program_bookings', 'payout_amount')) {
                $dropColumns[] = 'payout_amount';
            }

            if (Schema::hasColumn('program_bookings', 'payout_account_id')) {
                $dropColumns[] = 'payout_account_id';
            }

            if (Schema::hasColumn('program_bookings', 'stripe_transfer_id')) {
                $dropColumns[] = 'stripe_transfer_id';
            }

            if (Schema::hasColumn('program_bookings', 'payout_status')) {
                $dropColumns[] = 'payout_status';
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
