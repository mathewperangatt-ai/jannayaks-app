<?php

use App\Models\Profile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable Profile Reference Number (e.g. JN-7K4P2).
     *
     * Stored independently of the public slug: never used for routing, never
     * rewritten when the slug changes. Assigned automatically when a profile
     * row is created (Profile::creating), so it only ever exists on genuine
     * living-profile records; existing rows are backfilled here once.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('reference_code')->nullable()->after('slug');
            $table->unique('reference_code');
        });

        $ids = Profile::query()->whereNull('reference_code')->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            // Direct query update: backfill must not pass through the model
            // immutability guard, and each candidate is uniqueness-checked
            // before write (the unique index remains the hard guarantee).
            Profile::query()->whereKey($id)->update([
                'reference_code' => Profile::generateUniqueReferenceCode(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropUnique(['reference_code']);
            $table->dropColumn('reference_code');
        });
    }
};
