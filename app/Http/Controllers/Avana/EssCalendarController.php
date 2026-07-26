<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kalender Saya" — company events the employee is actually part of: tenant-wide
 * ones, their own department's, and anything addressed to them personally.
 */
class EssCalendarController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the event type enum.
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'holiday' => 'Libur',
        'meeting' => 'Rapat',
        'training' => 'Pelatihan',
        'event' => 'Acara',
        'deadline' => 'Tenggat',
        'birthday' => 'Ulang Tahun',
    ];

    /**
     * Events for a month, split into upcoming and past.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $month = $this->resolveMonth($request);
        $today = Carbon::today();

        $events = CalendarEvent::forTenant($employee->tenant_id)
            ->where(function ($query) use ($employee) {
                // Tenant-wide when neither an employee nor a department is set.
                $query
                    ->where(fn ($scoped) => $scoped
                        ->whereNull('employee_id')
                        ->whereNull('department_id'))
                    ->orWhere('employee_id', $employee->id)
                    ->orWhere(fn ($scoped) => $scoped
                        ->whereNotNull('department_id')
                        ->where('department_id', $employee->department_id));
            })
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->where(function ($query) use ($month) {
                $query
                    ->whereDate('end_date', '>=', $month->copy()->startOfMonth()->toDateString())
                    ->orWhereNull('end_date');
            })
            ->orderBy('start_date')
            ->get();

        $rows = $events->map(fn (CalendarEvent $event): array => [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'type_label' => self::TYPE_LABELS[$event->type] ?? $event->type,
            'start_date' => $this->dateString($event->start_date),
            'end_date' => $this->dateString($event->end_date),
            'all_day' => (bool) $event->all_day,
            'color' => $event->color,
            'description' => $event->description,
            'scope' => $event->employee_id !== null
                ? 'personal'
                : ($event->department_id !== null ? 'departemen' : 'perusahaan'),
        ]);

        return Inertia::render('avana/saya/kalender', [
            'month' => $month->format('Y-m'),
            'upcoming' => $rows
                ->filter(fn (array $row): bool => Carbon::parse($row['end_date'] ?? $row['start_date'])->gte($today))
                ->values(),
            'past' => $rows
                ->filter(fn (array $row): bool => Carbon::parse($row['end_date'] ?? $row['start_date'])->lt($today))
                ->values(),
        ]);
    }

    /**
     * The month being viewed, defaulting to the current one.
     *
     * Typed as CarbonInterface, not Illuminate\Support\Carbon: the app runs
     * Date::use(CarbonImmutable::class), which does not extend that class.
     */
    private function resolveMonth(Request $request): CarbonInterface
    {
        $month = $request->query('month');

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return Carbon::parse($month.'-01')->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
