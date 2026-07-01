<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->string('model')->nullable()->after('content');
            $table->unsignedInteger('prompt_tokens')->nullable()->after('model');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('completion_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropColumn(['model', 'prompt_tokens', 'completion_tokens', 'total_tokens']);
        });
    }
};
