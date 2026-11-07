<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when settlement documents (receipt / GST tax invoice / credit note)
 * were emailed to the customer's registered address. Additive and nullable:
 * existing payment rows keep null until an email is delivered, and delivery
 * failures can be retried without duplicating issued documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('invoice_emailed_at')->nullable()->after('credit_note_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('invoice_emailed_at');
        });
    }
};
