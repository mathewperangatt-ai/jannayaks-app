<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('preferred_whatsapp_mobile', 32)
                ->nullable()
                ->after('preferred_contact_mobile');

            // Weekly phone/WhatsApp export bookkeeping (temporary programme):
            // the batch id claims a row for an in-flight export; the timestamp
            // marks rows whose numbers were successfully exported and removed.
            $table->string('contact_export_batch_id', 64)->nullable()->index();
            $table->timestamp('contact_exported_at', 0)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['preferred_whatsapp_mobile', 'contact_export_batch_id', 'contact_exported_at']);
        });
    }
};
