<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_addons', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_addons', function (Blueprint $table): void {
            $table->date('starts_at')->nullable()->after('status');
            $table->date('ends_at')->nullable()->after('starts_at');
        });
    }
};
