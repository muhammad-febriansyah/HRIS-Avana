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
        Schema::table('tenants', function (Blueprint $table) {
            // Set once, at creation, and never reassigned afterwards — the
            // partner who is owed commission for this client stays fixed for
            // the tenant's lifetime.
            $table->foreignId('partner_id')->nullable()->after('package_id')->constrained('partners')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
        });
    }
};
