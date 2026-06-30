<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->string('position')->nullable()->after('job_posting_id');
            $table->string('photo_path')->nullable()->after('position');
            $table->string('linkedin_url')->nullable()->after('source');
            $table->string('portfolio_url')->nullable()->after('linkedin_url');
            $table->string('cv_path')->nullable()->after('portfolio_url');
            $table->dateTime('interview_at')->nullable()->after('applied_date');
            $table->dateTime('offered_at')->nullable()->after('interview_at');
            $table->text('offer_note')->nullable()->after('offered_at');
        });

        Schema::create('applicant_medical_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('pending'); // pending|passed|failed
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->date('checked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'applicant_id']);
        });

        Schema::create('applicant_background_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('check_type'); // employment|education|criminal|reference
            $table->string('status')->default('requested'); // requested|clear|flagged
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->date('requested_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_background_checks');
        Schema::dropIfExists('applicant_medical_checks');

        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropColumn([
                'position', 'photo_path', 'linkedin_url', 'portfolio_url',
                'cv_path', 'interview_at', 'offered_at', 'offer_note',
            ]);
        });
    }
};
