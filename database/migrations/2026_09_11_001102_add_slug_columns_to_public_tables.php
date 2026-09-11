<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('slug', 128)
                ->nullable()
                ->unique()
                ->after('bio_headline');

            $table->timestamp('slug_generated_at', 0)->nullable();
            $table->timestamp('slug_changed_at', 0)->nullable();
            $table->string('previous_slug', 128)->nullable();
        });

        Schema::table('in_memoriam_profiles', function (Blueprint $table) {
            $table->string('slug', 128)
                ->nullable()
                ->unique()
                ->after('display_name');

            $table->timestamp('slug_generated_at', 0)->nullable();
            $table->timestamp('slug_changed_at', 0)->nullable();
            $table->string('previous_slug', 128)->nullable();
        });

        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();

            $table->string('old_slug', 128)->unique();
            $table->string('new_slug', 128);

            $table->string('redirectable_type', 64);
            $table->unsignedBigInteger('redirectable_id');

            $table->timestamp('expires_at', 0)->nullable();
            $table->timestamps();

            $table->index(['redirectable_type', 'redirectable_id']);
            $table->index('new_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');

        Schema::table('in_memoriam_profiles', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn([
                'previous_slug',
                'slug_changed_at',
                'slug_generated_at',
                'slug',
            ]);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn([
                'previous_slug',
                'slug_changed_at',
                'slug_generated_at',
                'slug',
            ]);
        });
    }
};
