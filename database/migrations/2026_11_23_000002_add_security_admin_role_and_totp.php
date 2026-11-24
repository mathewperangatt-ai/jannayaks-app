<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 3 — Jannayaks Security Administrator.
 *
 * Adds the security_admin role (exclusive authority over published profile
 * administration) and TOTP two-factor columns. TOTP secrets and recovery
 * codes are encrypted at rest with the application key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable()->after('password');
            $table->timestamp('totp_enabled_at')->nullable()->after('totp_secret');
            $table->text('totp_recovery_codes')->nullable()->after('totp_enabled_at');
        });

        DB::table('users')
            ->where('role', 'published_profile_admin')
            ->update(['role' => 'security_admin']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('member', 'admin', 'editor', 'support', 'security_admin'))");
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'security_admin')
            ->update(['role' => 'admin']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('member', 'admin', 'editor', 'support'))");
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'totp_secret',
                'totp_enabled_at',
                'totp_recovery_codes',
            ]);
        });
    }
};
