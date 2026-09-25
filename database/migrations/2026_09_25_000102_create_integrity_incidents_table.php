<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only evidence store for integrity-monitor detections.
        // REPORT-ONLY wave: incidents record evidence; they never alter
        // profiles, media, or content. Resolution columns are reserved for a
        // future administrative wave; no code in this wave mutates rows after
        // creation.
        Schema::create('integrity_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('run_id', 32)->index();
            $table->string('type', 64)->index();
            $table->string('mode', 16)->default('report');
            $table->text('detection_reason');

            $table->jsonb('expected_state');
            $table->jsonb('observed_state');
            // Dedupe key: identical unchanged conditions must not create
            // unbounded duplicate open incidents across runs.
            $table->char('detection_fingerprint', 64)->index();
            $table->jsonb('correlated_event_ids')->nullable();

            // Reserved for future enforcement/administration waves.
            $table->string('status', 16)->default('open')->index();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('resolved_at', 0)->nullable();

            $table->timestampTz('detected_at', 0);
            $table->timestamps();
        });

        // At most one OPEN incident per profile+type+fingerprint.
        DB::statement(
            "CREATE UNIQUE INDEX integrity_incidents_one_open_per_fingerprint
             ON integrity_incidents (profile_id, type, detection_fingerprint)
             WHERE status = 'open'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS integrity_incidents_one_open_per_fingerprint');
        Schema::dropIfExists('integrity_incidents');
    }
};
