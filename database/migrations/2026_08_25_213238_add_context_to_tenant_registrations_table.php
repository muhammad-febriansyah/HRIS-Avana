<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_registrations', function (Blueprint $table): void {
            $table->string('source')->default('organic')->after('partner_id');
            $table->string('industry')->nullable()->after('source');
            $table->string('employee_count_range')->nullable()->after('industry');
            $table->boolean('terms_accepted')->default(false)->after('employee_count_range');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_registrations', function (Blueprint $table): void {
            $table->dropColumn(['source', 'industry', 'employee_count_range', 'terms_accepted']);
        });
    }
};
