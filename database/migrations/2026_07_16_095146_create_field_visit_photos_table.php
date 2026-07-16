<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A visit can carry several photos as evidence, so the single photo_path on
 * field_visits becomes a has-many — mirroring attendance_selfies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_visit_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_visit_id')->constrained('field_visits')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();

            $table->index(['tenant_id', 'field_visit_id']);
        });

        // Carry every existing single photo over as the visit's first photo, so
        // no evidence is lost when the column goes. CURRENT_TIMESTAMP rather
        // than NOW() — the test suite runs this on SQLite, which has no NOW().
        DB::statement('
            INSERT INTO field_visit_photos (tenant_id, field_visit_id, employee_id, file_path, created_at, updated_at)
            SELECT tenant_id, id, employee_id, photo_path, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM field_visits
            WHERE photo_path IS NOT NULL
        ');

        Schema::table('field_visits', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('field_visits', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('longitude');
        });

        // Collapse back onto the earliest photo per visit; any extras are lost,
        // which is inherent to a column that only holds one.
        DB::statement('
            UPDATE field_visits
            SET photo_path = (
                SELECT p.file_path FROM field_visit_photos p
                WHERE p.field_visit_id = field_visits.id
                ORDER BY p.id
                LIMIT 1
            )
        ');

        Schema::dropIfExists('field_visit_photos');
    }
};
