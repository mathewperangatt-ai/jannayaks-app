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
            $table->foreignId('application_id')
                ->nullable()
                ->after('membership_id')
                ->constrained('applications')
                ->onDelete('set null');

            $table->string('razorpay_link_id', 128)->nullable()->after('card_last4');
            $table->text('razorpay_link_url')->nullable()->after('razorpay_link_id');

            $table->string('razorpay_order_id', 128)->nullable()->after('razorpay_link_url');
            $table->string('event_type', 48)->nullable()->after('razorpay_order_id');

            $table->decimal('base_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('taxable_amount', 12, 2)->nullable()->after('base_amount');
            $table->decimal('gst_rate_percent', 5, 2)->nullable()->after('taxable_amount');
            $table->decimal('cgst_amount', 12, 2)->nullable()->after('gst_rate_percent');
            $table->decimal('sgst_amount', 12, 2)->nullable()->after('cgst_amount');
            $table->decimal('igst_amount', 12, 2)->nullable()->after('sgst_amount');

            $table->string('waiver_reason', 255)->nullable()->after('error_message');
            $table->foreignId('waived_by_user_id')
                ->nullable()
                ->after('waiver_reason')
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamp('waived_at', 0)->nullable()->after('waived_by_user_id');

            $table->decimal('refund_amount', 12, 2)->nullable()->after('waived_at');
            $table->timestamp('refunded_at', 0)->nullable()->after('refund_amount');
            $table->string('refund_gateway_id', 128)->nullable()->after('refunded_at');
            $table->string('refund_note', 255)->nullable()->after('refund_gateway_id');

            $table->unsignedBigInteger('invoice_number')->nullable()->unique()->after('refund_note');
            $table->timestamp('invoice_issued_at', 0)->nullable()->after('invoice_number');

            $table->index('application_id');
            $table->index('razorpay_link_id');
            $table->index('razorpay_order_id');
            $table->index('invoice_number');
            $table->index('event_type');
        });

        DB::statement("
            ALTER TABLE payments
            DROP CONSTRAINT IF EXISTS payments_item_type_check
        ");

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
                'waiver'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_event_type_check');

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_item_type_check');
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_item_type_check
            CHECK (item_type IN ('membership','in_memoriam','other'))
        ");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropIndex(['invoice_number']);
            $table->dropIndex(['razorpay_order_id']);
            $table->dropIndex(['razorpay_link_id']);
            $table->dropIndex(['application_id']);

            $table->dropConstrainedForeignId('waived_by_user_id');
            $table->dropConstrainedForeignId('application_id');

            $table->dropColumn([
                'invoice_issued_at',
                'invoice_number',
                'refund_note',
                'refund_gateway_id',
                'refunded_at',
                'refund_amount',
                'waived_at',
                'waiver_reason',
                'igst_amount',
                'sgst_amount',
                'cgst_amount',
                'gst_rate_percent',
                'taxable_amount',
                'base_amount',
                'event_type',
                'razorpay_order_id',
                'razorpay_link_url',
                'razorpay_link_id',
            ]);
        });
    }
};
