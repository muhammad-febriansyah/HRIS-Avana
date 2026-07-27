<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOP documents (PDF) owned by a tenant. `visibility` decides who the AI
 * assistant may quote a document to: `public` = every employee in the tenant,
 * `private` = only users holding the `sop.view` permission (HR/admin).
 *
 * `content` holds the text extracted from the uploaded PDF (or typed by the
 * admin) and is what the assistant actually searches and answers from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('visibility')->default('private');
            $table->string('status')->default('active');
            $table->string('version')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sops');
    }
};
