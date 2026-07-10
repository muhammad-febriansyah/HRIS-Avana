<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forwarding a mapped Sales Order to Recruitment for benefit approval
     * (BPR manual 1.4): status advances new → mapped → forwarded → approved, and
     * a rejection sends it back to mapped with a note.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('forwarded_by')->nullable()->after('mapped_at')->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable()->after('forwarded_by');
            $table->foreignId('benefit_decided_by')->nullable()->after('forwarded_at')->constrained('users')->nullOnDelete();
            $table->timestamp('benefit_decided_at')->nullable()->after('benefit_decided_by');
            $table->string('benefit_note')->nullable()->after('benefit_decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('forwarded_by');
            $table->dropConstrainedForeignId('benefit_decided_by');
            $table->dropColumn(['forwarded_at', 'benefit_decided_at', 'benefit_note']);
        });
    }
};
