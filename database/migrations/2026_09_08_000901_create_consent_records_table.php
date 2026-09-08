<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('consent_key', 64);
            $table->boolean('consented');
            $table->timestamp('action_at', 0);
            $table->string('notice_version', 32);

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'consent_key', 'action_at']);
            $table->index(['consent_key', 'consented']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
