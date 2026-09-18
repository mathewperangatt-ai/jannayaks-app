<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('event_key', 96);
            $table->string('event_type', 48);
            $table->timestampTz('recorded_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['membership_id', 'event_key']);
            $table->index(['event_type', 'recorded_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE membership_lifecycle_events
                ADD CONSTRAINT membership_lifecycle_events_event_type_check
                CHECK (event_type IN (
                    'reminder_before_expiry',
                    'reminder_after_expiry',
                    'deactivated',
                    'renewed',
                    'reactivated',
                    'membership_started'
                ))
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE membership_lifecycle_events DROP CONSTRAINT IF EXISTS membership_lifecycle_events_event_type_check');
        }

        Schema::dropIfExists('membership_lifecycle_events');
    }
};
