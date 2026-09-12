<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN (
                'pending',
                'initiated',
                'paid',
                'captured',
                'success',
                'failed',
                'cancelled',
                'expired',
                'refunded',
                'partially_refunded'
            ))
        ");

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_item_type_check');
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_item_type_check
            CHECK (item_type IN (
                'membership',
                'in_memoriam',
                'profile_package',
                'distinguished_interview_addon',
                'application_payment',
                'refund',
                'other'
            ))
        ");

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_event_type_check');
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_event_type_check
            CHECK (event_type IN (
                'payment_link.paid',
                'payment.captured',
                'payment.failed',
                'refund.processed',
                'refund.failed',
                'manual_adjustment',
                'waiver',
                'application_package',
                'distinguished_interview_addon',
                'renewal',
                'in_memoriam_package'
            ))
        ");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('invoice_number', 64)->nullable()->change();
            $table->unique('invoice_number');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('payment_status', 32)->nullable()->after('converted_to_profile_at');
            $table->timestamp('payment_settled_at', 0)->nullable()->after('payment_status');
            $table->string('status', 64)->nullable()->after('payment_settled_at');
            $table->index('payment_status');
            $table->index('status');
        });

        DB::statement("
            ALTER TABLE applications
            ADD CONSTRAINT applications_payment_status_check
            CHECK (payment_status IN ('pending', 'paid', 'waived', 'refunded', 'partially_refunded'))
        ");

        DB::statement("
            ALTER TABLE applications
            ADD CONSTRAINT applications_status_check
            CHECK (status IN (
                'intake_in_progress',
                'interview_in_progress',
                'interview_submitted',
                'direct_submitted',
                'payment_pending',
                'payment_complete_awaiting_interview',
                'awaiting_editorial_review',
                'in_editorial_review',
                'editorial_approved',
                'editorial_revision_requested',
                'awaiting_publication',
                'published',
                'archived',
                'cancelled',
                'refunded'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_status_check');
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_payment_status_check');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['status']);
            $table->dropColumn('status');
            $table->dropColumn('payment_settled_at');
            $table->dropColumn('payment_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_number')->nullable()->change();
            $table->unique('invoice_number');
        });

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_item_type_check');
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_item_type_check
            CHECK (item_type IN (
                'membership',
                'in_memoriam',
                'profile_package',
                'distinguished_interview_addon',
                'refund',
                'other'
            ))
        ");

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN ('pending','success','failed','refunded','partially_refunded'))
        ");

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_event_type_check');
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_event_type_check
            CHECK (event_type IN (
                'payment_link.paid',
                'payment.captured',
                'payment.failed',
                'refund.processed',
                'refund.failed',
                'manual_adjustment',
                'waiver',
                'profile_package',
                'distinguished_interview_addon'
            ))
        ");
    }
};
