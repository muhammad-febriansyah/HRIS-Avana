<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Support\AvanaNav;
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
                'tenant' => fn () => $user?->tenant?->only('id', 'name', 'company_name'),
            ],
            'nav' => fn () => AvanaNav::forUser($user, $this->isPlatformScope($request, $user)),
            'superAdminView' => fn (): array => $this->superAdminView($request, $user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
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
