<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Embedded in every JWT as the `tv` claim. Bumped on password change
            // and "sign out of all devices"; a token whose `tv` no longer matches
            // is rejected, which is how the stateless JWT layer revokes tokens.
            $table->unsignedInteger('token_version')->default(0)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('token_version');
        });
    }
};
