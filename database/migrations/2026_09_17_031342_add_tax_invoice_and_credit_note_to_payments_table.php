<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('tax_invoice_number', 64)->nullable()->unique()->after('invoice_issued_at');
            $table->timestamp('tax_invoice_issued_at', 0)->nullable()->after('tax_invoice_number');
            $table->string('credit_note_number', 64)->nullable()->unique()->after('tax_invoice_issued_at');
            $table->timestamp('credit_note_issued_at', 0)->nullable()->after('credit_note_number');

            $table->index('tax_invoice_number');
            $table->index('credit_note_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tax_invoice_number']);
            $table->dropIndex(['credit_note_number']);
            $table->dropColumn([
                'credit_note_issued_at',
                'credit_note_number',
                'tax_invoice_issued_at',
                'tax_invoice_number',
            ]);
        });
    }
};
