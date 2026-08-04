<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duty travel had a status and an `approved_by`, but nowhere to record whose desk
 * a trip is sitting on — so the "Perjalanan Dinas" workflow a tenant configured
 * in Setup Alur Persetujuan could never route it anywhere. Same shape as every
 * other request type: an EMPLOYEE id, which is what the approval screens and the
 * mobile queue both compare against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duty_travels', function (Blueprint $table): void {
            $table->foreignId('current_approver_id')
                ->nullable()
                ->after('approved_by')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('duty_travels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_approver_id');
        });
    }
};
