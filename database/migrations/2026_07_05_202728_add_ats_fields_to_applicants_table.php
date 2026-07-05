<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->foreignId('talent_pool_id')->nullable()->after('job_posting_id')->constrained('talent_pools')->nullOnDelete();
            $table->string('interview_type')->nullable()->after('interview_at'); // hr|technical|user|final
            $table->string('interview_status')->nullable()->after('interview_type'); // scheduled|completed|cancelled
            $table->string('interview_location')->nullable()->after('interview_status');
            $table->decimal('offer_salary', 15, 2)->nullable()->after('offer_note');
            $table->date('offer_start_date')->nullable()->after('offer_salary');
            $table->string('offer_status')->nullable()->after('offer_start_date'); // draft|sent|approved|accepted|rejected
            $table->unsignedTinyInteger('ai_confidence')->nullable()->after('offer_status'); // 0-100
            $table->string('ai_recommendation')->nullable()->after('ai_confidence'); // high|medium|low
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('talent_pool_id');
            $table->dropColumn([
                'interview_type', 'interview_status', 'interview_location',
                'offer_salary', 'offer_start_date', 'offer_status',
                'ai_confidence', 'ai_recommendation',
            ]);
        });
    }
};
