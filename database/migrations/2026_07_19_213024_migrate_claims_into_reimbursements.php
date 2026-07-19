<?php

use App\Models\Claim;
use App\Models\Reimbursement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Folds the older `claims` feature into `reimbursements`.
 *
 * The two covered the same ground with separate tables, and the mobile app
 * wrote to the older one while the surviving screen reads the newer — so once
 * the Klaim menu was retired, anything filed from a phone became invisible,
 * including claims still waiting to be approved.
 *
 * Rows are copied, not moved: `claims` is left untouched so nothing is lost if
 * this has to be revisited. `migrated_claim_id` makes the copy idempotent and
 * traceable back to its origin.
 */
return new class extends Migration
{
    /**
     * The old five claim types onto the five reimbursement categories.
     *
     * `transport` and `medical` map straight across. The rest are a judgement:
     * glasses is a health benefit, and both meals and the catch-all "other"
     * are operational spending.
     *
     * @var array<string, string>
     */
    private const CATEGORY_MAP = [
        'transport' => 'transportasi',
        'medical' => 'medical',
        'glasses' => 'medical',
        'meal' => 'operasional',
        'other' => 'operasional',
    ];

    public function up(): void
    {
        $alreadyCopied = DB::table('reimbursements')
            ->whereNotNull('migrated_claim_id')
            ->pluck('migrated_claim_id')
            ->all();

        // Per-tenant running sequence, continuing from whatever is already there.
        $sequences = [];

        Claim::query()
            ->whereNotIn('id', $alreadyCopied)
            ->orderBy('id')
            ->chunkById(200, function ($claims) use (&$sequences): void {
                foreach ($claims as $claim) {
                    $tenantId = (int) $claim->tenant_id;

                    $sequences[$tenantId] ??= $this->startingSequence($tenantId);
                    $sequences[$tenantId]++;

                    Reimbursement::create([
                        'tenant_id' => $tenantId,
                        'employee_id' => $claim->employee_id,
                        'current_approver_id' => $claim->current_approver_id,
                        'number' => $this->numberFor($claim, $sequences[$tenantId]),
                        'category' => self::CATEGORY_MAP[$claim->claim_type] ?? 'operasional',
                        'title' => $claim->title ?: 'Reimbursement',
                        'amount' => $claim->amount,
                        'expense_date' => $claim->claim_date,
                        'description' => $claim->description,
                        'receipt_path' => $claim->receipt_path,
                        'status' => $claim->status,
                        'approver_id' => $claim->approver_id,
                        'approved_at' => $claim->approved_at,
                        'paid_at' => $claim->paid_at,
                        'paid_by' => $claim->paid_by,
                        'notes' => $claim->notes,
                        'migrated_claim_id' => $claim->id,
                        'created_at' => $claim->created_at,
                        'updated_at' => $claim->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('reimbursements')->whereNotNull('migrated_claim_id')->delete();
    }

    /** Highest sequence already used for a tenant this month. */
    private function startingSequence(int $tenantId): int
    {
        $prefix = 'RMB-'.now()->format('Ym').'-';

        $last = DB::table('reimbursements')
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        return $last === null ? 0 : (int) substr($last, -4);
    }

    /** RMB-YYYYMM-0001, the same shape the module allocates. */
    private function numberFor(Claim $claim, int $sequence): string
    {
        return 'RMB-'.now()->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
};
