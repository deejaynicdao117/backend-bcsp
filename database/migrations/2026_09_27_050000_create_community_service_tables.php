<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_code')->unique();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('address');
            $table->string('purok')->index();
            $table->unsignedSmallInteger('member_count')->default(1);
            $table->string('verification_status')->default('pending')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('household_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('relationship')->default('member');
            $table->timestamps();
        });

        Schema::create('resident_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('sex', 30)->nullable();
            $table->string('civil_status', 40)->nullable();
            $table->string('occupation')->nullable();
            $table->string('address')->nullable();
            $table->string('purok')->nullable()->index();
            $table->string('verification_status')->default('unverified')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('category')->default('other')->index();
            $table->string('priority')->default('medium')->index();
            $table->string('location')->nullable();
            $table->string('purok')->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->string('ai_recommended_category')->nullable();
            $table->string('ai_recommended_priority')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('ai_engine')->default('local_keyword_rules_v1');
            $table->json('validation_flags')->nullable();
            $table->boolean('possible_duplicate')->default(false);
            $table->string('final_category')->nullable();
            $table->string('final_priority')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('staff_remarks')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('type')->default('announcement')->index();
            $table->string('severity')->default('info');
            $table->string('status')->default('draft')->index();
            $table->string('location')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('office')->nullable();
            $table->string('phone', 40);
            $table->string('alternate_phone', 40)->nullable();
            $table->string('availability')->default('24/7');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('evacuation_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('purok')->nullable()->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('current_occupancy')->default(0);
            $table->string('contact_phone', 40)->nullable();
            $table->string('status')->default('open')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('disaster_assistance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference_number')->unique();
            $table->string('incident_type')->index();
            $table->text('description');
            $table->string('location');
            $table->string('purok')->nullable()->index();
            $table->string('priority')->default('high')->index();
            $table->string('status')->default('submitted')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('evacuation_center_id')->nullable()->constrained()->nullOnDelete();
            $table->text('staff_remarks')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('disaster_assistance_requests');
        Schema::dropIfExists('evacuation_centers');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('resident_profiles');
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
    }
};
