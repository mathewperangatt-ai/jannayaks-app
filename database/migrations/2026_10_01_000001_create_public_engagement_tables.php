<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profile reactions (Like / Applaud). Private by design: no public
        // counters, no public reaction lists — only the profile owner may
        // see name + broad location + reaction kind.
        Schema::create('profile_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 16); // like | applaud
            $table->string('broad_location', 120)->nullable();
            $table->timestamps();
            $table->unique(['profile_id', 'user_id', 'reaction']);
            $table->index(['profile_id', 'reaction']);
        });

        // Visitor → profile owner contact requests. The visitor's details are
        // never displayed publicly; they are delivered to the owner privately.
        Schema::create('profile_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_name', 120);
            $table->string('visitor_mobile', 32);
            $table->string('message', 1000);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'created_at']);
        });

        // "Recommend Someone You May Know" submissions.
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('recommender_name', 120);
            $table->string('recommender_contact', 255);
            $table->string('recommended_name', 120);
            $table->string('recommended_location', 120)->nullable();
            $table->string('recommended_role', 255)->nullable();
            $table->string('reason', 2000);
            $table->text('supporting_info')->nullable();
            $table->boolean('acknowledged_terms')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        // "Request an Invitation" submissions (the person themselves).
        Schema::create('invitation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('contact', 255);
            $table->string('town', 120)->nullable();
            $table->string('role', 255)->nullable();
            $table->string('reason', 2000);
            $table->boolean('acknowledged_terms')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_requests');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('profile_contact_messages');
        Schema::dropIfExists('profile_reactions');
    }
};
