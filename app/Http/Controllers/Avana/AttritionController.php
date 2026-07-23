<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttritionScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function __construct(private AttritionScorer $scorer) {}

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) ($request->user()->tenant_id ?? 0);

        $query = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->with(['department:id,name', 'branch:id,name', 'position:id,name']);

        $this->applyBranchScope($query, $request->user());

        $scored = $query->get()->map(fn (Employee $employee): array => $this->shape($employee));

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
                'join_date' => $employee->join_date?->format('d M Y'),
                'initials' => $this->initials($employee->full_name),
                'avatar_color' => $this->avatarColor($employee->full_name),
            ],
            'result' => $result,
        ]);
    }

    /**
     * Shape one employee's scored row for the list.
     *
     * @return array<string, mixed>
     */
    private function shape(Employee $employee): array
    {
        $result = $this->scorer->score($employee);

        return [
            'id' => $employee->id,
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
