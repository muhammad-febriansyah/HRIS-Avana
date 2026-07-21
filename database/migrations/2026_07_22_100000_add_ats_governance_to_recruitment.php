<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->foreignId('interviewer_id')->nullable()->after('interview_location')
                ->constrained('users')->nullOnDelete();
            $table->text('offer_benefit')->nullable()->after('offer_note');
            $table->date('offer_valid_until')->nullable()->after('offer_start_date');
        });

        Schema::create('applicant_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->string('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'applicant_id']);
        });

        Schema::create('applicant_onboarding_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_onboarding_items');
        Schema::dropIfExists('applicant_status_logs');

        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('interviewer_id');
            $table->dropColumn(['offer_benefit', 'offer_valid_until']);
        });
    }
};
