<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mobile', 32);
            $table->string('otp_hash', 255);
            $table->timestamp('expires_at', 0);
            $table->timestamp('used_at', 0)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['mobile', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
