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
            $table->foreignId('tenant_group_id')
                ->nullable()
                ->after('id')
                ->constrained('tenant_groups')
                ->nullOnDelete();
            $table->boolean('is_primary')->default(true)->after('tenant_group_id');
            $table->index(['tenant_group_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['tenant_group_id']);
            $table->dropIndex(['tenant_group_id', 'status']);
            $table->dropColumn(['tenant_group_id', 'is_primary']);
        });
    }
};
