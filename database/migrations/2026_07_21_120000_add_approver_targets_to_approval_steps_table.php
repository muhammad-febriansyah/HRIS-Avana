<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->foreignId('approver_department_id')->nullable()->after('approver_role_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('approver_position_id')->nullable()->after('approver_department_id')->constrained('positions')->nullOnDelete();
            $table->json('condition')->nullable()->after('approver_position_id');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_department_id');
            $table->dropConstrainedForeignId('approver_position_id');
            $table->dropColumn('condition');
        });
    }
};
