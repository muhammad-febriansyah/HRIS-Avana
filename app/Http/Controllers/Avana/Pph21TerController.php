<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Pph21TerCategory;
use App\Models\Pph21TerRate;
use App\Models\User;
use App\Support\Pph21Ter;
use App\Support\Pph21TerImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * "Tarif TER PPh 21": the withholding tariff as master data.
 *
 * PP 58/2023 replaced the whole PPh 21 scheme, and its Lampiran is exactly the
 * sort of table a later PMK revises. This screen is how that revision is
 * entered — upload the official workbook, or edit a bracket by hand — instead
 * of shipping a release. Every version is dated, so publishing next year's
 * table never changes what last year's payslips charged.
 *
 * Reading is open to anyone who can see payroll; writing is super-admin only,
 * because the rows are global and a tenant admin editing them would move the
 * tariff for every tenant on the platform.
 */
class Pph21TerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureCanView($request);

        $asOf = $this->asOf($request->query('as_of'));

        Pph21Ter::forget();

        return Inertia::render('avana/payroll-ter/index', [
            'asOf' => $asOf,
            'canManage' => $this->isSuperAdmin($request),
            'categories' => $this->categoryTables($asOf),
            'categoryMap' => $this->categoryMapRows($asOf),
            'versions' => $this->versions(),
            'ptkpStatuses' => Pph21Ter::PTKP_STATUSES,
            'categoryOptions' => collect(Pph21Ter::CATEGORIES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values(),
        ]);
    }

    /**
     * Replace a tariff from the official workbook.
     *
     * The upload is parsed in full before anything is written: a file that only
     * half-reads must leave the tariff in force untouched rather than half
     * replaced.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'effective_start_date' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
        ], [
            'file.mimes' => 'Unggah berkas .xlsx (workbook resmi Setup TER PPh21).',
        ]);

        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        try {
            $parsed = Pph21TerImport::parse($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak bisa dibaca sebagai workbook TER.']);
        }

        DB::transaction(function () use ($parsed, $from, $data): void {
            foreach ($parsed['brackets'] as $category => $brackets) {
                $this->publishBrackets($category, $brackets, $from, $data['source'] ?? 'Impor workbook resmi');
            }

            if ($parsed['categories'] !== []) {
                $this->publishCategoryMap($parsed['categories'], $from);
            }
        });

        Pph21Ter::forget();

        $summary = collect($parsed['brackets'])
            ->map(fn (array $rows, string $category): string => $category.' '.count($rows).' baris')
            ->implode(', ');

        return back()->with('success', 'Tarif TER diperbarui dari berkas: '.$summary);
    }

    /**
     * Restore the PP 58/2023 tariff as enacted, effective from a date.
     */
    public function reset(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'effective_start_date' => ['required', 'date'],
        ]);

        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        DB::transaction(function () use ($from): void {
            foreach (Pph21Ter::statutoryMonthly() as $category => $brackets) {
                $this->publishBrackets($category, $this->fromStatutory($brackets), $from, 'PP 58/2023 & PMK 168/2023');
            }

            $this->publishBrackets('HARIAN', $this->fromStatutory(Pph21Ter::statutoryDaily()), $from, 'PP 58/2023 & PMK 168/2023');
            $this->publishCategoryMap(Pph21Ter::statutoryCategoryMap(), $from);
        });

        Pph21Ter::forget();

        return back()->with('success', 'Tarif TER dikembalikan ke PP 58/2023');
    }

    /**
     * Correct a single bracket of the tariff in force.
     */
    public function updateBracket(Request $request, Pph21TerRate $rate): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'income_min' => ['required', 'numeric', 'min:0'],
            'income_max' => ['nullable', 'numeric', 'gt:income_min'],
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
        ], [
            'income_max.gt' => 'Batas atas harus lebih besar dari batas bawah.',
            'rate.max' => 'Tarif ditulis sebagai desimal, mis. 0,0225 untuk 2,25%.',
        ]);

        $rate->update($data);
        Pph21Ter::forget();

        return back()->with('success', 'Bracket TER diperbarui');
    }

    public function destroyBracket(Request $request, Pph21TerRate $rate): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $rate->delete();
        Pph21Ter::forget();

        return back()->with('success', 'Bracket TER dihapus');
    }

    /**
     * Move a PTKP status to another category, effective from a date.
     */
    public function updateCategoryMap(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'ptkp_status' => ['required', Rule::in(Pph21Ter::PTKP_STATUSES)],
            'category' => ['required', Rule::in(['A', 'B', 'C'])],
            'effective_start_date' => ['required', 'date'],
        ]);

        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        DB::transaction(function () use ($data, $from): void {
            Pph21TerCategory::query()
                ->where('ptkp_status', $data['ptkp_status'])
                ->effectiveOn($from)
                ->update(['effective_end_date' => $from->copy()->subDay()->toDateString()]);

            Pph21TerCategory::create([
                'ptkp_status' => $data['ptkp_status'],
                'category' => $data['category'],
                'effective_start_date' => $from->toDateString(),
            ]);
        });

        Pph21Ter::forget();

        return back()->with('success', 'Kategori PTKP diperbarui');
    }

    /**
     * Publish a table for a category: close whatever is in force the day before
     * the new set starts, then insert it.
     *
     * @param  list<array{income_min: float, income_max: float|null, rate: float}>  $brackets
     */
    private function publishBrackets(string $category, array $brackets, Carbon $from, string $source): void
    {
        if ($brackets === []) {
            return;
        }

        Pph21TerRate::query()
            ->where('category', $category)
            ->effectiveOn($from)
            ->update(['effective_end_date' => $from->copy()->subDay()->toDateString()]);

        // A set already starting on this very day is a re-import, not a new
        // version — drop it so the file replaces it cleanly.
        Pph21TerRate::query()
            ->where('category', $category)
            ->whereDate('effective_start_date', $from->toDateString())
            ->delete();

        foreach ($brackets as $bracket) {
            Pph21TerRate::create([
                'category' => $category,
                'income_min' => $bracket['income_min'],
                'income_max' => $bracket['income_max'],
                'rate' => $bracket['rate'],
                'effective_start_date' => $from->toDateString(),
                'source' => $source,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $map
     */
    private function publishCategoryMap(array $map, Carbon $from): void
    {
        Pph21TerCategory::query()
            ->effectiveOn($from)
            ->update(['effective_end_date' => $from->copy()->subDay()->toDateString()]);

        Pph21TerCategory::query()
            ->whereDate('effective_start_date', $from->toDateString())
            ->delete();

        foreach ($map as $status => $category) {
            Pph21TerCategory::create([
                'ptkp_status' => $status,
                'category' => $category,
                'effective_start_date' => $from->toDateString(),
            ]);
        }
    }

    /**
     * Turn the [upper bound, rate] constants into publishable bracket rows.
     *
     * @param  list<array{0: int, 1: float}>  $brackets
     * @return list<array{income_min: float, income_max: float|null, rate: float}>
     */
    private function fromStatutory(array $brackets): array
    {
        $rows = [];
        $min = 0.0;

        foreach ($brackets as [$upTo, $rate]) {
            $isTop = $upTo >= PHP_INT_MAX;

            $rows[] = [
                'income_min' => $min,
                'income_max' => $isTop ? null : (float) $upTo,
                'rate' => $rate,
            ];

            $min = $isTop ? $min : (float) $upTo;
        }

        return $rows;
    }

    /**
     * The bracket table in force per category, with the gap/overlap check the
     * screen shows — a tariff with a hole in it withholds nothing for incomes
     * that fall in the hole, which is worth catching before a payroll run does.
     *
     * @return list<array<string, mixed>>
     */
    private function categoryTables(string $asOf): array
    {
        return collect(Pph21Ter::CATEGORIES)
            ->map(function (string $label, string $category) use ($asOf): array {
                $rows = Pph21TerRate::query()
                    ->where('category', $category)
                    ->effectiveOn($asOf)
                    ->orderByRaw('income_max IS NULL')
                    ->orderBy('income_max')
                    ->get();

                return [
                    'code' => $category,
                    'label' => $label,
                    'effective_from' => $rows->first()?->effective_start_date?->toDateString(),
                    'source' => $rows->first()?->source,
                    'brackets' => $rows->map(fn (Pph21TerRate $row): array => [
                        'id' => $row->id,
                        'income_min' => (float) $row->income_min,
                        'income_max' => $row->income_max !== null ? (float) $row->income_max : null,
                        'rate' => (float) $row->rate,
                    ])->values()->all(),
                    'issues' => $this->issuesIn($rows),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Gaps, overlaps and a missing open-ended top bracket.
     *
     * @param  Collection<int, Pph21TerRate>  $rows
     * @return list<string>
     */
    private function issuesIn($rows): array
    {
        if ($rows->isEmpty()) {
            return ['Belum ada tarif — perhitungan memakai tabel bawaan PP 58/2023.'];
        }

        $issues = [];
        $previousMax = 0.0;
        $hasOpenTop = false;

        foreach ($rows as $index => $row) {
            $min = (float) $row->income_min;
            $max = $row->income_max !== null ? (float) $row->income_max : null;

            if ($index === 0 && $min > 0) {
                $issues[] = 'Bracket pertama tidak mulai dari 0.';
            }

            if ($index > 0 && abs($min - $previousMax) > 0.001) {
                $issues[] = sprintf(
                    'Ada %s antara %s dan %s.',
                    $min > $previousMax ? 'celah' : 'tumpang tindih',
                    number_format($previousMax, 0, ',', '.'),
                    number_format($min, 0, ',', '.'),
                );
            }

            if ($max === null) {
                $hasOpenTop = true;
            } else {
                $previousMax = $max;
            }
        }

        if (! $hasOpenTop) {
            $issues[] = 'Tidak ada bracket teratas tanpa batas — penghasilan di atas nilai tertinggi tidak tertagih.';
        }

        return $issues;
    }

    /**
     * @return list<array{ptkp_status: string, category: string, effective_from: string|null}>
     */
    private function categoryMapRows(string $asOf): array
    {
        $rows = Pph21TerCategory::query()->effectiveOn($asOf)->get()->keyBy('ptkp_status');
        $fallback = Pph21Ter::statutoryCategoryMap();

        return collect(Pph21Ter::PTKP_STATUSES)
            ->map(fn (string $status): array => [
                'ptkp_status' => $status,
                'category' => $rows[$status]->category ?? ($fallback[$status] ?? 'A'),
                'effective_from' => $rows[$status]?->effective_start_date?->toDateString(),
            ])
            ->all();
    }

    /**
     * Every published version, so the reader can see what else exists and jump
     * to the table that applied then.
     *
     * @return list<array{effective_start_date: string, categories: string, brackets: int}>
     */
    private function versions(): array
    {
        return Pph21TerRate::query()
            ->selectRaw('effective_start_date, COUNT(*) as brackets, GROUP_CONCAT(DISTINCT category ORDER BY category) as categories')
            ->groupBy('effective_start_date')
            ->orderByDesc('effective_start_date')
            ->get()
            ->map(fn ($row): array => [
                // A grouped raw select bypasses the cast, and MySQL hands the
                // date back with a time on it.
                'effective_start_date' => $row->effective_start_date !== null
                    ? Carbon::parse($row->effective_start_date)->toDateString()
                    : '',
                'categories' => (string) $row->categories,
                'brackets' => (int) $row->brackets,
            ])
            ->all();
    }

    /**
     * The date the screen reads the tariff at — today unless asked otherwise.
     */
    private function asOf(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return Carbon::now()->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return Carbon::now()->toDateString();
        }
    }

    private function ensureCanView(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo('payroll.view'), 403);
    }

    private function isSuperAdmin(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        return $user->roles->contains(fn ($role): bool => $role->code === 'super_admin');
    }

    /**
     * The TER tables are GLOBAL statutory config shared by every tenant, so only
     * a super admin may change them — a tenant admin editing them would move the
     * tariff for the whole platform.
     */
    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($this->isSuperAdmin($request), 403);
    }
}
