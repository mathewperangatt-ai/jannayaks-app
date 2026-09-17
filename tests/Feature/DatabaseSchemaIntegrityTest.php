<?php

namespace Tests\Feature;

use App\Models\GeoDistrict;
use App\Models\GeoLocalBody;
use App\Models\GeoState;
use App\Models\GeoWard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaIntegrityTest extends TestCase
{
    private const TABLES = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'profiles',
        'profile_verifications',
        'profile_public_offices',
        'geo_states',
        'geo_districts',
        'geo_local_bodies',
        'geo_wards',
        'profile_geographies',
        'editorial_contents',
        'ai_editorial_runs',
        'editorial_claim_traces',
        'editorial_revision_requests',
        'editorial_customer_approvals',
        'media_items',
        'memberships',
        'payments',
        'consent_records',
        'in_memoriam_profiles',
        'in_memoriam_geographies',
        'in_memoriam_public_offices',
        'in_memoriam_editorial_contents',
    ];

    public function test_all_required_tables_exist(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Required table '$table' is missing from database schema"
            );
        }
    }

    public function test_profiles_has_no_denormalised_pii_columns(): void
    {
        $columns = Schema::getColumnListing('profiles');
        $forbidden = ['phone_number', 'mobile', 'email'];
        foreach ($forbidden as $c) {
            $this->assertNotContains(
                $c,
                $columns,
                "profiles must NOT contain column '$c' (must live on users only)"
            );
        }
    }

    public function test_profile_verifications_stores_no_id_document_values(): void
    {
        $columns = Schema::getColumnListing('profile_verifications');
        $forbidden = ['epic_number', 'document_number', 'document_id', 'scan_path', 'image_path'];
        foreach ($forbidden as $c) {
            $this->assertNotContains(
                $c,
                $columns,
                "profile_verifications must NOT contain raw id/document column '$c'"
            );
        }
    }

    public function test_payments_stores_no_pii_columns(): void
    {
        $columns = Schema::getColumnListing('payments');
        $forbidden = ['email', 'mobile', 'phone', 'pincode', 'pan', 'aadhaar'];
        foreach ($forbidden as $c) {
            $this->assertNotContains(
                $c,
                $columns,
                "payments must NOT copy PII column '$c' (must be looked up via profile->user only)"
            );
        }
    }

    public function test_profiles_status_check_constraint_exists(): void
    {
        $exists = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_constraint c
             JOIN pg_class cls ON cls.oid = c.conrelid
             JOIN pg_namespace nsp ON nsp.oid = cls.relnamespace
             WHERE nsp.nspname = 'public'
               AND cls.relname = 'profiles'
               AND c.conname = 'profiles_status_check'"
        );
        $this->assertNotEmpty(
            $exists,
            'CHECK constraint profiles_status_check missing on profiles.status'
        );
    }

    public function test_geo_local_bodies_type_check_constraint_exists(): void
    {
        $exists = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_constraint c
             JOIN pg_class cls ON cls.oid = c.conrelid
             JOIN pg_namespace nsp ON nsp.oid = cls.relnamespace
             WHERE nsp.nspname = 'public'
               AND cls.relname = 'geo_local_bodies'
               AND c.conname = 'geo_local_bodies_type_check'"
        );
        $this->assertNotEmpty(
            $exists,
            'CHECK constraint geo_local_bodies_type_check missing'
        );
    }

    public function test_profiles_user_id_unique_index_exists(): void
    {
        $idx = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_indexes
             WHERE schemaname = 'public'
               AND tablename = 'profiles'
               AND indexname = 'profiles_user_id_unique'"
        );
        $this->assertNotEmpty(
            $idx,
            'Unique index profiles_user_id_unique required for 1:1 user->profile'
        );
    }

    public function test_geography_reference_tables_are_empty_foundation(): void
    {
        $this->assertSame(1, GeoState::query()->count(), 'geo_states must contain exactly 1 Kerala state row after Phase 2 seed');
        $this->assertSame(14, GeoDistrict::query()->count(), 'geo_districts must contain 14 Kerala districts after Phase 2 seed');
        $this->assertSame(1200, GeoLocalBody::query()->count(), 'geo_local_bodies must contain 1,200 Kerala local bodies after Phase 2 seed');
        $this->assertSame(23611, GeoWard::query()->count(), 'geo_wards must contain 23,611 Kerala wards after Phase 2 seed');

        $typeCounts = GeoLocalBody::query()
            ->selectRaw('type, count(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type')
            ->all();
        $this->assertSame(941, (int) ($typeCounts['grama_panchayat'] ?? 0), 'local bodies: grama_panchayat count must be 941');
        $this->assertSame(152, (int) ($typeCounts['block_panchayat'] ?? 0), 'local bodies: block_panchayat count must be 152');
        $this->assertSame(87, (int) ($typeCounts['municipality'] ?? 0), 'local bodies: municipality count must be 87');
        $this->assertSame(14, (int) ($typeCounts['district_panchayat'] ?? 0), 'local bodies: district_panchayat count must be 14');
        $this->assertSame(6, (int) ($typeCounts['municipal_corporation'] ?? 0), 'local bodies: municipal_corporation count must be 6');

        $placeholders = [
            'B05049005' => 'A',
            'B05049009' => 'B',
            'B05049010' => 'C',
            'B05049011' => 'D',
            'B05049013' => 'E',
        ];
        foreach ($placeholders as $wardCode => $expectedName) {
            $ward = GeoWard::query()->where('ward_code', $wardCode)->first();
            $this->assertNotNull($ward, "Temporary placeholder ward {$wardCode} must exist after Phase 2 seed");
            $this->assertSame($expectedName, $ward->name, "Temporary placeholder ward {$wardCode} must have name '{$expectedName}'");
        }
    }

    public function test_profile_geographies_postal_code_is_varchar_not_integer(): void
    {
        $type = DB::selectOne(
            "SELECT data_type, character_maximum_length AS len
             FROM information_schema.columns
             WHERE table_catalog = current_database()
               AND table_schema = 'public'
               AND table_name = 'profile_geographies'
               AND column_name = 'postal_code'"
        );
        $this->assertNotNull($type);
        $this->assertSame(
            'character varying',
            $type->data_type,
            "postal_code must be stored as text/varchar, actual type: {$type->data_type}"
        );
        $this->assertGreaterThan(0, $type->len);
    }

    public function test_media_items_has_no_binary_bytea_columns(): void
    {
        $bytea = DB::select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_catalog = current_database()
               AND table_schema = 'public'
               AND table_name = 'media_items'
               AND data_type = 'bytea'"
        );
        $this->assertEmpty(
            $bytea,
            'media_items must not store binaries. Found bytea columns: '.
            implode(',', array_column($bytea, 'column_name'))
        );
    }

    public function test_payments_uses_sanitised_reconciliation_columns_not_raw_jsonb(): void
    {
        $columns = Schema::getColumnListing('payments');
        $this->assertNotContains(
            'raw_gateway_payload',
            $columns,
            'payments must NOT contain unsanitised raw_gateway_payload JSONB (may contain PII)'
        );
        foreach (['gateway_event_id', 'gateway_payment_id', 'payment_method_type', 'card_last4', 'error_code', 'error_message', 'item_type', 'in_memoriam_profile_id', 'invoice_number', 'tax_invoice_number', 'credit_note_number'] as $required) {
            $this->assertContains(
                $required,
                $columns,
                "payments must contain sanitised reconciliation column '$required'"
            );
        }
    }

    public function test_consent_records_allows_changelog_history_not_single_row(): void
    {
        $uniqueIdx = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_indexes
             WHERE schemaname = 'public'
               AND tablename = 'consent_records'
               AND indexname = 'consent_records_user_id_consent_key_unique'"
        );
        $this->assertEmpty(
            $uniqueIdx,
            'consent_records UNIQUE(user_id,consent_key) must NOT exist — required to log consent → revoke → consent history'
        );
        $columns = Schema::getColumnListing('consent_records');
        $this->assertNotContains(
            'revoked_at',
            $columns,
            'consent_records should not have revoked_at; instead consented=false + action_at timestamp per event'
        );
        $this->assertContains('action_at', $columns, 'consent_records action_at column (event timestamp) missing');
    }

    public function test_financial_fk_set_null_does_not_block_profile_erasure(): void
    {
        $membershipFk = DB::selectOne(
            "SELECT c.confdeltype AS delete_action
             FROM pg_constraint c
             JOIN pg_class cls ON cls.oid = c.conrelid
             JOIN pg_namespace nsp ON nsp.oid = cls.relnamespace
             JOIN pg_class fcls ON fcls.oid = c.confrelid
             WHERE nsp.nspname = 'public'
               AND cls.relname = 'memberships'
               AND fcls.relname = 'profiles'
               AND c.contype = 'f'"
        );
        $this->assertNotNull($membershipFk);
        $this->assertSame(
            'n',
            $membershipFk->delete_action,
            "memberships.profile_id FK ON DELETE must be SET NULL (confdeltype='n') so profile erasure is not blocked; got '{$membershipFk->delete_action}'"
        );

        $paymentsProfileFk = DB::selectOne(
            "SELECT c.confdeltype AS delete_action
             FROM pg_constraint c
             JOIN pg_class cls ON cls.oid = c.conrelid
             JOIN pg_namespace nsp ON nsp.oid = cls.relnamespace
             JOIN pg_class fcls ON fcls.oid = c.confrelid
             WHERE nsp.nspname = 'public'
               AND cls.relname = 'payments'
               AND fcls.relname = 'profiles'
               AND c.contype = 'f'"
        );
        $this->assertNotNull($paymentsProfileFk);
        $this->assertSame(
            'n',
            $paymentsProfileFk->delete_action,
            "payments.profile_id FK ON DELETE must be SET NULL; got '{$paymentsProfileFk->delete_action}'"
        );
    }

    public function test_in_memoriam_core_structure_separates_deceased_and_commissioner(): void
    {
        $cols = Schema::getColumnListing('in_memoriam_profiles');
        foreach ([
            'commissioner_contact_name',
            'commissioner_contact_mobile',
            'commissioner_display_consent',
            'deceased_full_name',
            'deceased_date_of_birth',
            'deceased_date_of_death',
            'status',
            'commission_amount',
            'commission_gst_amount',
            'hosting_starts_on',
            'hosting_ends_on',
            'is_sealed',
            'last_admin_corrected_at',
        ] as $required) {
            $this->assertContains(
                $required,
                $cols,
                "in_memoriam_profiles must contain column '$required' (§1 In Memoriam requirement)"
            );
        }
    }

    public function test_in_memoriam_support_tables_exist_with_correct_fks(): void
    {
        $this->assertTrue(Schema::hasTable('in_memoriam_geographies'));
        $this->assertTrue(Schema::hasTable('in_memoriam_public_offices'));
        $this->assertTrue(Schema::hasTable('in_memoriam_editorial_contents'));

        $geoCols = Schema::getColumnListing('in_memoriam_geographies');
        $this->assertContains('postal_code', $geoCols);
        $this->assertContains('locality_place', $geoCols);
        $this->assertContains('district_id', $geoCols);
        $this->assertContains('country_code', $geoCols);

        $geoType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns
             WHERE table_catalog = current_database()
               AND table_schema='public'
               AND table_name='in_memoriam_geographies'
               AND column_name='postal_code'"
        );
        $this->assertSame('character varying', $geoType?->data_type, 'in_memoriam_geographies.postal_code must be varchar not integer');
    }

    public function test_in_memoriam_editorial_has_version_history_unique(): void
    {
        $idx = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_indexes
             WHERE schemaname='public'
               AND tablename='in_memoriam_editorial_contents'
               AND indexname='im_editorial_profile_lang_version_unique'"
        );
        $this->assertNotEmpty(
            $idx,
            'in_memoriam_editorial_contents must have UNIQUE(profile,language,version_number) for version history'
        );
    }

    public function test_in_memoriam_profile_status_check_and_sealed_displayname_index(): void
    {
        $chk = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_constraint c
             JOIN pg_class cls ON cls.oid = c.conrelid
             JOIN pg_namespace nsp ON nsp.oid = cls.relnamespace
             WHERE nsp.nspname='public'
               AND cls.relname='in_memoriam_profiles'
               AND c.conname='in_memoriam_profiles_status_check'"
        );
        $this->assertNotEmpty($chk, 'Missing in_memoriam_profiles_status_check constraint');

        $idx = DB::selectOne(
            "SELECT 1 AS present FROM pg_indexes
             WHERE schemaname='public'
               AND tablename='in_memoriam_profiles'
               AND indexname='in_memoriam_display_name_idx'"
        );
        $this->assertNotEmpty(
            $idx,
            'Missing partial display name index on in_memoriam_profiles.deceased_display_name'
        );
    }

    public function test_bio_headline_btree_indexes_present_for_search(): void
    {
        $profilesIdx = DB::selectOne(
            "SELECT 1 AS present FROM pg_indexes
             WHERE schemaname='public'
               AND tablename='profiles'
               AND indexname='profiles_bio_headline_index'"
        );
        $this->assertNotEmpty(
            $profilesIdx,
            'B-Tree index on profiles.bio_headline missing for §20 search preparation'
        );

        $imIdx = DB::selectOne(
            "SELECT 1 AS present FROM pg_indexes
             WHERE schemaname='public'
               AND tablename='in_memoriam_profiles'
               AND indexname='in_memoriam_profiles_bio_headline_index'"
        );
        $this->assertNotEmpty(
            $imIdx,
            'B-Tree index on in_memoriam_profiles.bio_headline missing for §20 search preparation'
        );
    }

    public function test_staff_action_logs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('staff_action_logs'));

        foreach ([
            'actor_user_id',
            'action',
            'subject_type',
            'subject_id',
            'before',
            'after',
            'ip_address',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('staff_action_logs', $column),
                "staff_action_logs missing column {$column}"
            );
        }
    }

    public function test_ai_editorial_generation_tables_and_links_exist(): void
    {
        $this->assertTrue(Schema::hasTable('ai_editorial_runs'));
        $this->assertTrue(Schema::hasTable('editorial_claim_traces'));

        foreach ([
            'application_id',
            'profile_id',
            'provider',
            'model',
            'status',
            'stage',
            'english_editorial_content_id',
            'malayalam_editorial_content_id',
            'input_fingerprint',
            'error_code',
            'error_message',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('ai_editorial_runs', $column),
                "ai_editorial_runs missing column {$column}"
            );
        }

        foreach ([
            'source_editorial_content_id',
            'generation_run_id',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('editorial_contents', $column),
                "editorial_contents missing column {$column}"
            );
        }

        foreach ([
            'editorial_content_id',
            'claim_excerpt',
            'question_id',
            'interview_answer_id',
            'source_material_id',
            'mapped_to_source',
            'sort_order',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('editorial_claim_traces', $column),
                "editorial_claim_traces missing column {$column}"
            );
        }

        $runningUnique = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_indexes
             WHERE schemaname = 'public'
               AND tablename = 'ai_editorial_runs'
               AND indexname = 'ai_editorial_runs_one_running_per_application'"
        );
        $this->assertNotEmpty(
            $runningUnique,
            'ai_editorial_runs must enforce at most one running generation per application'
        );
    }

    public function test_customer_editorial_preview_and_revision_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('editorial_revision_requests'));
        $this->assertTrue(Schema::hasTable('editorial_customer_approvals'));

        foreach ([
            'included_revision_rounds_used',
            'customer_preview_released_at',
            'preview_english_editorial_content_id',
            'preview_malayalam_editorial_content_id',
            'customer_approved_at',
            'customer_approved_english_editorial_content_id',
            'customer_approved_by_user_id',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('applications', $column),
                "applications missing column {$column}"
            );
        }

        $activeApprovalUnique = DB::selectOne(
            "SELECT 1 AS present
             FROM pg_indexes
             WHERE schemaname = 'public'
               AND tablename = 'editorial_customer_approvals'
               AND indexname = 'editorial_customer_approvals_one_active_per_application'"
        );
        $this->assertNotEmpty(
            $activeApprovalUnique,
            'editorial_customer_approvals must enforce one active approval per application'
        );
    }
}
