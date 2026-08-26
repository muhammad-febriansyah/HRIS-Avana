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
        Schema::table('referral_ledger', function (Blueprint $table) {
            $table->unique(['reference_type', 'reference_id', 'type'], 'referral_ledger_reference_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_ledger', function (Blueprint $table) {
            $table->dropUnique('referral_ledger_reference_unique');
        });
    }
};
