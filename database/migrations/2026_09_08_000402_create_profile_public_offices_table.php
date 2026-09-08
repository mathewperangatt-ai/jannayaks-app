<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_public_offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained('profiles')
                ->onDelete('cascade');

            $table->string('office_name', 255);
            $table->string('where_location', 255);
            $table->string('term_summary', 255)->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->unsignedInteger('sort_order')->default(1);

            $table->timestamps();

            $table->index(['profile_id', 'sort_order']);
            $table->index('office_name');
            $table->index('where_location');
            $table->index(['is_current', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_public_offices');
    }
};
