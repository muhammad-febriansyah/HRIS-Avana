<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Support\Access;
use App\Support\AvanaNav;
use App\Support\SubscriptionStatus;
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
                'tenant' => fn () => $this->tenantBranding($request, $user),
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

                return TenantTheme::resolve($this->scopedTenant($request, $user)?->theme);
            },
            'notifications' => fn (): array => $this->notifications($user),
            // Namespaced, not `subscription`: a page prop of the same name wins
            // over a shared one, and the client detail screen ships its own
            // `subscription` — which left the chrome's banner reading its
            // fields off the wrong shape ("berakhir undefined hari lagi").
            'subscriptionNotice' => fn (): ?array => $this->subscriptionNotice($request, $user),
            'superAdminView' => fn (): array => $this->superAdminView($request, $user),
            'flash' => [
                'success' => fn () => $this->session($request, 'success'),
                'error' => fn () => $this->session($request, 'error'),
                // An action that worked but has a caveat worth reading — a
                // payroll run that fell back to TK/0 for somebody, say.
                'warning' => fn () => $this->session($request, 'warning'),
                // One-time hand-off of a freshly issued tenant admin password.
                'credentials' => fn () => $this->session($request, 'credentials'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The tenant whose brand the chrome wears: name, logo, and the browser tab
     * suffix. A non-platform tenant is white-labelled with their own brand
     * instead of AvanaHR.
     *
     * Scoped the same way as `theme` and `nav`: null on the platform side, so a
     * super admin sees AvanaHR rather than whichever tenant their own account
     * happens to sit in, and the impersonated tenant while viewing as one.
     *
     * @return array{id: int, name: string, company_name: string|null, logo_url: string|null}|null
     */
    private function tenantBranding(Request $request, ?User $user): ?array
    {
        if ($user === null || $this->isPlatformScope($request, $user)) {
            return null;
        }

        // Only a super admin may be viewing as someone else; honouring anyone
        // else's `view_tenant_id` would put another tenant's name and logo on
        // their chrome, so {@see scopedTenant()} ignores it for them.
        $tenant = $this->scopedTenant($request, $user);

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
     * The subscription-expiry banner for the tenant chrome: null unless the
     * client's subscription ends within {@see SubscriptionStatus::WARNING_DAYS}
     * (or has already ended). Only warns — access is never blocked here.
     *
     * Platform scope returns null: a super admin's own account sits in a tenant,
     * and their banner would be about that tenant rather than the client they are
     * looking at. While viewing as a tenant they see that tenant's banner.
     *
     * Shown only to the accounts that can act on it — the tenant's Admin Tenant /
     * HR — so an ESS employee is not nagged about their employer's billing.
     *
     * @return array{end_date: string, end_date_label: string, days_left: int, level: string, package: string|null}|null
     */
    private function subscriptionNotice(Request $request, ?User $user): ?array
    {
        if ($user === null || $this->isPlatformScope($request, $user)) {
            return null;
        }

        $canSee = $user->roles()
            ->whereIn('code', ['admin_tenant_hr', 'super_admin'])
            ->exists();

        if (! $canSee) {
            return null;
        }

        $tenant = $this->scopedTenant($request, $user);

        if ($tenant === null) {
            return null;
        }

        $notice = SubscriptionStatus::forTenant($tenant);

        return $notice !== null && $notice['level'] !== 'ok' ? $notice : null;
    }

    /**
     * The tenant whose data the chrome speaks for: the user's own, or the one a
     * super admin is currently viewing as. A `view_tenant_id` in anyone else's
     * session is tampering and is ignored.
     */
    private function scopedTenant(Request $request, User $user): ?Tenant
    {
        $viewTenantId = $user->roles()->where('code', 'super_admin')->exists()
            ? (int) ($this->session($request, 'view_tenant_id') ?? 0)
            : 0;

        return $viewTenantId > 0 ? Tenant::find($viewTenantId) : $user->tenant;
    }

    /**
     * Read a session value, tolerating a request that never went through the
     * session middleware.
     *
     * The error page shares these props too, and a 404 is rendered outside the
     * middleware stack — so there is no session store on the request at all.
     */
    private function session(Request $request, string $key): mixed
    {
        return $request->hasSession() ? $request->session()->get($key) : null;
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

        return (int) ($this->session($request, 'view_tenant_id') ?? 0) === 0;
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
            'view_tenant_id' => (string) ($this->session($request, 'view_tenant_id') ?? ''),
            'tenants' => $isSuper
                ? Tenant::orderBy('name')->get(['id', 'name'])->map(fn (Tenant $t): array => ['id' => $t->id, 'name' => $t->name])->all()
                : [],
        ];
    }
}
