<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tenant_registrations')
            ->whereNotNull('partner_id')
            ->where('source', 'organic')
            ->update(['source' => 'referral']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing rows cannot be safely classified back to their original source.
    }
};
