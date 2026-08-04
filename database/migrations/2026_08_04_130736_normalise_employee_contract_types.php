<?php

use App\Support\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store one spelling of each contract kind.
 *
 * The employee form took the kind as free text and saved it as typed — "PKWT",
 * "PKWTT" — while the Kontrak screen's dropdown only knows the lower-case keys.
 * Its select therefore matched nothing and fell back to its first option, so
 * every existing contract opened there read as PKWT and saving one turned a
 * permanent contract into a fixed-term one.
 *
 * Both writers normalise now; this brings the rows already stored into line so
 * the dropdown finds them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = DB::table('employee_contracts')
            ->select('contract_type')
            ->whereNotNull('contract_type')
            ->distinct()
            ->pluck('contract_type');

        foreach ($types as $stored) {
            $normalised = ContractType::normalise($stored);

            if ($normalised === null || $normalised === $stored) {
                continue;
            }

            DB::table('employee_contracts')
                ->where('contract_type', $stored)
                ->update(['contract_type' => $normalised]);
        }
    }

    /**
     * Not reversible: the original casing is not recorded anywhere, and the
     * lower-case key means the same contract.
     */
    public function down(): void
    {
        //
    }
};
