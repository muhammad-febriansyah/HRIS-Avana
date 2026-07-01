<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offboarding_cases', function (Blueprint $table): void {
            $table->string('settlement_reason')->nullable()->after('reason');
            $table->decimal('settlement_amount', 15, 2)->nullable()->after('settlement_reason');
            $table->json('settlement_breakdown')->nullable()->after('settlement_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offboarding_cases', function (Blueprint $table): void {
            $table->dropColumn(['settlement_reason', 'settlement_amount', 'settlement_breakdown']);
        });
    }
};
