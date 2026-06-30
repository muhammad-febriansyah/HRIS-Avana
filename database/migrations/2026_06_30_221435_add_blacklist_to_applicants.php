<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->boolean('blacklisted')->default(false)->after('stage');
            $table->text('blacklist_reason')->nullable()->after('blacklisted');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropColumn(['blacklisted', 'blacklist_reason']);
        });
    }
};
