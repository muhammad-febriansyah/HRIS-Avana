<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiTokenOrder;
use App\Models\AiTokenPack;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin catalogue of AI token packs (name / token amount / Rupiah price)
 * and a read-only overview of tenant purchases. Tenants top up their permanent
 * wallet by buying these packs through Pakasir.
 */
class AiTokenPackController extends Controller
{
    /**
     * Show the pack catalogue and the recent orders overview.
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $packs = AiTokenPack::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AiTokenPack $pack): array => [
                'id' => $pack->id,
                'name' => $pack->name,
                'token_amount' => $pack->token_amount,
                'price' => $pack->price,
                'description' => $pack->description,
                'is_active' => $pack->is_active,
                'sort_order' => $pack->sort_order,
            ]);

        $orders = AiTokenOrder::query()
            ->with('tenant:id,name')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AiTokenOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'tenant' => $order->tenant?->name,
                'pack_name' => $order->pack_name,
                'token_amount' => $order->token_amount,
                'amount' => $order->amount,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'completed_at' => $order->completed_at?->toDateTimeString(),
                'created_at' => $order->created_at?->toDateTimeString(),
            ]);

        $completed = AiTokenOrder::query()->where('status', AiTokenOrder::STATUS_COMPLETED);

        return Inertia::render('avana/token-packs/index', [
            'packs' => $packs->values()->all(),
            'orders' => $orders->values()->all(),
            'kpis' => [
                'revenue' => (int) (clone $completed)->sum('amount'),
                'tokens_sold' => (int) (clone $completed)->sum('token_amount'),
                'pending_count' => AiTokenOrder::query()->where('status', AiTokenOrder::STATUS_PENDING)->count(),
                'completed_count' => (clone $completed)->count(),
            ],
        ]);
    }

    /**
     * Create a new token pack.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        AiTokenPack::create($this->validated($request));

        return back()->with('success', 'Paket token dibuat');
    }

    /**
     * Update an existing token pack.
     */
    public function update(Request $request, AiTokenPack $pack): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $pack->update($this->validated($request));

        return back()->with('success', 'Paket token diperbarui');
    }

    /**
     * Archive a token pack (soft delete keeps order history intact).
     */
    public function destroy(Request $request, AiTokenPack $pack): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $pack->delete();

        return back()->with('success', 'Paket token dihapus');
    }

    /**
     * Validate the pack create/update payload.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'token_amount' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * Abort with 403 unless the acting user is a platform super admin.
     */
    private function ensureSuperAdmin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->roles()->where('code', 'super_admin')->exists(), 403);
    }
}
