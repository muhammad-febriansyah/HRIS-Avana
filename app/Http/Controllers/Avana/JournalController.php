<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    /**
     * Roles that may always manage accounting journals within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Display the accounting journal entries grouped by date.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $entries = JournalEntry::forTenant($tenantId)
            ->with('period:id,name')
            ->orderByDesc('entry_date')
            ->orderBy('account_code')
            ->orderByDesc('id')
            ->get()
            ->map(fn (JournalEntry $entry): array => $this->transformEntry($entry));

        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');

        $latestRun = PayrollRun::forTenant($tenantId)
            ->with('period:id,name')
            ->latest('id')
            ->first();

        return Inertia::render('avana/jurnal/index', [
            'entries' => $entries,
            'latestRun' => $latestRun === null ? null : [
                'id' => $latestRun->id,
                'period' => $latestRun->period?->name,
                'total_gross' => (float) $latestRun->total_gross,
                'total_deduction' => (float) $latestRun->total_deduction,
                'total_tax' => (float) $latestRun->total_tax,
                'total_net' => (float) $latestRun->total_net,
            ],
            'kpis' => [
                'total_entries' => $entries->count(),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balanced' => abs($totalDebit - $totalCredit) < 0.01,
            ],
        ]);
    }

    /**
     * Generate a balanced payroll journal from the tenant's latest payroll run.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $run = PayrollRun::forTenant($tenantId)
            ->latest('id')
            ->first();

        if ($run === null) {
            return back()->with('error', 'Belum ada payroll run untuk dijurnal');
        }

        $gross = (float) $run->total_gross;
        $deduction = (float) $run->total_deduction;
        $tax = (float) $run->total_tax;
        $net = (float) $run->total_net;
        $entryDate = now()->toDateString();
        $description = 'Jurnal payroll run #'.$run->id;

        // Balanced double-entry: Dr Beban Gaji (gross) = Cr Hutang BPJS & Pajak (deduction + tax) + Cr Kas/Bank (net).
        $lines = [
            ['account_code' => '5101', 'account_name' => 'Beban Gaji', 'debit' => $gross, 'credit' => 0],
            ['account_code' => '2101', 'account_name' => 'Hutang BPJS & Pajak', 'debit' => 0, 'credit' => $deduction + $tax],
            ['account_code' => '1101', 'account_name' => 'Kas/Bank', 'debit' => 0, 'credit' => $net],
        ];

        foreach ($lines as $line) {
            JournalEntry::create([
                'tenant_id' => $tenantId,
                'payroll_period_id' => $run->payroll_period_id,
                'entry_date' => $entryDate,
                'account_code' => $line['account_code'],
                'account_name' => $line['account_name'],
                'description' => $description,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);
        }

        return redirect()->route('avana.jurnal')
            ->with('success', 'Jurnal payroll berhasil dibuat');
    }

    /**
     * Delete a single journal entry.
     */
    public function destroy(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $entry);

        $entry->delete();

        return back()->with('success', 'Entri jurnal dihapus');
    }

    /**
     * Build the row shape consumed by the journal table.
     *
     * @return array<string, mixed>
     */
    private function transformEntry(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_date' => $entry->entry_date?->toDateString(),
            'account_code' => $entry->account_code,
            'account_name' => $entry->account_name,
            'description' => $entry->description,
            'debit' => (float) $entry->debit,
            'credit' => (float) $entry->credit,
            'period' => $entry->period?->name,
        ];
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, JournalEntry $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}
