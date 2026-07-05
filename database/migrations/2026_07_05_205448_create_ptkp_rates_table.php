<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptkp_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ptkp_status'); // TK/0, K/0, K/1 ...
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2); // annual PTKP allowance
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'ptkp_status', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptkp_rates');
    }
};
