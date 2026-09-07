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
        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->timestamp('release_notified_at')->nullable()->after('released_at');
            $table->index(['release_notified_at', 'released_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->dropIndex(['release_notified_at', 'released_at']);
            $table->dropColumn('release_notified_at');
        });
    }
};
