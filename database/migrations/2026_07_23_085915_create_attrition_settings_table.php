<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant tuning for the attrition scorer: factor weights and the
     * low/medium risk-band cut-offs. One row per tenant; absent rows fall back
     * to config/attrition.php defaults.
     */
    public function up(): void
    {
        Schema::create('attrition_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->json('weights');
            $table->unsignedTinyInteger('band_low')->default(29);
            $table->unsignedTinyInteger('band_medium')->default(59);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attrition_settings');
    }
};
