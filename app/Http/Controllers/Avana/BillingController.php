<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform (Super Admin) billing: client subscriptions + invoices. Super Admin
 * issues invoices to each tenant for their subscription and tracks payment.
 */
class BillingController extends Controller
{
    /**
     * Allowed invoice statuses.
     *
     * @var array<int, string>
     */
    private const INVOICE_STATUSES = ['unpaid', 'paid', 'overdue', 'cancelled'];

    /**
     * Allowed subscription statuses.
     *
     * @var array<int, string>
     */
    private const SUBSCRIPTION_STATUSES = ['active', 'trial', 'past_due', 'cancelled'];

    /**
     * Allowed billing cycles.
     *
     * @var array<int, string>
     */
    private const BILLING_CYCLES = ['monthly', 'quarterly', 'yearly'];

    /**
     * Render the billing dashboard: KPIs, invoices, and subscriptions.
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $invoices = Invoice::query()
            ->with(['tenant:id,name', 'items'])
            ->latest('id')
            ->get()
            ->map(fn (Invoice $invoice): array => $this->transformInvoice($invoice));

        $subscriptions = Subscription::query()
            ->with(['tenant:id,name', 'package:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'tenant' => $subscription->tenant?->name,
                'package' => $subscription->package?->name,
                'package_id' => $subscription->package_id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'price' => (float) $subscription->price,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
            ]);

        $outstanding = $invoices->whereIn('status', ['unpaid', 'overdue'])->sum('total');
        $paidThisMonth = $invoices
            ->where('status', 'paid')
            ->filter(fn (array $invoice): bool => $invoice['paid_at'] !== null
                && str_starts_with((string) $invoice['paid_at'], now()->format('Y-m')))
            ->sum('total');

        return Inertia::render('avana/billing/index', [
            'invoices' => $invoices->values()->all(),
            'subscriptions' => $subscriptions->values()->all(),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Tenant $tenant): array => ['id' => $tenant->id, 'name' => $tenant->name])->all(),
            'packages' => Package::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'price', 'billing_cycle'])
                ->map(fn (Package $package): array => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => (float) $package->price,
                    'billing_cycle' => $package->billing_cycle,
                ])->all(),
            'kpis' => [
                'outstanding' => round($outstanding, 2),
                'paid_this_month' => round($paidThisMonth, 2),
                'overdue_count' => $invoices->where('status', 'overdue')->count()
                    + $invoices->where('status', 'unpaid')
                        ->filter(fn (array $invoice): bool => $invoice['due_date'] !== null
                            && $invoice['due_date'] < now()->toDateString())
                        ->count(),
                'active_subscriptions' => $subscriptions->where('status', 'active')->count(),
                'mrr' => round($subscriptions->where('status', 'active')->sum('price'), 2),
            ],
            'subscriptionStatuses' => self::SUBSCRIPTION_STATUSES,
            'billingCycles' => self::BILLING_CYCLES,
        ]);
    }

    /**
     * Create a subscription assigning a package to a tenant.
     */
    public function storeSubscription(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $this->validateSubscription($request);

        Subscription::create($data);

        return back()->with('success', 'Langganan dibuat');
    }

    /**
     * Update an existing subscription.
     */
    public function updateSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $subscription->update($this->validateSubscription($request));

        return back()->with('success', 'Langganan diperbarui');
    }

    /**
     * Persist a manually composed invoice with its line items.
     */
    public function storeInvoice(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = collect($data['items'])->sum(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']);
        $tax = (float) ($data['tax'] ?? 0);

        $invoice = Invoice::create([
            'tenant_id' => $data['tenant_id'],
            'subscription_id' => $data['subscription_id'] ?? null,
            'invoice_number' => $this->nextInvoiceNumber(),
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'status' => 'unpaid',
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($data['items'] as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => (float) $item['quantity'] * (float) $item['unit_price'],
            ]);
        }

        return redirect()->route('avana.billing')->with('success', "Invoice {$invoice->invoice_number} dibuat");
    }

    /**
     * Auto-generate an invoice for a subscription's current billing period.
     */
    public function generateInvoice(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $subscription->loadMissing(['tenant', 'package']);

        $issue = now();
        $due = now()->addDays(14);
        $price = (float) $subscription->price;
        $label = 'Langganan '.($subscription->package?->name ?? 'AvanaHR').' ('.$issue->translatedFormat('F Y').')';

        $invoice = Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => $this->nextInvoiceNumber(),
            'period_start' => $issue->copy()->startOfMonth()->toDateString(),
            'period_end' => $issue->copy()->endOfMonth()->toDateString(),
            'issue_date' => $issue->toDateString(),
            'due_date' => $due->toDateString(),
            'subtotal' => $price,
            'tax' => 0,
            'total' => $price,
            'status' => 'unpaid',
        ]);

        $invoice->items()->create([
            'description' => $label,
            'quantity' => 1,
            'unit_price' => $price,
            'amount' => $price,
        ]);

        return redirect()->route('avana.billing')->with('success', "Invoice {$invoice->invoice_number} dibuat");
    }

    /**
     * Mark an invoice as paid.
     */
    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('success', 'Invoice ditandai lunas');
    }

    /**
     * Cancel an invoice.
     */
    public function cancelInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $invoice->update(['status' => 'cancelled']);

        return back()->with('success', 'Invoice dibatalkan');
    }

    /**
     * Delete an invoice and its items.
     */
    public function destroyInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $invoice->delete();

        return back()->with('success', 'Invoice dihapus');
    }

    /**
     * Render a printable invoice sheet (browser print-to-PDF).
     */
    public function printInvoice(Request $request, Invoice $invoice): \Illuminate\Http\Response
    {
        $this->ensureSuperAdmin($request);

        $invoice->load(['tenant', 'items']);

        $statusLabels = [
            'unpaid' => 'Belum Bayar',
            'paid' => 'Lunas',
            'overdue' => 'Jatuh Tempo',
            'cancelled' => 'Dibatalkan',
        ];

        $periodLabel = $invoice->period_start && $invoice->period_end
            ? $invoice->period_start->format('d M Y').' – '.$invoice->period_end->format('d M Y')
            : null;

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => [
                ...$this->transformInvoice($invoice),
                'tenant_company' => $invoice->tenant?->company_name ?? $invoice->tenant?->name,
                'notes' => $invoice->notes,
            ],
            'statusLabel' => $statusLabels[$invoice->status] ?? $invoice->status,
            'issueDate' => $invoice->issue_date?->format('d M Y') ?? '-',
            'dueDate' => $invoice->due_date?->format('d M Y') ?? '-',
            'periodLabel' => $periodLabel,
        ])->setPaper('a4');

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    /**
     * Build the invoice payload consumed by the index/print screens.
     *
     * @return array<string, mixed>
     */
    private function transformInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'tenant_id' => $invoice->tenant_id,
            'tenant' => $invoice->tenant?->name,
            'period_start' => $invoice->period_start?->toDateString(),
            'period_end' => $invoice->period_end?->toDateString(),
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'subtotal' => (float) $invoice->subtotal,
            'tax' => (float) $invoice->tax,
            'total' => (float) $invoice->total,
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at?->toDateTimeString(),
            'items' => $invoice->items->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'amount' => (float) $item->amount,
            ])->all(),
        ];
    }

    /**
     * Validate the subscription create/update payload.
     *
     * @return array<string, mixed>
     */
    private function validateSubscription(Request $request): array
    {
        return $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'status' => ['required', Rule::in(self::SUBSCRIPTION_STATUSES)],
            'billing_cycle' => ['required', Rule::in(self::BILLING_CYCLES)],
            'price' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);
    }

    /**
     * Generate the next sequential invoice number for the current month.
     */
    private function nextInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';

        $last = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
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
