<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('performance_reviews')
            ->select('tenant_id', 'cycle_id', 'employee_id', DB::raw('count(*) as total'))
            ->groupBy('tenant_id', 'cycle_id', 'employee_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Cannot add unique constraint: duplicate (tenant_id, cycle_id, employee_id) rows exist in performance_reviews. Resolve them before migrating.'
            );
        }

        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'cycle_id', 'employee_id'], 'performance_reviews_tenant_cycle_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropUnique('performance_reviews_tenant_cycle_employee_unique');
        });
    }
};
