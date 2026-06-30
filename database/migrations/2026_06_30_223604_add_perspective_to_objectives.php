<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('objectives', function (Blueprint $table): void {
            // Balanced Scorecard perspective: financial|customer|internal|learning
            $table->string('perspective')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('objectives', function (Blueprint $table): void {
            $table->dropColumn('perspective');
        });
    }
};
