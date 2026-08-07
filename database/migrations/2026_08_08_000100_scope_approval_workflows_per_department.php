<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One approval chain per module was too coarse: divisions route the same
 * request type differently ("Cuti" for Warehouse crosses other desks than
 * "Cuti" for Engineering). A workflow now optionally names the department it
 * applies to; NULL keeps meaning "every division" and acts as the fallback the
 * engine picks when no scoped flow matches the requester.
 *
 * The one-active-flow guarantee widens with it: unique per (tenant, module,
 * department) instead of per (tenant, module). Dialect split as before —
 * MySQL gets a generated column (NULL for soft-deleted rows) because it cannot
 * index a subset of rows; SQLite/Postgres express the same as a partial index.
 * The column is VIRTUAL, not STORED: on MySQL 9 a STORED column forces a table
 * rebuild that re-validates the foreign keys and fails with errno 1215, while
 * a VIRTUAL one is added in place and InnoDB indexes it just the same.
 *
 * Every step is guarded so a run that died halfway (or a database already
 * carrying part of the shape) can simply be re-run.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'approval_workflows_one_per_module';

    private const OLD_COLUMN = 'active_request_type';

    private const INDEX = 'approval_workflows_one_per_scope';

    private const COLUMN = 'active_scope';

    public function up(): void
    {
        if (! Schema::hasColumn('approval_workflows', 'department_id')) {
            Schema::table('approval_workflows', function (Blueprint $table): void {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('request_type')
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }

        if ($this->indexExists(self::OLD_INDEX)) {
            DB::statement('DROP INDEX '.self::OLD_INDEX.($this->isMysql() ? ' ON approval_workflows' : ''));
        }

        if ($this->isMysql()) {
            if (Schema::hasColumn('approval_workflows', self::OLD_COLUMN)) {
                DB::statement('ALTER TABLE approval_workflows DROP COLUMN '.self::OLD_COLUMN);
            }

            if (! Schema::hasColumn('approval_workflows', self::COLUMN)) {
                DB::statement(sprintf(
                    'ALTER TABLE approval_workflows ADD COLUMN %s VARCHAR(191) '
                    ."GENERATED ALWAYS AS (IF(deleted_at IS NULL, CONCAT(request_type, '#', COALESCE(department_id, 0)), NULL)) VIRTUAL",
                    self::COLUMN,
                ));
            }

            if (! $this->indexExists(self::INDEX)) {
                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX %s ON approval_workflows (tenant_id, %s)',
                    self::INDEX,
                    self::COLUMN,
                ));
            }

            return;
        }

        if (! $this->indexExists(self::INDEX)) {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON approval_workflows (tenant_id, request_type, COALESCE(department_id, 0)) '
                .'WHERE deleted_at IS NULL',
                self::INDEX,
            ));
        }
    }

    public function down(): void
    {
        if ($this->indexExists(self::INDEX)) {
            DB::statement('DROP INDEX '.self::INDEX.($this->isMysql() ? ' ON approval_workflows' : ''));
        }

        if ($this->isMysql()) {
            if (Schema::hasColumn('approval_workflows', self::COLUMN)) {
                DB::statement('ALTER TABLE approval_workflows DROP COLUMN '.self::COLUMN);
            }

            DB::statement(sprintf(
                'ALTER TABLE approval_workflows ADD COLUMN %s VARCHAR(191) '
                .'GENERATED ALWAYS AS (IF(deleted_at IS NULL, request_type, NULL)) VIRTUAL',
                self::OLD_COLUMN,
            ));
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON approval_workflows (tenant_id, %s)',
                self::OLD_INDEX,
                self::OLD_COLUMN,
            ));
        } else {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON approval_workflows (tenant_id, request_type) WHERE deleted_at IS NULL',
                self::OLD_INDEX,
            ));
        }

        Schema::table('approval_workflows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });
    }

    private function indexExists(string $name): bool
    {
        if ($this->isMysql()) {
            return DB::table('information_schema.statistics')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', 'approval_workflows')
                ->where('index_name', $name)
                ->exists();
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return DB::table('sqlite_master')
                ->where('type', 'index')
                ->where('name', $name)
                ->exists();
        }

        return DB::table('pg_indexes')->where('indexname', $name)->exists();
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
