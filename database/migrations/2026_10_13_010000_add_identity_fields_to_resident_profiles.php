<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resident_profiles', function (Blueprint $table): void {
            $table->string('last_name', 100)->nullable()->after('user_id');
            $table->string('first_name', 100)->nullable()->after('last_name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('resident_profiles', function (Blueprint $table): void {
            $table->dropColumn(['last_name', 'first_name', 'middle_name']);
        });
    }
};