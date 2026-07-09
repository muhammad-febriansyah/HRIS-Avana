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
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            // null tenant_id = platform-wide default (super admin); a tenant id
            // = that tenant's own SMTP override (tenant admin).
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('encryption')->nullable(); // ssl | tls | null
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted at rest via model cast
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_settings');
    }
};
