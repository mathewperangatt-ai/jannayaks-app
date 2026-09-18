<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            // Deterministic retention boundary from ends_on (+ retention_years), not deactivation time.
            $table->date('retention_until')->nullable()->after('renewal_due_on');
            $table->timestampTz('lapsed_at')->nullable()->after('retention_until');
            $table->index(['status', 'ends_on']);
            $table->index('retention_until');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['status', 'ends_on']);
            $table->dropIndex(['retention_until']);
            $table->dropColumn(['retention_until', 'lapsed_at']);
        });
    }
};
