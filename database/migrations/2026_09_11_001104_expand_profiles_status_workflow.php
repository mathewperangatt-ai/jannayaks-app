<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_status_check');

        DB::statement("
            ALTER TABLE profiles
            ADD CONSTRAINT profiles_status_check
            CHECK (status IN (
                'draft',
                'payment_pending',
                'submitted',
                'under_editorial_review',
                'verification_pending',
                'customer_preview',
                'revision_requested',
                'member_approved',
                'published',
                'rejected',
                'suspended',
                'inactive',
                'archived'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_status_check');

        DB::statement("
            ALTER TABLE profiles
            ADD CONSTRAINT profiles_status_check
            CHECK (status IN (
                'draft',
                'payment_pending',
                'submitted',
                'under_editorial_review',
                'verification_pending',
                'approved_published',
                'rejected',
                'suspended',
                'archived'
            ))
        ");
    }
};
