<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Support\Access;
use App\Support\AvanaNav;
use App\Support\TenantTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'website' => fn (): array => WebsiteSetting::cached()->toBrandingArray(),
            'auth' => [
                'user' => $user,
                'avatar' => fn () => $this->resolveAvatarUrl($user),
                'roles' => fn () => $user?->roles()->pluck('code')->all() ?? [],
                'isSuperAdmin' => fn () => (bool) $user?->roles()->where('code', 'super_admin')->exists(),
                'tenant' => fn () => $this->tenantBranding($user),
                // Effective {module}.{action} codes for action-level UI gating
                // (usePermission). A super admin resolves to every code; null
                // when enforcement is disabled so the UI gates nothing.
                'permissions' => fn (): ?array => Access::enforced()
                    ? ($user?->permissionCodes()->all() ?? [])
                    : null,
            ],
            'nav' => fn () => AvanaNav::forUser($user, $this->isPlatformScope($request, $user)),
            'theme' => function () use ($request, $user): array {
                if ($user === null) {
                    return TenantTheme::resolve(null);
                }

                if ($this->isPlatformScope($request, $user)) {
                    return TenantTheme::resolve(WebsiteSetting::cached()->theme);
                }

                $viewTenantId = (int) ($request->session()->get('view_tenant_id') ?? 0);
                $tenant = $viewTenantId > 0 ? Tenant::find($viewTenantId) : $user->tenant;

                return TenantTheme::resolve($tenant?->theme);
            },
            'notifications' => fn (): array => $this->notifications($user),
            'superAdminView' => fn (): array => $this->superAdminView($request, $user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // One-time hand-off of a freshly issued tenant admin password.
                'credentials' => fn () => $request->session()->get('credentials'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The user's tenant identity + branding for the sidebar. `logo_url` is the
     * tenant's own company logo, so a non-platform tenant is white-labelled
     * with their own brand instead of AvanaHR.
     *
     * @return array{id: int, name: string, company_name: string|null, logo_url: string|null}|null
     */
    private function tenantBranding(?User $user): ?array
    {
        $tenant = $user?->tenant;

        if ($tenant === null) {
            return null;
        }

        $logoPath = $tenant->company?->logo_path;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'company_name' => $tenant->company_name,
            'logo_url' => $logoPath !== null ? Storage::disk('public')->url($logoPath) : null,
        ];
    }

    /**
     * Resolve the header avatar URL. A user's own uploaded avatar wins;
     * otherwise fall back to the linked employee's photo (mobile ESS logins).
     */
    private function resolveAvatarUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $path = $user->avatar_path ?? $user->employee?->photo_path;

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Whether the nav should show the platform (super-admin) menu: a super admin
     * who is not currently impersonating a tenant via the topbar switcher.
     */
    private function isPlatformScope(Request $request, ?User $user): bool
    {
        if ($user === null || ! $user->roles()->where('code', 'super_admin')->exists()) {
            return false;
        }

        return (int) ($request->session()->get('view_tenant_id') ?? 0) === 0;
    }

    /**
     * The current user's recent notifications for the header bell. Scoped to
     * `user_id` alone — a user only ever belongs to one tenant, so this can
     * never surface another tenant's data.
     *
     * @return array{items: array<int, array{id: int, type: string|null, title: string, body: string|null, is_read: bool, created_at: string|null}>, unread: int}
     */
    private function notifications(?User $user): array
    {
        if ($user === null) {
            return ['items' => [], 'unread' => 0];
        }

        $items = Notification::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'type', 'title', 'body', 'read_at', 'created_at'])
            ->map(fn (Notification $n): array => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'is_read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'items' => $items,
            'unread' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    /**
     * The "view as tenant" state for the topbar switcher (super admin only).
     *
     * @return array{is_super: bool, view_tenant_id: string, tenants: array<int, array{id: int, name: string}>}
     */
    private function superAdminView(Request $request, ?User $user): array
    {
        $isSuper = $user !== null && $user->roles()->where('code', 'super_admin')->exists();

        return [
            'is_super' => $isSuper,
            'view_tenant_id' => (string) ($request->session()->get('view_tenant_id') ?? ''),
            'tenants' => $isSuper
                ? Tenant::orderBy('name')->get(['id', 'name'])->map(fn (Tenant $t): array => ['id' => $t->id, 'name' => $t->name])->all()
                : [],
        ];
    }
}
