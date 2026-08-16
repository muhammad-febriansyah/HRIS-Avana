<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which mass assignment run a change set came from.
 *
 * Penetapan Gaji Massal writes one change set per employee, and an approver was
 * then asked to sign hundreds of them one at a time — so in practice they were
 * signed without being read. With the run identified, the whole batch can be
 * presented as what it is: this many employees, this much money, these
 * exceptions, approved or rejected as one decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_change_sets', function (Blueprint $table): void {
            $table->string('batch_id', 32)->nullable()->after('salary_master_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('salary_change_sets', function (Blueprint $table): void {
            $table->dropColumn('batch_id');
        });
    }
};
