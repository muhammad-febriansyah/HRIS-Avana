<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttritionSetting;
use App\Models\Employee;
use App\Models\EmployeeCareerHistory;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PerformanceReview;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\AttritionScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prediksi Risiko Resign — a read-only dashboard that scores every active
 * employee's attrition risk (0-100) via {@see AttritionScorer} and surfaces the
 * factors driving each score.
 */
class AttritionController extends Controller
{
    use AppliesBranchScope;

    private const MODULE = 'attrition';

    /**
     * Avatar background palette, indexed by a stable hash of the name.
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Roles that can be routed an attrition alert (code => label).
     *
     * @var array<string, string>
     */
    private const ROLE_OPTIONS = [
        'admin_tenant_hr' => 'Admin / HR',
        'manager' => 'Manager',
        'finance' => 'Finance',
    ];

    public function __construct(private AttritionScorer $scorer) {}

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) ($request->user()->tenant_id ?? 0);

        $query = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->with(['department:id,name', 'branch:id,name', 'position:id,name']);

        $this->applyBranchScope($query, $request->user());

        $settings = AttritionSetting::resolve($tenantId);
        $scored = $query->get()->map(fn (Employee $employee): array => $this->shape($employee, $settings));

        $kpis = [
            'total' => $scored->count(),
            'high' => $scored->where('category', 'high')->count(),
            'medium' => $scored->where('category', 'medium')->count(),
            'low' => $scored->where('category', 'low')->count(),
            'avg' => $scored->isNotEmpty() ? (int) round($scored->avg('score')) : 0,
        ];

        $rows = $this->filterRows($scored, $request)
            ->sortByDesc('score')
            ->values()
            ->all();

        return Inertia::render('avana/attrition/index', [
            'rows' => $rows,
            'kpis' => $kpis,
            'filters' => [
                'search' => trim((string) $request->query('search', '')) ?: null,
                'category' => in_array($request->query('category'), ['low', 'medium', 'high'], true)
                    ? $request->query('category')
                    : null,
            ],
        ]);
    }

    public function show(Request $request, Employee $employee): Response
    {
        $this->ensureCan($request, 'view');

        abort_if((int) $employee->tenant_id !== (int) ($request->user()->tenant_id ?? 0), 404);

        $employee->load([
            'department:id,name',
            'branch:id,name',
            'position:id,name',
            'jobLevel:id,name',
            'manager:id,full_name',
        ]);

        $result = $this->scorer->score($employee);

        return Inertia::render('avana/attrition/show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'branch' => $employee->branch?->name,
                'position' => $employee->position?->name,
                'job_level' => $employee->jobLevel?->name,
                'manager' => $employee->manager?->full_name,
                'join_date' => $employee->join_date?->translatedFormat('d M Y'),
                'initials' => $this->initials($employee->full_name),
                'avatar_color' => $this->avatarColor($employee->full_name),
            ],
            'result' => $result,
        ]);
    }

    /**
     * The "Setup Master" config page — tune per-factor weights and the risk
     * band cut-offs for the tenant.
     */
    public function settings(Request $request): Response
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) ($request->user()->tenant_id ?? 0);
        $settings = AttritionSetting::resolve($tenantId);
        $readiness = $this->dataReadiness($tenantId);

        return Inertia::render('avana/attrition/settings', [
            'factors' => collect(AttritionScorer::FACTOR_LABELS)
                ->map(fn (string $label, string $key): array => [
                    'key' => $key,
                    'label' => $label,
                    'weight' => (int) ($settings->weights[$key] ?? 0),
                    'source' => AttritionScorer::FACTOR_SOURCES[$key] ?? '',
                    'active' => ! $settings->factorDisabled($key),
                    'has_data' => (bool) ($readiness[$key] ?? false),
                ])
                ->values()
                ->all(),
            'bands' => [
                'low' => $settings->band_low,
                'medium' => $settings->band_medium,
            ],
            'alerts' => [
                'enabled' => $settings->alerts_enabled,
                'threshold' => $settings->alert_threshold,
                'weekly_summary' => $settings->weekly_summary,
            ],
            'routing' => [
                'high' => $settings->notify_roles['high'] ?? null,
                'medium' => $settings->notify_roles['medium'] ?? null,
                'low' => $settings->notify_roles['low'] ?? null,
            ],
            'frequency' => $settings->scan_frequency,
            'roleOptions' => collect(self::ROLE_OPTIONS)
                ->map(fn (string $label, string $code): array => ['code' => $code, 'label' => $label])
                ->values()
                ->all(),
            'defaults' => [
                'weights' => config('attrition.weights'),
                'bands' => config('attrition.bands'),
            ],
        ]);
    }

    /**
     * Persist the tenant's full Setup Master configuration.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $roleCodes = array_keys(self::ROLE_OPTIONS);
        $factorKeys = array_keys(AttritionScorer::FACTOR_LABELS);

        $data = $request->validate([
            'weights' => ['required', 'array'],
            'weights.*' => ['integer', 'min:0', 'max:100'],
            'band_low' => ['required', 'integer', 'min:0', 'max:98'],
            'band_medium' => ['required', 'integer', 'gt:band_low', 'max:99'],
            'alerts_enabled' => ['boolean'],
            'alert_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'weekly_summary' => ['boolean'],
            'scan_frequency' => ['required', Rule::in(['daily', 'weekly', 'off'])],
            'notify_roles' => ['array'],
            'notify_roles.high' => ['nullable', Rule::in($roleCodes)],
            'notify_roles.medium' => ['nullable', Rule::in($roleCodes)],
            'notify_roles.low' => ['nullable', Rule::in($roleCodes)],
            'disabled_factors' => ['array'],
            'disabled_factors.*' => [Rule::in($factorKeys)],
        ]);

        $weights = [];
        foreach ($factorKeys as $key) {
            $weights[$key] = (int) ($data['weights'][$key] ?? 0);
        }

        // At least one enabled factor must carry weight, else nothing is scored.
        $disabled = array_values($data['disabled_factors'] ?? []);
        $activeWeight = collect($weights)->reject(fn ($w, $k) => in_array($k, $disabled, true))->sum();
        if ($activeWeight === 0) {
            return back()->withErrors(['weights' => 'Minimal satu faktor aktif harus punya bobot lebih dari 0.']);
        }

        AttritionSetting::updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id],
            [
                'weights' => $weights,
                'band_low' => $data['band_low'],
                'band_medium' => $data['band_medium'],
                'alerts_enabled' => $data['alerts_enabled'] ?? false,
                'alert_threshold' => $data['alert_threshold'],
                'weekly_summary' => $data['weekly_summary'] ?? false,
                'scan_frequency' => $data['scan_frequency'],
                'notify_roles' => array_intersect_key(
                    $data['notify_roles'] ?? [],
                    array_flip(['high', 'medium', 'low']),
                ),
                'disabled_factors' => $disabled,
            ],
        );

        return redirect()->route('avana.attrition')->with('success', 'Konfigurasi risiko resign disimpan');
    }

    /**
     * Whether the tenant has any data behind each factor (drives the "Kesiapan
     * Data" indicators — the real, honest replacement for the mockup's external
     * data-source panel).
     *
     * @return array<string, bool>
     */
    private function dataReadiness(int $tenantId): array
    {
        return [
            'tenure' => Employee::forTenant($tenantId)->whereNotNull('join_date')->exists(),
            'no_raise' => EmployeeSalaryComponent::whereHas('employee', fn ($q) => $q->where('tenant_id', $tenantId))
                ->whereNotNull('effective_start_date')->exists(),
            'lateness' => Attendance::forTenant($tenantId)->where('late_minutes', '>', 0)->exists(),
            'overtime' => OvertimeRequest::forTenant($tenantId)->where('status', 'approved')->exists(),
            'performance' => PerformanceReview::forTenant($tenantId)->whereNotNull('final_score')->exists(),
            'engagement' => SurveyResponse::forTenant($tenantId)->whereNotNull('employee_id')
                ->whereHas('question', fn ($q) => $q->where('type', 'rating'))->exists(),
            'leave_spike' => LeaveRequest::forTenant($tenantId)->exists(),
            'manager_change' => DB::table('audit_logs')->where('tenant_id', $tenantId)
                ->where('auditable_type', Employee::class)->exists(),
            'no_promotion' => EmployeeCareerHistory::forTenant($tenantId)->where('movement_type', 'promotion')->exists(),
        ];
    }

    /**
     * Shape one employee's scored row for the list.
     *
     * @return array<string, mixed>
     */
    private function shape(Employee $employee, AttritionSetting $settings): array
    {
        $result = $this->scorer->score($employee, $settings);

        return [
            'id' => $employee->id,
            // Employees are addressed in the URL by their opaque key.
            'route_key' => $employee->public_id,
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'department' => $employee->department?->name,
            'branch' => $employee->branch?->name,
            'position' => $employee->position?->name,
            'initials' => $this->initials($employee->full_name),
            'avatar_color' => $this->avatarColor($employee->full_name),
            'score' => $result['score'],
            'category' => $result['category'],
            'coverage' => $result['coverage'],
            'top_factors' => $result['top_factors'],
        ];
    }

    /**
     * Apply the category + search filters to the scored collection.
     *
     * @param  Collection<int, array<string, mixed>>  $scored
     * @return Collection<int, array<string, mixed>>
     */
    private function filterRows(Collection $scored, Request $request): Collection
    {
        $category = $request->query('category');
        $search = strtolower(trim((string) $request->query('search', '')));

        return $scored
            ->when(
                in_array($category, ['low', 'medium', 'high'], true),
                fn (Collection $c): Collection => $c->where('category', $category)
            )
            ->when(
                $search !== '',
                fn (Collection $c): Collection => $c->filter(fn (array $row): bool => str_contains(strtolower((string) $row['name']), $search)
                    || str_contains(strtolower((string) $row['employee_number']), $search))
            );
    }

    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper($first.$second) ?: '?';
    }

    private function avatarColor(string $name): string
    {
        return self::AVATAR_PALETTE[crc32($name) % count(self::AVATAR_PALETTE)];
    }
}
