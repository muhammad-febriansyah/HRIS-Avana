<?php

use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        AvanaNav::seedPlatformDefaults();
    }

    public function down(): void
    {
        DB::table('menu_items')->whereNull('tenant_id')->where('key', 'berita')->delete();
    }
};
