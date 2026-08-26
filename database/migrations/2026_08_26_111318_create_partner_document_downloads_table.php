<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('partner_document_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_hash', 64)->index();
            $table->string('document', 100)->default('company-profile');
            $table->timestamps();
            $table->index(['document', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_document_downloads');
    }
};
