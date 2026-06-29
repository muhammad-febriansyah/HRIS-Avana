<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Sortable columns whitelist for the index DataTable.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'created_at'];

    /**
     * Deterministic avatar background palette.
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display a server-side paginated, filterable list of tenant users.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $request->user()->tenant_id;

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'roles:id,name,code',
                'branchAccesses:id,user_id,branch_id',
                'dataScopes:id,user_id,scope_type,scope_value',
            ])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/pengguna', [
            'users' => [
                'data' => collect($users->items())
                    ->map(fn (User $user): array => $this->transformUser($user))
                    ->all(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ],
            'roles' => $this->assignableRoles($tenantId),
            'branches' => Branch::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'filters' => $request->only(['search', 'status', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Persist a new user under the authenticated user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'data_scope' => ['nullable', 'in:company,branch,team,own'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', $this->branchOwnedByTenant($request)],
        ]);

        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'status' => $validated['status'],
        ]);

        $user->roles()->sync($validated['role_ids'] ?? []);
        $this->syncDataScope($user, $validated['data_scope'] ?? null);
        $this->syncBranchAccess($user, $validated['branch_ids'] ?? []);

        return redirect()->route('avana.pengguna')
            ->with('success', 'Pengguna berhasil ditambahkan');
    }

    /**
     * Update an existing user; the password is only changed when provided.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $user);
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'data_scope' => ['nullable', 'in:company,branch,team,own'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', $this->branchOwnedByTenant($request)],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        $user->roles()->sync($validated['role_ids'] ?? []);
        $this->syncDataScope($user, $validated['data_scope'] ?? null);
        $this->syncBranchAccess($user, $validated['branch_ids'] ?? []);

        return redirect()->route('avana.pengguna')
            ->with('success', 'Pengguna berhasil diperbarui');
    }

    /**
     * Delete a user; the authenticated account cannot delete itself.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $user);
        $this->authorize('delete', $user);

        abort_if($user->id === $request->user()->id, 403, 'Tidak dapat menghapus akun sendiri.');

        $user->delete();

        return back()->with('success', 'Pengguna dihapus');
    }

    /**
     * Toggle a user between active and inactive; the account cannot disable itself.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $user);
        $this->authorize('update', $user);

        abort_if($user->id === $request->user()->id, 403, 'Tidak dapat menonaktifkan akun sendiri.');

        $user->update([
            'status' => $user->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Status pengguna diperbarui');
    }

    /**
     * Build the row shape consumed by the Pengguna DataTable.
     *
     * @return array<string, mixed>
     */
    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'roles' => $user->roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
            ])->values()->all(),
            'initials' => $this->initials($user->name),
            'avatar_color' => $this->avatarColor($user->name),
            'data_scope' => $user->dataScopes->first()?->scope_type ?? 'company',
            'branch_ids' => $user->branchAccesses
                ->pluck('branch_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }

    /**
     * Replace the user's data scope with a single row of the given type.
     */
    private function syncDataScope(User $user, ?string $scopeType): void
    {
        $user->dataScopes()->delete();
        $user->dataScopes()->create([
            'scope_type' => $scopeType ?? 'company',
            'scope_value' => null,
        ]);
    }

    /**
     * Replace the user's branch access rows with view access to each branch id.
     *
     * @param  array<int, int>  $branchIds
     */
    private function syncBranchAccess(User $user, array $branchIds): void
    {
        $user->branchAccesses()->delete();

        foreach (array_unique($branchIds) as $branchId) {
            $user->branchAccesses()->create([
                'branch_id' => $branchId,
                'access_type' => 'view',
            ]);
        }
    }

    /**
     * Validation rule ensuring a branch id belongs to the acting user's tenant.
     */
    private function branchOwnedByTenant(Request $request): Exists
    {
        return Rule::exists('branches', 'id')
            ->where('tenant_id', $request->user()->tenant_id);
    }

    /**
     * Assignable roles for a tenant: tenant-owned plus global, minus super_admin.
     *
     * @return array<int, Role>
     */
    private function assignableRoles(?int $tenantId): array
    {
        return Role::query()
            ->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('code', '!=', 'super_admin')
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->all();
    }

    /**
     * Build up to two uppercase initials from the user's name.
     */
    private function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];

        $initials = collect($words)
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Pick a deterministic avatar color derived from the user's name.
     */
    private function avatarColor(?string $name): string
    {
        $index = crc32((string) $name) % count(self::AVATAR_PALETTE);

        return self::AVATAR_PALETTE[$index];
    }

    /**
     * Abort with 404 when the user does not belong to the acting user's tenant.
     */
    private function ensureTenantOwnership(Request $request, User $user): void
    {
        abort_if((int) $user->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
