<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approval keterangan + a rejection trail (BPR manual 1.3.1: "pilih status
     * disetujui dan isi keterangan bila diperlukan").
     */
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('approval_note')->nullable()->after('approved_at');
            $table->foreignId('rejected_by')->nullable()->after('approval_note')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->string('rejection_note')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['approval_note', 'rejected_at', 'rejection_note']);
        });
    }
};
