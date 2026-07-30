<?php

use App\Support\FaceMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting recordings, their transcript, and everything the AI derives from it.
 *
 * The phone streams audio straight to the speech provider and never sends it
 * here, so the transcript arrives as batches of finished segments while the
 * recording runs. That batch is the only copy the server ever gets — the
 * summary, the AI's answers, and the billing all hang off it, which is why
 * `meetings.billed_ms` lives beside `duration_ms`: audio already charged for
 * must not be charged again when a phone re-sends a batch it was unsure about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            /** Whoever pressed record — the account the tokens are billed to. */
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('location')->nullable();
            /** `mobile_live` for a phone recording, `upload` for an audio file. */
            $table->string('source', 16)->default('mobile_live');
            /** recording → processing → ready, or failed. */
            $table->string('status', 16)->default('recording');
            /** Who may read the transcript: `participants` or the whole `tenant`. */
            $table->string('visibility', 16)->default('participants');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            /** Audio heard so far, as the phone reports it. */
            $table->unsignedBigInteger('duration_ms')->default(0);
            /** Audio already debited, so a repeated batch bills nothing twice. */
            $table->unsignedBigInteger('billed_ms')->default(0);
            $table->string('language', 16)->nullable();
            $table->string('stt_model')->nullable();
            /** Optional keepsake copy of the recording, for replay. */
            $table->string('audio_path')->nullable();
            $table->unsignedBigInteger('audio_size')->nullable();
            $table->longText('summary')->nullable();
            $table->string('summary_model')->nullable();
            $table->unsignedBigInteger('summary_tokens')->default(0);
            /** Why processing gave up, shown to the person who recorded it. */
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'started_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('meeting_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            /** The speech provider's speaker number, before anyone names it. */
            $table->unsignedSmallInteger('speaker_index')->default(0);
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->text('text');
            $table->timestamps();

            // A phone that loses its connection re-sends the batch it was
            // unsure about; the start offset is what makes that harmless.
            $table->unique(['meeting_id', 'start_ms']);
        });

        Schema::create('meeting_speakers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('speaker_index');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name')->nullable();
            /** True until a human confirms the name the AI proposed. */
            $table->boolean('guessed_by_ai')->default(false);
            $table->decimal('confidence', 4, 3)->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'speaker_index']);
        });

        Schema::create('meeting_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_id', 'employee_id']);
        });

        Schema::create('meeting_action_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->foreignId('assignee_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('open');
            /** `ai` when the summary proposed it, `manual` when a person did. */
            $table->string('source', 8)->default('ai');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['meeting_id', 'sort_order']);
        });

        Schema::create('meeting_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            /** One of the premium analyses, e.g. `executive_summary`. */
            $table->string('type', 32);
            $table->json('payload');
            $table->string('model')->nullable();
            $table->unsignedBigInteger('tokens')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            // One row per analysis: these cost real money, so the stored answer
            // is reused until somebody asks for a fresh one.
            $table->unique(['meeting_id', 'type']);
        });

        Schema::create('meeting_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->text('text');
            $table->unsignedBigInteger('start_ms')->default(0);
            $table->unsignedBigInteger('end_ms')->default(0);
            /**
             * The chunk's embedding as a JSON array. MySQL 9 has a VECTOR type
             * but not the distance function in this edition, so ranking is
             * cosine in PHP over the tenant's own rows — the same thing
             * {@see FaceMatcher} already does for faces.
             */
            $table->json('embedding')->nullable();
            $table->string('embedding_model')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'ordinal']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_chunks');
        Schema::dropIfExists('meeting_insights');
        Schema::dropIfExists('meeting_action_items');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meeting_speakers');
        Schema::dropIfExists('meeting_segments');
        Schema::dropIfExists('meetings');
    }
};
