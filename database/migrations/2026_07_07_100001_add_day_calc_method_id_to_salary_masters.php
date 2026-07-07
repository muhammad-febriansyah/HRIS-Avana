<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_masters', function (Blueprint $table) {
            $table->foreignId('day_calc_method_id')
                ->nullable()
                ->after('day_calc_method')
                ->constrained('day_calc_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salary_masters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('day_calc_method_id');
        });
    }
};
