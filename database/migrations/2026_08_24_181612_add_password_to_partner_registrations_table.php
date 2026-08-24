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
        Schema::table('partner_registrations', function (Blueprint $table) {
            // The applicant's own chosen password — carried through to the
            // real login `approve()` creates, so there's no separate
            // generated password for the super admin to relay.
            $table->string('password')->nullable()->after('whatsapp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_registrations', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
