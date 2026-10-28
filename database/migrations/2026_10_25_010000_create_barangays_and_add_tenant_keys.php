<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tenantTables = [
        'users', 'resident_profiles', 'households', 'complaints', 'announcements',
        'emergency_contacts', 'evacuation_centers', 'disaster_assistance_requests',
        'document_types', 'document_requests', 'portal_notifications', 'activity_logs',
        'system_settings',
    ];

    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table): void {
            $table->id();
            $table->string('psgc_code', 20)->unique();
            $table->string('name');
            $table->string('municipality');
            $table->string('province')->default('Bulacan');
            $table->string('logo_path')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('office_address')->nullable();
            $table->string('primary_color', 7)->default('#2563eb');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $defaultBarangayId = DB::table('barangays')->insertGetId([
            'psgc_code' => 'DEFAULT',
            'name' => 'Barangay Portal',
            'municipality' => 'Unassigned',
            'province' => 'Bulacan',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tenantTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('barangay_id')->nullable()->after('id')->constrained('barangays')->restrictOnDelete();
            });

            DB::table($tableName)->whereNull('barangay_id')->update(['barangay_id' => $defaultBarangayId]);
        }

        $firstAdministrator = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');
        if ($firstAdministrator) {
            DB::table('users')->where('id', $firstAdministrator)->update(['role' => 'platform_admin']);
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropUnique('system_settings_key_unique');
            $table->unique(['barangay_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropUnique(['barangay_id', 'key']);
            $table->unique('key');
        });

        foreach (array_reverse($this->tenantTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('barangay_id');
            });
        }

        Schema::dropIfExists('barangays');
    }
};