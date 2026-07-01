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
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->decimal('calibrated_score', 5, 2)->nullable()->after('final_score');
            $table->foreignId('calibrated_by')->nullable()->after('calibrated_score')->constrained('users')->nullOnDelete();
            $table->timestamp('calibrated_at')->nullable()->after('calibrated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('calibrated_by');
            $table->dropColumn(['calibrated_score', 'calibrated_at']);
        });
    }
};
