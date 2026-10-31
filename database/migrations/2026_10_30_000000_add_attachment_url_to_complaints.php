<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table): void {
            $table->string('attachment_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table): void {
            $table->dropColumn('attachment_url');
        });
    }
};
