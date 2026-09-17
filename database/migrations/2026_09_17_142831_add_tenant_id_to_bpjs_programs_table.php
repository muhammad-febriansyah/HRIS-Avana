<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpjs_programs', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('bpjs_programs', function (Blueprint $table): void {
            $table->dropUnique('bpjs_programs_code_unique');
            $table->unique(['tenant_id', 'code']);
        });

        $tenantIds = DB::table('tenants')->orderBy('id')->pluck('id')->all();

        if ($tenantIds !== []) {
            $defaultTenantId = (int) $tenantIds[0];
            $programs = DB::table('bpjs_programs')->whereNull('tenant_id')->get();

            foreach ($programs as $program) {
                $rates = DB::table('bpjs_rates')->where('program_id', $program->id)->get();

                DB::table('bpjs_programs')
                    ->where('id', $program->id)
                    ->update(['tenant_id' => $defaultTenantId]);

                foreach (array_slice($tenantIds, 1) as $tenantId) {
                    $programId = DB::table('bpjs_programs')->insertGetId([
                        'tenant_id' => $tenantId,
                        'code' => $program->code,
                        'name' => $program->name,
                        'type' => $program->type,
                        'description' => $program->description,
                        'is_active' => $program->is_active,
                        'created_at' => $program->created_at,
                        'updated_at' => $program->updated_at,
                        'deleted_at' => $program->deleted_at,
                    ]);

                    foreach ($rates as $rate) {
                        DB::table('bpjs_rates')->insert([
                            'program_id' => $programId,
                            'employee_rate' => $rate->employee_rate,
                            'company_rate' => $rate->company_rate,
                            'max_wage' => $rate->max_wage,
                            'min_wage' => $rate->min_wage,
                            'risk_level' => $rate->risk_level,
                            'effective_start_date' => $rate->effective_start_date,
                            'effective_end_date' => $rate->effective_end_date,
                            'is_active' => $rate->is_active,
                            'created_at' => $rate->created_at,
                            'updated_at' => $rate->updated_at,
                        ]);
                    }
                }
            }
        }

        Schema::table('bpjs_programs', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bpjs_programs', function (Blueprint $table): void {
            $table->dropUnique('bpjs_programs_tenant_id_code_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique('code');
        });
    }
};
