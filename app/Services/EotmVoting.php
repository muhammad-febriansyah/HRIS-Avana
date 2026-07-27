<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EotmPeriod;
use App\Models\EotmVote;
use App\Support\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee of the Month voting rules, in one place.
 *
 * Three invariants the callers rely on:
 *  - votes are only accepted while the period is `open`;
 *  - one vote per employee per period (changing your mind updates it);
 *  - nobody votes for themselves.
 */
final class EotmVoting
{
    /**
     * The period employees may currently vote in, if any.
     */
    public function openPeriod(int $tenantId): ?EotmPeriod
    {
        return EotmPeriod::forTenant($tenantId)->open()->latest('period')->first();
    }

    /**
     * Cast or change a vote.
     *
     * @throws ValidationException when the period is closed, or the voter is
     *                             nominating themselves
     */
    public function vote(
        EotmPeriod $period,
        Employee $voter,
        Employee $nominee,
        ?int $coreValueId = null,
        ?string $reason = null,
    ): EotmVote {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'period' => 'Voting untuk periode ini sudah ditutup.',
            ]);
        }

        if ((int) $nominee->id === (int) $voter->id) {
            throw ValidationException::withMessages([
                'nominee_employee_id' => 'Kamu tidak bisa memilih dirimu sendiri.',
            ]);
        }

        if ((int) $nominee->tenant_id !== (int) $voter->tenant_id) {
            throw ValidationException::withMessages([
                'nominee_employee_id' => 'Karyawan tidak ditemukan.',
            ]);
        }

        // updateOrCreate on the (period, voter) unique key: a second vote
        // replaces the first rather than stacking.
        return EotmVote::updateOrCreate(
            [
                'eotm_period_id' => $period->id,
                'voter_employee_id' => $voter->id,
            ],
            [
                'tenant_id' => $period->tenant_id,
                'nominee_employee_id' => $nominee->id,
                'eotm_core_value_id' => $coreValueId,
                'reason' => $reason,
            ],
        );
    }

    /**
     * Live tally for a period, most votes first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function standings(EotmPeriod $period, int $limit = 50): Collection
    {
        $rows = EotmVote::query()
            ->where('eotm_period_id', $period->id)
            ->with(['nominee:id,full_name,photo_path', 'coreValue:id,name,icon,color'])
            ->get();

        $total = $rows->count();

        return $rows->groupBy('nominee_employee_id')
            ->map(function (Collection $votes) use ($total): array {
                $nominee = $votes->first()->nominee;

                // The value most often attributed to this nominee — what the
                // colleagues actually recognised them for.
                $topValue = $votes->groupBy('eotm_core_value_id')
                    ->sortByDesc(fn (Collection $group): int => $group->count())
                    ->first()
                    ?->first()
                    ?->coreValue;

                return [
                    'employee_id' => (int) $votes->first()->nominee_employee_id,
                    'name' => $nominee?->full_name ?? 'Karyawan',
                    'photo' => $nominee?->photo_path,
                    'votes' => $votes->count(),
                    'percent' => $total > 0 ? (int) round($votes->count() / $total * 100) : 0,
                    'core_value' => $topValue?->name,
                    'core_value_icon' => $topValue?->icon,
                    'core_value_color' => $topValue?->color,
                ];
            })
            ->sortByDesc('votes')
            ->values()
            ->take($limit)
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    /**
     * Close a period and stamp its winner, so the result stops depending on the
     * votes still being there. A tie is resolved by the lowest employee id —
     * arbitrary but stable, and HR can override by reopening.
     */
    public function close(EotmPeriod $period): EotmPeriod
    {
        return DB::transaction(function () use ($period): EotmPeriod {
            $top = $this->standings($period)->first();

            $period->update([
                'status' => EotmPeriod::STATUS_CLOSED,
                'closes_at' => $period->closes_at ?? now(),
                'winner_employee_id' => $top['employee_id'] ?? null,
                'winner_votes' => $top['votes'] ?? 0,
            ]);

            $period->refresh();

            Notifier::eotmPeriodClosed($period);

            return $period;
        });
    }

    /**
     * The vote this employee already cast in the period, if any.
     */
    public function voteOf(EotmPeriod $period, Employee $employee): ?EotmVote
    {
        return EotmVote::query()
            ->where('eotm_period_id', $period->id)
            ->where('voter_employee_id', $employee->id)
            ->with(['nominee:id,full_name,photo_path', 'coreValue:id,name'])
            ->first();
    }
}
