<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every browser or app a user has ever signed in from.
 *
 * Two jobs: it is the "Perangkat Dikenal" list a user can review and revoke,
 * and it is what makes "sign-in from a new device" answerable — without a
 * record of the old ones there is nothing to compare against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // sha256 of the user agent plus the client platform: stable across
            // sessions on the same browser, and carries no personal data itself.
            $table->char('fingerprint', 64);

            $table->string('label')->nullable();
            $table->string('platform', 64)->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('channel', 16)->default('web');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
            $table->index(['tenant_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_devices');
    }
};
