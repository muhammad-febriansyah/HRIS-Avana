<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OnboardingStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Mulai" checklist a tenant lands on until they've picked a package and
 * saved their company profile — see {@see EnsureOnboardingComplete},
 * which is what redirects everything else here while either is missing.
 * Reachable by every role, same as the subscription lock notice: an
 * employee can't act on it, but they need to see why the app is gated.
 */
class OnboardingSetupController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = Tenant::query()->with('company')->findOrFail($user->tenant_id);

        $status = OnboardingStatus::resolve($tenant);

        return Inertia::render('avana/mulai/index', [
            'status' => $status,
            'tenantName' => $tenant->company_name ?? $tenant->name,
            'canManage' => $user->isSuperAdmin() || $user->roles->pluck('code')->contains('admin_tenant_hr'),
        ]);
    }
}
