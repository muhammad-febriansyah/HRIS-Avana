<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table): void {
            $table->longText('embedding_ciphertext')->nullable()->after('embedding');
            $table->string('model_version', 64)->nullable()->after('dimensions');
        });
    }

    public function down(): void
    {
        Schema::table('face_embeddings', function (Blueprint $table): void {
            $table->dropColumn(['embedding_ciphertext', 'model_version']);
        });
    }
};
