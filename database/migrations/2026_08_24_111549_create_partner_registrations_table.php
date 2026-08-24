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
        Schema::create('partner_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('whatsapp');
            $table->string('partner_type');
            $table->string('company_name')->nullable();
            $table->string('network_size')->nullable();
            $table->string('network_focus')->nullable();
            $table->text('network_description')->nullable();
            $table->string('social_link')->nullable();
            $table->string('how_did_you_know')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_registrations');
    }
};
