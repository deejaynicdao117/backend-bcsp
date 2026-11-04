<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table): void {
            $table->index(['province', 'onboarding_status', 'is_active'], 'barangays_province_onboarding_active_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['barangay_id', 'role', 'created_at'], 'users_barangay_role_created_at_index');
        });

        Schema::table('document_requests', function (Blueprint $table): void {
            $table->index(['barangay_id', 'status', 'created_at'], 'document_requests_barangay_status_created_at_index');
            $table->index(['barangay_id', 'created_at'], 'document_requests_barangay_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropIndex('document_requests_barangay_created_at_index');
            $table->dropIndex('document_requests_barangay_status_created_at_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_barangay_role_created_at_index');
        });

        Schema::table('barangays', function (Blueprint $table): void {
            $table->dropIndex('barangays_province_onboarding_active_index');
        });
    }
};
