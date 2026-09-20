<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('published_english_editorial_content_id')->nullable()->after('customer_approved_by_user_id');
            $table->unsignedBigInteger('published_malayalam_editorial_content_id')->nullable()->after('published_english_editorial_content_id');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->foreign('published_english_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
            $table->foreign('published_malayalam_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
        });

        $this->backfillPublishedBindings();
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign(['published_english_editorial_content_id']);
            $table->dropForeign(['published_malayalam_editorial_content_id']);
            $table->dropColumn([
                'published_english_editorial_content_id',
                'published_malayalam_editorial_content_id',
            ]);
        });
    }

    private function backfillPublishedBindings(): void
    {
        $published = DB::table('applications')
            ->where('status', 'published')
            ->whereNotNull('profile_id')
            ->get(['id', 'profile_id', 'customer_approved_english_editorial_content_id']);

        foreach ($published as $application) {
            $englishId = $application->customer_approved_english_editorial_content_id;
            if ($englishId === null) {
                $englishId = DB::table('editorial_contents')
                    ->where('profile_id', $application->profile_id)
                    ->where('language', 'en')
                    ->where('status', 'approved')
                    ->orderByDesc('version_number')
                    ->value('id');
            }

            if ($englishId === null) {
                continue;
            }

            $malayalamId = DB::table('editorial_contents')
                ->where('profile_id', $application->profile_id)
                ->where('language', 'ml')
                ->where('status', 'approved')
                ->where('source_editorial_content_id', $englishId)
                ->orderByDesc('version_number')
                ->value('id');

            DB::table('applications')->where('id', $application->id)->update([
                'published_english_editorial_content_id' => $englishId,
                'published_malayalam_editorial_content_id' => $malayalamId,
                'updated_at' => now(),
            ]);
        }
    }
};
