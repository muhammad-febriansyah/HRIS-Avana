<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an employee row whose personal data was erased on request.
 *
 * The row survives the erasure — payroll and attendance history point at it —
 * so something has to say that the placeholder name is not a real person and
 * record when the request was carried out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('resign_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
    }
};
