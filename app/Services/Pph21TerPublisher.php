<?php

namespace App\Services;

use App\Models\PayrollPeriod;
use App\Models\Pph21TerCategory;
use App\Models\Pph21TerRate;
use App\Support\Pph21Ter;
use App\Support\Pph21TerTableValidator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class Pph21TerPublisher
{
    /**
     * @param  array<string, list<array{income_min: float, income_max: float|null, rate: float}>>  $tables
     * @param  array<string, string>  $categoryMap
     */
    public function publish(
        array $tables,
        array $categoryMap,
        Carbon $from,
        string $source,
        string $reason,
        ?string $checksum,
        int $actorId,
    ): void {
        try {
            Pph21TerTableValidator::validate($tables, $categoryMap);
        } catch (RuntimeException $e) {
            // A table that fails the check is never written — a correction that
            // would leave a gap is refused with the reason on screen.
            throw ValidationException::withMessages(['tarif' => $e->getMessage()]);
        }

        try {
            Cache::lock('pph21-ter:publish', 30)->block(5, function () use ($tables, $categoryMap, $from, $source, $reason, $checksum, $actorId): void {
                DB::transaction(function () use ($tables, $categoryMap, $from, $source, $reason, $checksum, $actorId): void {
                    Pph21TerRate::query()->orderBy('id')->lockForUpdate()->get(['id']);
                    Pph21TerCategory::query()->orderBy('id')->lockForUpdate()->get(['id']);

                    $this->assertPublishableDate($from);

                    foreach ($tables as $category => $brackets) {
                        $this->publishCategoryRates($category, $brackets, $from, $source, $reason, $checksum, $actorId);
                    }

                    $this->publishCategoryMap($categoryMap, $from, $source, $reason, $checksum, $actorId);
                }, attempts: 3);
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'effective_start_date' => 'Master TER sedang dipublikasikan oleh pengguna lain. Coba lagi beberapa saat.',
            ]);
        }

        Pph21Ter::forget();
    }

    /**
     * @return array<string, list<array{income_min: float, income_max: float|null, rate: float}>>
     */
    public function tablesOn(string $date): array
    {
        $tables = [];

        foreach (array_keys(Pph21Ter::CATEGORIES) as $category) {
            $effective = Pph21TerRate::query()->where('category', $category)->effectiveOn($date);
            $latest = (clone $effective)->max('effective_start_date');

            $rows = $effective
                ->when($latest !== null, fn ($query) => $query->whereDate('effective_start_date', Carbon::parse($latest)->toDateString()))
                ->orderBy('income_min')
                ->get(['income_min', 'income_max', 'rate']);

            if ($rows->isEmpty()) {
                $statutory = $category === 'HARIAN'
                    ? Pph21Ter::statutoryDaily()
                    : Pph21Ter::statutoryMonthly()[$category];
                $tables[$category] = $this->fromStatutory($statutory);

                continue;
            }

            $tables[$category] = $rows->map(fn (Pph21TerRate $row): array => [
                'income_min' => (float) $row->income_min,
                'income_max' => $row->income_max !== null ? (float) $row->income_max : null,
                'rate' => (float) $row->rate,
            ])->all();
        }

        return $tables;
    }

    /**
     * @return array<string, string>
     */
    public function categoryMapOn(string $date): array
    {
        $rows = Pph21TerCategory::query()
            ->effectiveOn($date)
            ->orderBy('effective_start_date')
            ->orderBy('id')
            ->get(['ptkp_status', 'category'])
            ->pluck('category', 'ptkp_status')
            ->all();

        return $rows === [] ? Pph21Ter::statutoryCategoryMap() : $rows;
    }

    /**
     * The statutory PP 58/2023 tables in publishable shape.
     *
     * @return array<string, list<array{income_min: float, income_max: float|null, rate: float}>>
     */
    public function statutoryTables(): array
    {
        $tables = [];

        foreach (Pph21Ter::statutoryMonthly() as $category => $brackets) {
            $tables[$category] = $this->fromStatutory($brackets);
        }

        $tables['HARIAN'] = $this->fromStatutory(Pph21Ter::statutoryDaily());

        return $tables;
    }

    /**
     * Why this effective date cannot be published on, in plain language.
     *
     * A published version is immutable: correcting one means a new version on a
     * new date, never an edit in place. And a date that reaches back into a
     * locked payroll period would change the tariff a payslip was already
     * issued under.
     *
     * @return list<string>
     */
    public function blockers(Carbon $from): array
    {
        $blockers = [];

        $sameDayExists = Pph21TerRate::query()
            ->whereDate('effective_start_date', $from->toDateString())
            ->exists() || Pph21TerCategory::query()
            ->whereDate('effective_start_date', $from->toDateString())
            ->exists();

        if ($sameDayExists) {
            $blockers[] = 'Versi TER dengan tanggal berlaku '.$from->toDateString()
                .' sudah terbit dan tidak bisa ditimpa. Pakai tanggal berlaku baru.';
        }

        $lockedPeriod = PayrollPeriod::query()
            ->where('status', 'locked')
            ->whereDate('end_date', '>=', $from->toDateString())
            ->orderByDesc('end_date')
            ->first(['id', 'code', 'end_date']);

        if ($lockedPeriod !== null) {
            $blockers[] = "Tanggal berlaku masuk ke periode payroll terkunci {$lockedPeriod->code}"
                .' — tarif yang sudah dipakai payroll terkunci tidak boleh berubah.'
                .' Pakai tanggal setelah '.$lockedPeriod->end_date?->toDateString().'.';
        }

        return $blockers;
    }

    private function assertPublishableDate(Carbon $from): void
    {
        $blockers = $this->blockers($from);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['effective_start_date' => $blockers[0]]);
        }
    }

    /**
     * @param  list<array{income_min: float, income_max: float|null, rate: float}>  $brackets
     */
    private function publishCategoryRates(
        string $category,
        array $brackets,
        Carbon $from,
        string $source,
        string $reason,
        ?string $checksum,
        int $actorId,
    ): void {
        $successor = Pph21TerRate::query()
            ->where('category', $category)
            ->whereDate('effective_start_date', '>', $from->toDateString())
            ->min('effective_start_date');
        $effectiveEnd = $successor !== null ? Carbon::parse($successor)->subDay()->toDateString() : null;

        $currentStart = Pph21TerRate::query()
            ->where('category', $category)
            ->effectiveOn($from)
            ->max('effective_start_date');

        if ($currentStart !== null) {
            Pph21TerRate::query()
                ->where('category', $category)
                ->whereDate('effective_start_date', Carbon::parse($currentStart)->toDateString())
                ->get()
                ->each(fn (Pph21TerRate $rate) => $rate->update([
                    'effective_end_date' => $from->copy()->subDay()->toDateString(),
                ]));
        }

        foreach ($brackets as $bracket) {
            Pph21TerRate::create([
                'category' => $category,
                'income_min' => $bracket['income_min'],
                'income_max' => $bracket['income_max'],
                'rate' => $bracket['rate'],
                'effective_start_date' => $from->toDateString(),
                'effective_end_date' => $effectiveEnd,
                'source' => $source,
                'source_checksum' => $checksum,
                'change_reason' => $reason,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $map
     */
    private function publishCategoryMap(
        array $map,
        Carbon $from,
        string $source,
        string $reason,
        ?string $checksum,
        int $actorId,
    ): void {
        $successor = Pph21TerCategory::query()
            ->whereDate('effective_start_date', '>', $from->toDateString())
            ->min('effective_start_date');
        $effectiveEnd = $successor !== null ? Carbon::parse($successor)->subDay()->toDateString() : null;

        $currentStart = Pph21TerCategory::query()->effectiveOn($from)->max('effective_start_date');

        if ($currentStart !== null) {
            Pph21TerCategory::query()
                ->whereDate('effective_start_date', Carbon::parse($currentStart)->toDateString())
                ->get()
                ->each(fn (Pph21TerCategory $row) => $row->update([
                    'effective_end_date' => $from->copy()->subDay()->toDateString(),
                ]));
        }

        foreach ($map as $status => $category) {
            Pph21TerCategory::create([
                'ptkp_status' => $status,
                'category' => $category,
                'effective_start_date' => $from->toDateString(),
                'effective_end_date' => $effectiveEnd,
                'source' => $source,
                'source_checksum' => $checksum,
                'change_reason' => $reason,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * @param  list<array{0: int, 1: float}>  $brackets
     * @return list<array{income_min: float, income_max: float|null, rate: float}>
     */
    private function fromStatutory(array $brackets): array
    {
        $rows = [];
        $minimum = 0.0;

        foreach ($brackets as [$upperBound, $rate]) {
            $isTop = $upperBound >= PHP_INT_MAX;
            $rows[] = [
                'income_min' => $minimum,
                'income_max' => $isTop ? null : (float) $upperBound,
                'rate' => $rate,
            ];
            $minimum = $isTop ? $minimum : (float) $upperBound;
        }

        return $rows;
    }
}
