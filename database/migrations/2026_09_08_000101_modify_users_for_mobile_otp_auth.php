<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 32)->nullable()->after('email');
            $table->timestamp('mobile_verified_at', 0)->nullable()->after('mobile');
            $table->string('account_status', 32)->default('active')->after('remember_token');

            $table->index('mobile_verified_at');

            $table->string('name', 255)->nullable(false)->change();
            $table->string('name', 255)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
        });

        DB::statement('CREATE UNIQUE INDEX users_mobile_unique ON users (mobile) WHERE mobile IS NOT NULL');

        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_account_status_check
            CHECK (account_status IN ('active','suspended'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_status_check');
        DB::statement('DROP INDEX IF EXISTS users_mobile_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['mobile_verified_at']);
            $table->dropColumn(['account_status', 'mobile_verified_at', 'mobile']);

            $table->string('name', 255)->nullable(false)->change();
            $table->string('email', 255)->nullable(false)->change();
        });
    }
};
