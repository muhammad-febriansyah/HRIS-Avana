<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('title');
            $table->decimal('value', 15, 2)->default(0);
            $table->string('stage')->default('lead'); // lead|qualified|proposal|won|lost
            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('expected_close')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};
