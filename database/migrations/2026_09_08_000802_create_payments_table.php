<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')
                ->nullable()
                ->constrained('memberships')
                ->onDelete('cascade');
            $table->unsignedBigInteger('in_memoriam_profile_id')->nullable();
            $table->foreignId('profile_id')
                ->nullable()
                ->constrained('profiles')
                ->onDelete('set null');

            $table->string('transaction_reference', 128)->unique();
            $table->string('gateway', 32)->default('unknown');
            $table->string('item_type', 32)->default('membership');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at', 0)->nullable();
            $table->timestamp('captured_at', 0)->nullable();
            $table->string('method', 32)->nullable();

            $table->string('gateway_event_id', 128)->nullable();
            $table->string('gateway_payment_id', 128)->nullable();
            $table->string('payment_method_type', 32)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 255)->nullable();

            $table->timestamps();

            $table->index(['membership_id', 'status', 'created_at']);
            $table->index(['in_memoriam_profile_id', 'status', 'created_at']);
            $table->index('profile_id');
            $table->index('item_type');
        });

        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN ('pending','success','failed','refunded','partially_refunded'))
        ");

        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_item_type_check
            CHECK (item_type IN ('membership','in_memoriam','other'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_item_type_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        Schema::dropIfExists('payments');
    }
};
