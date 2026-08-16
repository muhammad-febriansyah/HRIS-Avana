<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\PreviewPph21TerImportRequest;
use App\Http\Requests\Avana\PublishPph21TerImportRequest;
use App\Http\Requests\Avana\RemovePph21TerBracketRequest;
use App\Http\Requests\Avana\ResetPph21TerRequest;
use App\Http\Requests\Avana\RevisePph21TerBracketRequest;
use App\Http\Requests\Avana\UpdatePph21TerCategoryRequest;
use App\Models\Pph21TerCategory;
use App\Models\Pph21TerRate;
use App\Models\User;
use App\Services\Pph21TerPublisher;
use App\Support\Pph21Ter;
use App\Support\Pph21TerImport;
use App\Support\Pph21TerPreviewToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
 * entered — upload the official workbook, or correct a bracket — instead of
 * shipping a release. Every version is dated, so publishing next year's table
 * never changes what last year's payslips charged.
 *
 * A published version is immutable. Nothing here edits a row that payroll has
 * already read: every change — an import, a reset, a single corrected bracket,
 * a PTKP status moved — closes the version in force the day before and
 * publishes a new one, with the author, the reason and the workbook checksum
 * recorded on every row. An import must be previewed first, and the preview is
 * bound to the exact file it validated.
 *
 * Reading is open to anyone who can see payroll; writing is super-admin only,
 * because the rows are global and a tenant admin editing them would move the
 * tariff for every tenant on the platform.
 */
class Pph21TerController extends Controller
{
    public function __construct(private readonly Pph21TerPublisher $publisher) {}

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
            // Set by preview(); the screen shows the old-versus-new comparison
            // and only then unlocks the publish button.
            'preview' => session('terPreview'),
        ]);
    }

    /**
     * Validate an uploaded workbook and show what publishing it would change,
     * without writing anything.
     *
     * The token handed back is bound to the file's checksum and to the
     * effective date, source and reason that were previewed, so the publish
     * step cannot quietly apply a different file or a different date than the
     * one somebody looked at.
     */
    public function preview(PreviewPph21TerImportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $path = $request->file('file')->getRealPath();
        $checksum = hash_file('sha256', $path);
        $parsed = $this->parseWorkbook($path);
        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        $context = [
            'checksum' => $checksum,
            'effective_start_date' => $from->toDateString(),
            'source' => $data['source'],
            'reason' => $data['reason'],
            'user_id' => (int) $request->user()->id,
        ];

        return back()->with('terPreview', [
            ...$context,
            'token' => Pph21TerPreviewToken::issue($context),
            'file_name' => $request->file('file')->getClientOriginalName(),
            'blockers' => $this->publisher->blockers($from),
            'categories' => $this->previewCategories($parsed['brackets'], $from),
            'category_map' => $this->previewCategoryMap($parsed['categories'], $from),
            'sheets' => $parsed['sheets'],
        ]);
    }

    /**
     * Publish a previewed workbook as a new dated version.
     */
    public function import(PublishPph21TerImportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $path = $request->file('file')->getRealPath();
        $checksum = hash_file('sha256', $path);
        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        Pph21TerPreviewToken::assertMatches($data['preview_token'], [
            'checksum' => $checksum,
            'effective_start_date' => $from->toDateString(),
            'source' => $data['source'],
            'reason' => $data['reason'],
            'user_id' => (int) $request->user()->id,
        ]);

        $parsed = $this->parseWorkbook($path);

        $this->publisher->publish(
            $parsed['brackets'],
            $parsed['categories'],
            $from,
            $data['source'],
            $data['reason'],
            $checksum,
            (int) $request->user()->id,
        );

        $summary = collect($parsed['brackets'])
            ->map(fn (array $rows, string $category): string => $category.' '.count($rows).' baris')
            ->implode(', ');

        return back()->with('success', 'Tarif TER terbit berlaku '.$from->toDateString().': '.$summary);
    }

    /**
     * Publish the PP 58/2023 tariff as enacted as a new dated version.
     */
    public function reset(ResetPph21TerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        $this->publisher->publish(
            $this->publisher->statutoryTables(),
            Pph21Ter::statutoryCategoryMap(),
            $from,
            'PP 58/2023 & PMK 168/2023',
            $data['reason'],
            null,
            (int) $request->user()->id,
        );

        return back()->with('success', 'Tarif TER kembali ke PP 58/2023, berlaku '.$from->toDateString());
    }

    /**
     * Correct one bracket by publishing a new version of its whole category.
     *
     * The row that was read by a payroll run is never touched: the version in
     * force is closed the day before, and the corrected table starts on the new
     * effective date.
     */
    public function updateBracket(RevisePph21TerBracketRequest $request, Pph21TerRate $rate): RedirectResponse
    {
        $data = $request->validated();
        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        $this->publishRevision(
            $rate,
            $from,
            $data['reason'],
            (int) $request->user()->id,
            fn (array $brackets): array => $this->replaceBracket($brackets, $rate, [
                'income_min' => (float) $data['income_min'],
                'income_max' => $data['income_max'] !== null ? (float) $data['income_max'] : null,
                'rate' => (float) $data['rate'],
            ]),
        );

        return back()->with('success', 'Bracket dikoreksi lewat versi baru berlaku '.$from->toDateString());
    }

    /**
     * Drop one bracket, again as a new version of the category.
     *
     * The remaining table still has to be a valid tariff — no gap, no missing
     * open-ended top bracket — so removing a middle band is refused rather than
     * leaving income nobody withholds on.
     */
    public function destroyBracket(RemovePph21TerBracketRequest $request, Pph21TerRate $rate): RedirectResponse
    {
        $data = $request->validated();
        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        $this->publishRevision(
            $rate,
            $from,
            $data['reason'],
            (int) $request->user()->id,
            fn (array $brackets): array => array_values(array_filter(
                $brackets,
                fn (array $row): bool => abs($row['income_min'] - (float) $rate->income_min) > 0.001,
            )),
        );

        return back()->with('success', 'Bracket dihapus lewat versi baru berlaku '.$from->toDateString());
    }

    /**
     * Move a PTKP status to another category, effective from a date.
     *
     * The whole mapping is republished, not just the row that moved, so the
     * eight statuses always resolve from one dated version instead of eight
     * independently dated ones.
     */
    public function updateCategoryMap(UpdatePph21TerCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $from = Carbon::parse($data['effective_start_date'])->startOfDay();

        $map = $this->publisher->categoryMapOn($from->toDateString());
        $map[$data['ptkp_status']] = $data['category'];

        $this->publisher->publish(
            $this->publisher->tablesOn($from->toDateString()),
            $map,
            $from,
            'Koreksi kategori PTKP',
            $data['reason'],
            null,
            (int) $request->user()->id,
        );

        return back()->with('success', 'Kategori PTKP terbit sebagai versi baru berlaku '.$from->toDateString());
    }

    /**
     * Republish a category with one bracket rewritten by the given callback.
     *
     * @param  callable(list<array{income_min: float, income_max: float|null, rate: float}>): list<array{income_min: float, income_max: float|null, rate: float}>  $revise
     */
    private function publishRevision(Pph21TerRate $rate, Carbon $from, string $reason, int $actorId, callable $revise): void
    {
        $tables = $this->publisher->tablesOn($from->toDateString());
        $category = $rate->category;

        $tables[$category] = $revise($tables[$category] ?? []);

        $this->publisher->publish(
            $tables,
            $this->publisher->categoryMapOn($from->toDateString()),
            $from,
            'Koreksi manual bracket '.$category,
            $reason,
            null,
            $actorId,
        );
    }

    /**
     * @param  list<array{income_min: float, income_max: float|null, rate: float}>  $brackets
     * @param  array{income_min: float, income_max: float|null, rate: float}  $replacement
     * @return list<array{income_min: float, income_max: float|null, rate: float}>
     */
    private function replaceBracket(array $brackets, Pph21TerRate $rate, array $replacement): array
    {
        $rewritten = [];

        foreach ($brackets as $row) {
            $rewritten[] = abs($row['income_min'] - (float) $rate->income_min) <= 0.001
                ? $replacement
                : $row;
        }

        usort($rewritten, static fn (array $a, array $b): int => $a['income_min'] <=> $b['income_min']);

        return array_values($rewritten);
    }

    /**
     * Parse an uploaded workbook, turning every read/validation failure into a
     * field error on the file input.
     *
     * @return array{
     *     brackets: array<string, list<array{income_min: float, income_max: float|null, rate: float}>>,
     *     categories: array<string, string>,
     *     sheets: list<string>
     * }
     */
    private function parseWorkbook(string $path): array
    {
        try {
            return Pph21TerImport::parse($path);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak bisa dibaca sebagai workbook TER.']);
        }
    }

    /**
     * Old versus new, per category, for the preview screen.
     *
     * @param  array<string, list<array{income_min: float, income_max: float|null, rate: float}>>  $incoming
     * @return list<array<string, mixed>>
     */
    private function previewCategories(array $incoming, Carbon $from): array
    {
        $current = $this->publisher->tablesOn($from->toDateString());

        return collect(Pph21Ter::CATEGORIES)
            ->map(function (string $labelText, string $code) use ($incoming, $current): array {
                $before = $current[$code] ?? [];
                $after = $incoming[$code] ?? [];

                return [
                    'code' => $code,
                    'label' => $labelText,
                    'current_brackets' => count($before),
                    'incoming_brackets' => count($after),
                    'changed' => $this->normalise($before) !== $this->normalise($after),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $incoming
     * @return list<array{ptkp_status: string, current: string|null, incoming: string|null, changed: bool}>
     */
    private function previewCategoryMap(array $incoming, Carbon $from): array
    {
        $current = $this->publisher->categoryMapOn($from->toDateString());

        return collect(Pph21Ter::PTKP_STATUSES)
            ->map(fn (string $status): array => [
                'ptkp_status' => $status,
                'current' => $current[$status] ?? null,
                'incoming' => $incoming[$status] ?? null,
                'changed' => ($current[$status] ?? null) !== ($incoming[$status] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * Bracket rows as comparable scalars, so "the same table" does not read as
     * a change because of float formatting.
     *
     * @param  list<array{income_min: float, income_max: float|null, rate: float}>  $brackets
     * @return list<string>
     */
    private function normalise(array $brackets): array
    {
        return array_map(
            static fn (array $row): string => sprintf(
                '%.2f|%s|%.6f',
                $row['income_min'],
                $row['income_max'] === null ? 'inf' : sprintf('%.2f', $row['income_max']),
                $row['rate'],
            ),
            $brackets,
        );
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
                    'change_reason' => $rows->first()?->change_reason,
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

        // TER Harian stops at Rp2,5jt by design — above that the caller applies
        // 50% × Pasal 17, so the missing open bracket is the rule, not a hole.
        if (! $hasOpenTop && $rows->first()?->category !== 'HARIAN') {
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
}
