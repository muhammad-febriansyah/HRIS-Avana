<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A branch's time zone becomes an override rather than a copy.
 *
 * The column existed, defaulted to Asia/Jakarta and was never read by anything.
 * Now that it is read, every branch carrying that default would silently pin
 * itself to WIB and overrule the company's own zone — a Makassar company would
 * set WITA and see no change at all.
 *
 * Null now means "follow the company", which is what an untouched branch has
 * always meant. Rows still holding the default are cleared: they were never a
 * decision anyone made, and leaving them would defeat the setting above them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('timezone')->nullable()->default(null)->change();
        });

        DB::table('branches')->where('timezone', 'Asia/Jakarta')->update(['timezone' => null]);
        DB::table('branches')->where('timezone', '')->update(['timezone' => null]);
    }

    public function down(): void
    {
        DB::table('branches')->whereNull('timezone')->update(['timezone' => 'Asia/Jakarta']);

        Schema::table('branches', function (Blueprint $table): void {
            $table->string('timezone')->default('Asia/Jakarta')->nullable(false)->change();
        });
    }
};
