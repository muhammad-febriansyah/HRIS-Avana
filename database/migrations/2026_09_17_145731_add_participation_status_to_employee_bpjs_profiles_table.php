<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_bpjs_profiles', function (Blueprint $table) {
            $table->string('participation_status', 32)
                ->default('participant')
                ->after('employee_id');
        });

        $programCodes = [
            'kesehatan_enabled' => 'KESEHATAN',
            'jht_enabled' => 'JHT',
            'jp_enabled' => 'JP',
            'jkk_enabled' => 'JKK',
            'jkm_enabled' => 'JKM',
        ];

        DB::table('employee_bpjs_profiles')->orderBy('id')->each(function (object $profile) use ($programCodes): void {
            $programIds = DB::table('bpjs_programs')
                ->where('tenant_id', $profile->tenant_id)
                ->whereIn('code', array_values($programCodes))
                ->pluck('id', 'code');

            $enabled = [];

            foreach ($programCodes as $flag => $code) {
                if ((bool) $profile->{$flag} && isset($programIds[$code])) {
                    $enabled[] = $code;

                    DB::table('employee_bpjs_programs')->insertOrIgnore([
                        'tenant_id' => $profile->tenant_id,
                        'employee_id' => $profile->employee_id,
                        'program_id' => $programIds[$code],
                        'is_active' => true,
                        'effective_start_date' => $profile->effective_start_date,
                        'effective_end_date' => $profile->effective_end_date,
                        'created_at' => $profile->created_at,
                        'updated_at' => $profile->updated_at,
                    ]);
                }
            }

            DB::table('employee_bpjs_profiles')
                ->where('id', $profile->id)
                ->update(['participation_status' => $enabled === [] ? 'not_participant' : 'participant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_bpjs_profiles', function (Blueprint $table) {
            $table->dropColumn('participation_status');
        });
    }
};
