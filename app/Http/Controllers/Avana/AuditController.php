<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    /**
     * Roles that may always view the audit trail.
     *
     * @var array<int, string>
     */
    private const VIEWER_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Valid audit actions, used to validate the action filter.
     *
     * @var array<int, string>
     */
    private const ACTIONS = ['created', 'updated', 'deleted'];

    /**
     * Valid activity events, used to validate the event filter on the
     * "Aktivitas" tab.
     *
     * @var array<int, string>
     */
    private const EVENTS = ['login', 'logout', 'login_failed', 'page_view', 'data_created', 'data_updated', 'data_deleted'];

    /**
     * Page-size choices offered to the user.
     *
     * @var array<int, int>
     */
    private const PER_PAGE = [15, 25, 50, 100];

    /**
     * Attribute keys, in priority order, used to derive a human-readable label
     * for an audited record.
     *
     * @var array<int, string>
     */
    private const LABEL_KEYS = ['full_name', 'name', 'company_name', 'title', 'reason', 'code', 'employee_number', 'email'];

    /**
     * Render the audit trail: a "Perubahan Data" tab (data changes) and an
     * "Aktivitas" tab (login/logout/page-view/data-change activity), each
     * scoped to every tenant for a super admin and to just their own for a
     * tenant user.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanViewAudit($request);

        /** @var User $user */
        $user = $request->user();
        $tab = $request->query('tab') === 'activity' ? 'activity' : 'changes';
        $tenantFilter = $user->isSuperAdmin() && $request->filled('tenant_id')
            ? (int) $request->query('tenant_id')
            : null;

        $perPage = in_array((int) $request->query('per_page'), self::PER_PAGE, true)
            ? (int) $request->query('per_page')
            : 15;

        return Inertia::render('avana/audit/index', [
            'tab' => $tab,
            'logs' => fn () => $tab === 'changes' ? $this->changesData($request, $user, $tenantFilter, $perPage) : null,
            'activity' => fn () => $tab === 'activity' ? $this->activityData($request, $user, $tenantFilter, $perPage) : null,
            'tenants' => fn () => $user->isSuperAdmin() ? Tenant::query()->orderBy('name')->get(['id', 'name']) : [],
            'filters' => [
                'search' => trim((string) $request->query('search', '')) ?: null,
                'action' => in_array($request->query('action'), self::ACTIONS, true) ? $request->query('action') : null,
                'event' => in_array($request->query('event'), self::EVENTS, true) ? $request->query('event') : null,
                'tenant_id' => $tenantFilter,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    /**
     * Data-change tab payload: the existing paginated `audit_logs` view.
     *
     * @return array<string, mixed>
     */
    private function changesData(Request $request, User $user, ?int $tenantFilter, int $perPage): array
    {
        $search = trim((string) $request->query('search', '')) ?: null;
        $action = in_array($request->query('action'), self::ACTIONS, true)
            ? $request->query('action')
            : null;

        $logs = AuditLog::query()
            ->forViewer($user, $tenantFilter)
            ->with('user:id,name')
            ->when($search !== null, function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('auditable_type', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%");
                });
            })
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $labels = $this->resolveLabels($logs->getCollection());

        return [
            'data' => $logs->getCollection()
                ->map(fn (AuditLog $log): array => $this->transform($log, $labels))
                ->all(),
            'meta' => $this->meta($logs),
        ];
    }

    /**
     * Activity tab payload: logins, page visits, and mirrored data changes
     * from `user_activity_logs`.
     *
     * @return array<string, mixed>
     */
    private function activityData(Request $request, User $user, ?int $tenantFilter, int $perPage): array
    {
        $search = trim((string) $request->query('search', '')) ?: null;
        $event = in_array($request->query('event'), self::EVENTS, true)
            ? $request->query('event')
            : null;

        $logs = UserActivityLog::query()
            ->forViewer($user, $tenantFilter)
            ->with('user:id,name')
            ->when($search !== null, function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%");
                });
            })
            ->when($event !== null, fn ($query) => $query->where('event', $event))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'data' => $logs->getCollection()
                ->map(fn (UserActivityLog $log): array => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'description' => $log->description,
                    'user' => $log->user?->name,
                    'ip_address' => $log->ip_address,
                    'path' => $log->path,
                    'created_at' => $log->created_at?->format('d M Y, H:i'),
                ])
                ->all(),
            'meta' => $this->meta($logs),
        ];
    }

    /**
     * Shape a paginator's meta block, shared by both tabs.
     *
     * @return array<string, int|null>
     */
    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Shape a single audit row for the front-end table.
     *
     * @param  array<string, array<int|string, string>>  $labels
     * @return array<string, mixed>
     */
    private function transform(AuditLog $log, array $labels): array
    {
        $changed = array_keys($log->new_values ?? $log->old_values ?? []);

        return [
            'id' => $log->id,
            'action' => $log->action,
            'auditable_type' => class_basename((string) $log->auditable_type),
            'auditable_id' => $log->auditable_id,
            'label' => $labels[$log->auditable_type][$log->auditable_id] ?? '#'.$log->auditable_id,
            'user' => $log->user?->name,
            'ip_address' => $log->ip_address,
            'changes' => $changed,
            'created_at' => $log->created_at?->format('d M Y, H:i'),
        ];
    }

    /**
     * Build a `[class => [id => label]]` map for the audited records on the
     * current page, batching one query per model type (including trashed rows).
     *
     * @param  Collection<int, AuditLog>  $logs
     * @return array<string, array<int|string, string>>
     */
    private function resolveLabels(Collection $logs): array
    {
        $map = [];

        foreach ($logs->groupBy('auditable_type') as $type => $rows) {
            if (! is_string($type) || ! class_exists($type) || ! is_subclass_of($type, Model::class)) {
                continue;
            }

            /** @var class-string<Model> $type */
            $query = $type::query();

            if (in_array(SoftDeletes::class, class_uses_recursive($type), true)) {
                $query->withTrashed();
            }

            $instance = new $type;
            $ids = $rows->pluck('auditable_id')->filter()->unique()->all();

            $map[$type] = $query->whereIn($instance->getKeyName(), $ids)
                ->get()
                ->mapWithKeys(fn (Model $model): array => [
                    $model->getKey() => $this->modelLabel($model),
                ])
                ->all();
        }

        return $map;
    }

    /**
     * Pick the first meaningful identifier attribute on the model, else "#id".
     */
    private function modelLabel(Model $model): string
    {
        foreach (self::LABEL_KEYS as $key) {
            $value = $model->getAttribute($key);

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '#'.$model->getKey();
    }

    /**
     * Abort with 403 unless the user may view the audit trail: a privileged
     * role or the explicit `audit.view` permission.
     */
    private function ensureCanViewAudit(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        if ($user->roles->whereIn('code', self::VIEWER_ROLES)->isNotEmpty()) {
            return;
        }

        abort_unless($user->hasPermissionTo('audit.view'), 403);
    }
}
