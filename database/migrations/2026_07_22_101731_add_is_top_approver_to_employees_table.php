<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A top approver (e.g. a director) sits at the top of the org chart: their
     * own requests have no manager above them, so submissions are approved on
     * the spot instead of routing to a non-existent approver.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->boolean('is_top_approver')->default(false)->after('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('is_top_approver');
        });
    }
};
