<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Menu Cepat tiles of the Flutter app, per tenant.
 *
 * They used to be a hard-coded Dart list, so hiding one from a role — or from a
 * whole company — meant shipping a new build. Kept apart from `menu_items` on
 * purpose: those rows are the web sidebar, carrying href, section and parent,
 * and the Hak Akses matrix reads them directly. A phone tile has none of that
 * and would only have to be filtered out everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            /** Iconsax name as written in the Flutter app, e.g. `sun_1`. */
            $table->string('icon');
            /** Tile colour as `#RRGGBB`, so a tenant can match its branding. */
            $table->string('color', 7);
            /** GetX route the tile opens, e.g. `/leave`. */
            $table->string('route');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            /** Shipped with the app: the label may be edited, the row not deleted. */
            $table->boolean('is_system')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_menu_items');
    }
};
