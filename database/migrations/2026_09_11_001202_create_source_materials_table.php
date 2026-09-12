<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_materials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('material_type', 32)->default('other');

            $table->string('storage_disk', 32)->default('private_uploads');
            $table->text('storage_path');
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_bytes')->nullable();
            $table->string('client_hash_sha256', 64)->nullable();

            $table->text('admin_notes')->nullable();

            $table->timestamp('uploaded_at', 0)->nullable();
            $table->timestamp('purged_at', 0)->nullable();

            $table->timestamps();

            $table->index(['application_id', 'material_type']);
            $table->index(['user_id', 'created_at']);
            $table->index('storage_disk');
        });

        DB::statement("
            ALTER TABLE source_materials
            ADD CONSTRAINT sm_material_type_check
            CHECK (material_type IN (
                'biography',
                'article',
                'profile',
                'resume',
                'personal_notes',
                'other'
            ))
        ");

        DB::statement("
            ALTER TABLE source_materials
            ADD CONSTRAINT sm_path_not_empty_check
            CHECK (length(trim(storage_path)) > 0)
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE source_materials DROP CONSTRAINT IF EXISTS sm_path_not_empty_check');
        DB::statement('ALTER TABLE source_materials DROP CONSTRAINT IF EXISTS sm_material_type_check');
        Schema::dropIfExists('source_materials');
    }
};
