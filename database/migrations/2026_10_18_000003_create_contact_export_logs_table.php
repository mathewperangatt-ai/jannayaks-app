<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_export_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id')->unique();
            $table->string('recipient', 255);
            $table->unsignedInteger('row_count')->default(0);
            $table->string('file_path', 255)->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at', 0)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_export_logs');
    }
};
