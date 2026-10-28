<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_types', 'body_template')) {
            Schema::table('document_types', function (Blueprint $table): void {
                $table->longText('body_template')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_types', 'body_template')) {
            Schema::table('document_types', function (Blueprint $table): void {
                $table->dropColumn('body_template');
            });
        }
    }
};
