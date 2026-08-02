<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Give employees a URL key that cannot be counted through.
 *
 * `/avana/employees/36` is not an open door — the controller checks tenancy
 * and the view policy before it answers — but the number still tells anyone
 * how many employees exist and invites walking the range, and a link pasted
 * into a chat carries a guessable neighbour. A ULID says neither.
 *
 * The primary key stays as it is: every foreign key in the schema points at
 * it, and none of that belongs in a URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('public_id', 26)->nullable()->after('id');
        });

        // Every existing row needs its own value before the unique index goes on.
        DB::table('employees')->orderBy('id')->select('id')->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('employees')->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
