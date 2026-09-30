<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('current_activity', 255)
                ->nullable()
                ->after('profession');
            $table->index('current_activity');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['current_activity']);
            $table->dropColumn('current_activity');
        });
    }
};
