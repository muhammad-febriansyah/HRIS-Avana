<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user permission overrides. A `grant` adds a permission a user's roles do
 * not carry; a `revoke` removes one a role does carry. Overrides win over role
 * permissions but are still bound by the tenant feature ceiling. See
 * {@see User::permissionCodes()} for how they are resolved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('permission_code');
            $table->string('type'); // grant | revoke
            $table->timestamps();

            $table->unique(['user_id', 'permission_code']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
