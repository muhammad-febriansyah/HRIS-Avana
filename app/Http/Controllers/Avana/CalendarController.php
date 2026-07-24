<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Roles that may always manage the calendar within their tenant.
     *
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'calendar';

    /**
     * Allowed calendar event type enum values.
     *
     * @var array<int, string>
     */
    private const TYPES = ['holiday', 'meeting', 'training', 'event', 'deadline'];

    /**
     * Indonesian month names indexed 1-12.
     *
     * @var array<int, string>
     */
    private const MONTHS_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Render the month-grid calendar of events for the requested month.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = $request->user()->tenant_id;

        $month = $this->resolveMonth($request->query('month'));
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $events = CalendarEvent::forTenant($tenantId)
            ->with(['employee:id,full_name', 'department:id,name'])
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where(function ($query) use ($monthStart): void {
                $query->where('end_date', '>=', $monthStart->toDateString())
                    ->orWhere(function ($inner) use ($monthStart): void {
                        $inner->whereNull('end_date')->where('start_date', '>=', $monthStart->toDateString());
                    });
            })
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (CalendarEvent $event): array => $this->transformEvent($event));

        return Inertia::render('avana/kalender/index', [
            'month' => $month->format('Y-m'),
            'monthLabel' => self::MONTHS_ID[$month->month].' '.$month->year,
            'events' => $events,
            'types' => $this->typeOptions(),
            'employees' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(fn (Employee $e): array => ['value' => (string) $e->id, 'label' => $e->full_name])
                ->all(),
            'departments' => Department::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $d): array => ['value' => (string) $d->id, 'label' => $d->name])
                ->all(),
            'kpis' => [
                'this_month' => $events->count(),
            ],
        ]);
    }

    /**
     * Persist a new calendar event under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $data = $this->validateEvent($request);

        CalendarEvent::create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return back()->with('success', 'Acara berhasil ditambahkan');
    }

    /**
     * Update an existing calendar event.
     */
    public function update(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $event);

        $event->update($this->validateEvent($request));

        return back()->with('success', 'Acara berhasil diperbarui');
    }

    /**
     * Delete a calendar event.
     */
    public function destroy(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $event);

        $event->delete();

        return back()->with('success', 'Acara dihapus');
    }

    /**
     * Resolve the requested `YYYY-MM` month, falling back to the current month.
     */
    private function resolveMonth(?string $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // fall through to the current month
            }
        }

        return Carbon::now()->startOfMonth();
    }

    /**
     * Validate the create/update payload for a calendar event.
     *
     * @return array<string, mixed>
     */
    private function validateEvent(Request $request): array
    {
        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'all_day' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);

        $data['all_day'] = $request->boolean('all_day', true);

        return $data;
    }

    /**
     * Build the row shape consumed by the calendar grid.
     *
     * @return array<string, mixed>
     */
    private function transformEvent(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'employee_id' => $event->employee_id,
            'department_id' => $event->department_id,
            // Who the agenda is for: a named employee wins, else a department.
            'assignee' => $event->employee?->full_name ?? $event->department?->name,
            'start_date' => $event->start_date?->toDateString(),
            'end_date' => $event->end_date?->toDateString(),
            'all_day' => (bool) $event->all_day,
            'color' => $event->color,
            'description' => $event->description,
        ];
    }

    /**
     * Build the `{ value, label }` list of event types.
     *
     * @return array<int, array<string, string>>
     */
    private function typeOptions(): array
    {
        $labels = [
            'holiday' => 'Libur',
            'meeting' => 'Rapat',
            'training' => 'Pelatihan',
            'event' => 'Acara',
            'deadline' => 'Tenggat',
        ];

        return collect(self::TYPES)
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => $labels[$type],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, CalendarEvent $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}
