<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope_type');
            $table->string('scope_value')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'scope_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_data_scopes');
    }
};
