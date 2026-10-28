<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->text('rejection_reason')->nullable();
            $table->date('ready_for_pickup_at')->nullable();
            $table->date('released_at')->nullable();
        });

        DB::table('document_requests')->where('status', 'ready_for_release')->update(['status' => 'ready_for_pickup']);
        DB::table('document_requests')->where('status', 'completed')->update(['status' => 'released']);
    }

    public function down(): void
    {
        DB::table('document_requests')->where('status', 'ready_for_pickup')->update(['status' => 'ready_for_release']);
        DB::table('document_requests')->where('status', 'released')->update(['status' => 'completed']);

        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'rejection_reason',
                'ready_for_pickup_at',
                'released_at',
            ]);
        });
    }
};