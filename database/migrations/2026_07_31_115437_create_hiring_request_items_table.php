<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A hiring request carries one row per manpower need, not one need per request.
 *
 * The spec is explicit — "Hiring Request dapat berisi satu atau lebih kebutuhan
 * tenaga kerja", and a requisition is raised per need — so a manager asking for
 * two engineers and a designer files one request rather than three.
 *
 * The need also names a Position from the master data now instead of free text,
 * which is what the spec's pre-condition assumes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hiring_request_id')->constrained()->cascadeOnDelete();
            // The master Position, when the title matches one. Free text stays
            // in position_title so a need can still be raised for a role the
            // master does not carry yet.
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position_title');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('vacancy')->default(1);
            $table->text('job_description')->nullable();
            $table->text('qualification')->nullable();
            $table->string('employment_type')->default('tetap');
            $table->date('target_join_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'hiring_request_id']);
        });

        // Every request already filed becomes a one-line request, so none of
        // them loses its position, department, or target date.
        foreach (DB::table('hiring_requests')->orderBy('id')->cursor() as $row) {
            DB::table('hiring_request_items')->insert([
                'tenant_id' => $row->tenant_id,
                'hiring_request_id' => $row->id,
                'position_id' => null,
                'position_title' => $row->position_title,
                'department_id' => $row->department_id,
                'vacancy' => $row->vacancy,
                'job_description' => $row->job_description,
                'qualification' => $row->qualification,
                'employment_type' => $row->employment_type,
                'target_join_date' => $row->target_join_date,
                'sort_order' => 0,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::table('hiring_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'position_title',
                'vacancy',
                'job_description',
                'qualification',
                'employment_type',
                'target_join_date',
            ]);
        });

        // Which need a requisition answers. Without it a request carrying three
        // needs cannot say which one has been picked up and which are still
        // waiting.
        Schema::table('recruitment_requisitions', function (Blueprint $table): void {
            $table->foreignId('hiring_request_item_id')
                ->nullable()
                ->after('hiring_request_id')
                ->constrained('hiring_request_items')
                ->nullOnDelete();
        });

        // Existing requisitions point at the single item their request now has.
        // Row by row rather than an UPDATE…JOIN: the test suite runs on SQLite,
        // which has no such statement.
        foreach (DB::table('recruitment_requisitions')->whereNotNull('hiring_request_id')->cursor() as $requisition) {
            $itemId = DB::table('hiring_request_items')
                ->where('hiring_request_id', $requisition->hiring_request_id)
                ->orderBy('id')
                ->value('id');

            if ($itemId !== null) {
                DB::table('recruitment_requisitions')
                    ->where('id', $requisition->id)
                    ->update(['hiring_request_item_id' => $itemId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('recruitment_requisitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hiring_request_item_id');
        });

        Schema::table('hiring_requests', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('requester_id')->constrained()->nullOnDelete();
            $table->string('position_title')->default('');
            $table->unsignedInteger('vacancy')->default(1);
            $table->text('job_description')->nullable();
            $table->text('qualification')->nullable();
            $table->string('employment_type')->default('tetap');
            $table->date('target_join_date')->nullable();
        });

        // Fold the first need back onto the request it belongs to.
        foreach (DB::table('hiring_requests')->orderBy('id')->cursor() as $request) {
            $item = DB::table('hiring_request_items')
                ->where('hiring_request_id', $request->id)
                ->orderBy('id')
                ->first();

            if ($item === null) {
                continue;
            }

            DB::table('hiring_requests')->where('id', $request->id)->update([
                'position_title' => $item->position_title,
                'department_id' => $item->department_id,
                'vacancy' => $item->vacancy,
                'job_description' => $item->job_description,
                'qualification' => $item->qualification,
                'employment_type' => $item->employment_type,
                'target_join_date' => $item->target_join_date,
            ]);
        }

        Schema::dropIfExists('hiring_request_items');
    }
};
