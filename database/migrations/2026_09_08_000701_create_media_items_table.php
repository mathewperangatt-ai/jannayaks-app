<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('mediable_type');
            $table->unsignedBigInteger('mediable_id');
            $table->index(['mediable_type', 'mediable_id']);
            $table->index(['mediable_type', 'mediable_id', 'display_order']);

            $table->string('media_type', 32);
            $table->text('storage_path_key')->unique();
            $table->string('disk', 32)->default('r2');

            $table->text('caption')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedInteger('display_order')->default(1);

            $table->foreignId('uploaded_by_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->string('privacy', 16)->default('public');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE media_items
            ADD CONSTRAINT media_items_media_type_check
            CHECK (media_type IN ('profile_photo','gallery_image','document','other'))
        ");

        DB::statement("
            ALTER TABLE media_items
            ADD CONSTRAINT media_items_privacy_check
            CHECK (privacy IN ('public','unlisted','private'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_privacy_check');
        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_media_type_check');
        Schema::dropIfExists('media_items');
    }
};
