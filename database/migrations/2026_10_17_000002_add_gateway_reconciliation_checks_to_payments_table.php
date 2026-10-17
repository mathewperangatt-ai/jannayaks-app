<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('gateway_checked_at')->nullable();
            $table->unsignedSmallInteger('gateway_check_count')->default(0);
        });

        DB::statement("
            CREATE INDEX IF NOT EXISTS payments_open_razorpay_reconciliation_idx
            ON payments (gateway_checked_at, id)
            WHERE status IN ('pending', 'initiated')
              AND razorpay_link_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_open_razorpay_reconciliation_idx');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_checked_at', 'gateway_check_count']);
        });
    }
};
