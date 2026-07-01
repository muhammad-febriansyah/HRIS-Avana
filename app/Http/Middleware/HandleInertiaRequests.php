<?php

namespace App\Http\Middleware;

use App\Models\WebsiteSetting;
use App\Support\AvanaNav;
use Illuminate\Http\Request;
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
                'roles' => fn () => $user?->roles()->pluck('code')->all() ?? [],
                'isSuperAdmin' => fn () => (bool) $user?->roles()->where('code', 'super_admin')->exists(),
                'tenant' => fn () => $user?->tenant?->only('id', 'name', 'company_name'),
            ],
            'nav' => fn () => AvanaNav::forUser($user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
