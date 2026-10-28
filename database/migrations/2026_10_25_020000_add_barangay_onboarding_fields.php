<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('barangays', 'onboarding_status')) {
            Schema::table('barangays', function (Blueprint $table): void {
                $table->string('onboarding_status', 20)->default('unrequested')->index();
            });
        }

        if (! Schema::hasColumn('barangays', 'onboarding_reviewed_at')) {
            Schema::table('barangays', function (Blueprint $table): void {
                $table->timestamp('onboarding_reviewed_at')->nullable();
            });
        }

        if (! Schema::hasColumn('barangays', 'onboarding_reviewed_by')) {
            Schema::table('barangays', function (Blueprint $table): void {
                $table->foreignId('onboarding_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('barangays'))->pluck('name')->all();
        if (in_array('barangays_onboarding_reviewed_by_foreign', $foreignKeys, true)) {
            Schema::table('barangays', function (Blueprint $table): void {
                $table->dropForeign('barangays_onboarding_reviewed_by_foreign');
            });
        }

        $columnsToDrop = array_values(array_filter(
            ['onboarding_status', 'onboarding_reviewed_at', 'onboarding_reviewed_by'],
            fn (string $column): bool => Schema::hasColumn('barangays', $column),
        ));
        if ($columnsToDrop !== []) {
            Schema::table('barangays', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
