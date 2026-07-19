<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the trip context the mobile "Settlement Perdin" detail screen shows above
 * the expense lines: where the employee went, over which dates, and the map pin
 * for that destination. Trip length is derived from the two dates rather than
 * stored, so it can never drift out of sync with them.
 *
 * Line items gain a `detail` line for the specifics shown under the category
 * name — the flight number, the hotel and how many nights, the taxi route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->string('destination')->nullable()->after('department');
            $table->date('trip_start_date')->nullable()->after('destination');
            $table->date('trip_end_date')->nullable()->after('trip_start_date');
            $table->decimal('destination_latitude', 10, 7)->nullable()->after('trip_end_date');
            $table->decimal('destination_longitude', 10, 7)->nullable()->after('destination_latitude');
        });

        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->string('detail')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->dropColumn('detail');
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropColumn([
                'destination',
                'trip_start_date',
                'trip_end_date',
                'destination_latitude',
                'destination_longitude',
            ]);
        });
    }
};
