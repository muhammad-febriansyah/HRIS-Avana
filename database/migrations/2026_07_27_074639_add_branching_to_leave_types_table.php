<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a leave type branch into sub-types that draw from the parent's quota.
     *
     * `parent_id` marks a sub-type; `sub_limit` optionally caps how many of the
     * parent's days that sub-type may consume. The two toggle columns become
     * nullable so a sub-type can leave them unset and inherit the parent's
     * value — existing rows keep their concrete true/false.
     */
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('leave_types')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('sub_limit')->nullable()->after('default_quota');

            $table->boolean('allow_negative')->nullable()->default(false)->change();
            $table->boolean('requires_attachment')->nullable()->default(false)->change();

            $table->index(['tenant_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'parent_id']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'sub_limit']);

            $table->boolean('allow_negative')->default(false)->change();
            $table->boolean('requires_attachment')->default(false)->change();
        });
    }
};
