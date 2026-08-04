<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One approval workflow per module, per tenant.
 *
 * Nothing stopped a tenant from configuring "Cuti" twice, and the engine picked
 * whichever was newest — leaving the other one visible, marked active, and
 * never consulted. The index makes that state unrepresentable; variation within
 * a module belongs in the workflow's Kondisi, not in a rival workflow.
 *
 * The two dialects need different tools: MySQL cannot index a subset of rows,
 * so a stored column that is NULL for soft-deleted rows stands in — a UNIQUE
 * index treats NULLs as distinct, so retired workflows never collide. SQLite
 * and Postgres express the same thing as a partial index.
 */
return new class extends Migration
{
    private const INDEX = 'approval_workflows_one_per_module';

    private const GENERATED_COLUMN = 'active_request_type';

    public function up(): void
    {
        $this->retireOlderDuplicates();

        if ($this->isMysql()) {
            DB::statement(sprintf(
                'ALTER TABLE approval_workflows ADD COLUMN %s VARCHAR(191) '
                .'GENERATED ALWAYS AS (IF(deleted_at IS NULL, request_type, NULL)) STORED',
                self::GENERATED_COLUMN,
            ));
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON approval_workflows (tenant_id, %s)',
                self::INDEX,
                self::GENERATED_COLUMN,
            ));

            return;
        }

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON approval_workflows (tenant_id, request_type) '
            .'WHERE deleted_at IS NULL',
            self::INDEX,
        ));
    }

    public function down(): void
    {
        DB::statement('DROP INDEX '.self::INDEX.($this->isMysql() ? ' ON approval_workflows' : ''));

        if ($this->isMysql()) {
            DB::statement('ALTER TABLE approval_workflows DROP COLUMN '.self::GENERATED_COLUMN);
        }
    }

    /**
     * Soft-delete the duplicates an existing database may already carry, so the
     * index can be created. Only the newest survives — which is the one the
     * engine was already using, so no tenant's live behaviour changes.
     */
    private function retireOlderDuplicates(): void
    {
        $duplicates = DB::table('approval_workflows')
            ->select('tenant_id', 'request_type', DB::raw('MAX(id) as keep_id'))
            ->whereNull('deleted_at')
            ->groupBy('tenant_id', 'request_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('approval_workflows')
                ->where('tenant_id', $row->tenant_id)
                ->where('request_type', $row->request_type)
                ->whereNull('deleted_at')
                ->where('id', '!=', $row->keep_id)
                ->update(['is_active' => false, 'deleted_at' => now()]);
        }
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
