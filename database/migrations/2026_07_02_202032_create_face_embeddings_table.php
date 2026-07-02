<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One enrolled face embedding per employee (a normalized feature vector
     * produced on-device by MobileFaceNet), used to verify attendance selfies.
     */
    public function up(): void
    {
        Schema::create('face_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->json('embedding');
            $table->unsignedSmallInteger('dimensions');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->decimal('face_confidence', 5, 4)->nullable()->after('location_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn('face_confidence');
        });
        Schema::dropIfExists('face_embeddings');
    }
};
