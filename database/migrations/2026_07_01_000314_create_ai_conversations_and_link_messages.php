<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Percakapan baru');
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        // Existing loose messages are cleared; every message now belongs to a conversation.
        DB::table('ai_messages')->delete();

        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->after('id')->constrained('ai_conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('conversation_id');
        });

        Schema::dropIfExists('ai_conversations');
    }
};
