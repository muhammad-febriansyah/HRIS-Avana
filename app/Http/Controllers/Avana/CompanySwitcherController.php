<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CompanySwitcherController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenantId = $request->integer('tenant_id');

        if ($tenantId === 0) {
            $request->session()->forget('active_tenant_id');

            return back()->with('success', 'Kembali ke perusahaan utama.');
        }

        $validated = $request->validate([
            'tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenant_memberships', 'tenant_id')
                    ->where('user_id', $user->id)
                    ->where('status', 'active'),
            ],
        ]);

        $tenant = Tenant::query()
            ->whereKey($validated['tenant_id'])
            ->whereIn('status', ['trial', 'active'])
            ->firstOrFail();

        abort_unless(
            TenantMembership::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->exists(),
            403,
        );

        $request->session()->put('active_tenant_id', $tenant->id);

        return back()->with('success', 'Beralih ke perusahaan: '.$tenant->name);
    }
}
