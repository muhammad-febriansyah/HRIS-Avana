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
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('key'); // stable slug id (e.g. crm, kinerja)
            $table->string('section')->nullable(); // group header for top-level items
            $table->string('label');
            $table->string('icon')->nullable();
            $table->string('href')->nullable(); // null = container (has children)
            $table->string('feature')->nullable(); // tenant feature gate
            $table->json('modules')->nullable(); // permission modules gate
            $table->boolean('admin_only')->default(false);
            $table->boolean('super_admin_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // seeded core item
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
