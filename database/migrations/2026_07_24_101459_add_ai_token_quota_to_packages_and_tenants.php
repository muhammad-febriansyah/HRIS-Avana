<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly AI token allowance per package, keyed by package code. Packages
     * outside this list fall back to the default backfilled below.
     *
     * @var array<string, int>
     */
    private const PACKAGE_QUOTA = [
        'starter' => 500_000,
        'business' => 2_000_000,
        'pro' => 5_000_000,
        'enterprise' => 20_000_000,
    ];

    private const DEFAULT_QUOTA = 1_000_000;

    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_token_quota')->nullable()->after('max_branches');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_token_quota')->nullable()->after('max_branches');
        });

        // Backfill existing rows so the token meter has a quota without a reseed.
        foreach (self::PACKAGE_QUOTA as $code => $quota) {
            DB::table('packages')->where('code', $code)->update(['ai_token_quota' => $quota]);
        }
        DB::table('packages')->whereNull('ai_token_quota')->update(['ai_token_quota' => self::DEFAULT_QUOTA]);

        // Each tenant inherits its package's monthly quota. Done per-package to
        // stay portable across drivers (SQLite lacks UPDATE ... JOIN support).
        foreach (DB::table('packages')->get(['id', 'ai_token_quota']) as $package) {
            DB::table('tenants')
                ->where('package_id', $package->id)
                ->update(['ai_token_quota' => $package->ai_token_quota]);
        }
        DB::table('tenants')->whereNull('ai_token_quota')->update(['ai_token_quota' => self::DEFAULT_QUOTA]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('ai_token_quota');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('ai_token_quota');
        });
    }
};
