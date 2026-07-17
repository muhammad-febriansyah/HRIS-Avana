<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visit is reported against a branch, so field activity can be read per
 * cabang. Points at the tenant's branch master rather than storing a free-typed
 * name, which would fragment the same cabang across spellings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_visits', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('field_visits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
