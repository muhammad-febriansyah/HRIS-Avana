<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HelpdeskController extends Controller
{
    /**
     * Roles that may always manage the helpdesk within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Allowed ticket category enum values.
     *
     * @var array<int, string>
     */
    private const CATEGORIES = ['it', 'hr', 'payroll', 'facility', 'other'];

    /**
     * Allowed ticket priority enum values, in ascending order.
     *
     * @var array<int, string>
     */
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * Allowed ticket status enum values, in workflow order.
     *
     * @var array<int, string>
     */
    private const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    /**
     * Display the helpdesk ticket list together with KPI counters.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $tickets = Ticket::forTenant($tenantId)
            ->with(['requester:id,full_name,employee_number', 'assignee:id,name'])
            ->withCount('replies')
            ->latest('id')
            ->get()
            ->map(fn (Ticket $ticket): array => $this->transformTicket($ticket));

        return Inertia::render('avana/helpdesk/index', [
            'tickets' => $tickets,
            'employees' => $this->employeeOptions($tenantId),
            'users' => $this->userOptions($tenantId),
            'categories' => $this->categoryOptions(),
            'priorities' => $this->priorityOptions(),
            'statuses' => $this->statusOptions(),
            'kpis' => [
                'open' => $tickets->where('status', 'open')->count(),
                'in_progress' => $tickets->where('status', 'in_progress')->count(),
                'resolved' => $tickets->where('status', 'resolved')->count(),
                'closed' => $tickets->where('status', 'closed')->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/helpdesk/create', [
            'employees' => $this->employeeOptions($tenantId),
            'users' => $this->userOptions($tenantId),
            'categories' => $this->categoryOptions(),
            'priorities' => $this->priorityOptions(),
        ]);
    }

    /**
     * Show the form for editing an existing ticket, including its reply thread.
     */
    public function edit(Request $request, Ticket $ticket): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $ticket);

        $ticket->load(['replies.user:id,name', 'requester:id,full_name', 'assignee:id,name']);

        return Inertia::render('avana/helpdesk/edit', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'requester_id' => $ticket->requester_id,
                'requester' => $ticket->requester?->full_name,
                'category' => $ticket->category,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'assignee_id' => $ticket->assignee_id,
                'assignee' => $ticket->assignee?->name,
                'resolved_at' => $ticket->resolved_at?->toDateTimeString(),
                'created_at' => $ticket->created_at?->toDateTimeString(),
                'replies' => $ticket->replies->map(fn ($reply): array => [
                    'id' => $reply->id,
                    'message' => $reply->message,
                    'user' => $reply->user?->name,
                    'created_at' => $reply->created_at?->toDateTimeString(),
                ])->all(),
            ],
            'employees' => $this->employeeOptions($request->user()->tenant_id),
            'users' => $this->userOptions($request->user()->tenant_id),
            'categories' => $this->categoryOptions(),
            'priorities' => $this->priorityOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Persist a new ticket under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateTicket($request, $tenantId);

        Ticket::create([
            ...$data,
            'tenant_id' => $tenantId,
            'ticket_no' => $this->generateTicketNo($tenantId),
            'status' => 'open',
        ]);

        return redirect()->route('avana.helpdesk')
            ->with('success', 'Tiket berhasil dibuat');
    }

    /**
     * Update an existing ticket.
     */
    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $ticket);

        $data = $this->validateTicket($request, $request->user()->tenant_id);

        $ticket->update($data);

        return redirect()->route('avana.helpdesk')
            ->with('success', 'Tiket berhasil diperbarui');
    }

    /**
     * Delete a ticket.
     */
    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $ticket);

        $ticket->delete();

        return back()->with('success', 'Tiket dihapus');
    }

    /**
     * Assign a tenant user to handle the ticket.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $ticket);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $ticket->update(['assignee_id' => $data['assignee_id'] ?? null]);

        return back()->with('success', 'Penanggung jawab tiket diperbarui');
    }

    /**
     * Move a ticket to a different workflow status.
     */
    public function changeStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $ticket);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $ticket->update([
            'status' => $data['status'],
            'resolved_at' => $data['status'] === 'resolved' ? now() : $ticket->resolved_at,
        ]);

        return back()->with('success', 'Status tiket diperbarui');
    }

    /**
     * Add a reply to the ticket thread.
     */
    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $ticket);

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket->replies()->create([
            'tenant_id' => $ticket->tenant_id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        return back()->with('success', 'Balasan berhasil ditambahkan');
    }

    /**
     * Validate the create/update payload for a ticket.
     *
     * @return array<string, mixed>
     */
    private function validateTicket(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'requester_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ]);
    }

    /**
     * Generate the next sequential, tenant-scoped ticket number (e.g. TIK-0001).
     */
    private function generateTicketNo(int $tenantId): string
    {
        $last = Ticket::forTenant($tenantId)
            ->orderByDesc('id')
            ->value('ticket_no');

        $next = $last === null ? 1 : ((int) Str::afterLast($last, '-')) + 1;

        return 'TIK-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the row shape consumed by the tickets table.
     *
     * @return array<string, mixed>
     */
    private function transformTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'requester_id' => $ticket->requester_id,
            'requester' => $ticket->requester?->full_name,
            'requester_number' => $ticket->requester?->employee_number,
            'category' => $ticket->category,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'assignee_id' => $ticket->assignee_id,
            'assignee' => $ticket->assignee?->name,
            'resolved_at' => $ticket->resolved_at?->toDateTimeString(),
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'replies_count' => $ticket->replies_count,
        ];
    }

    /**
     * Build the tenant's selectable requester (employee) options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * Build the tenant's selectable assignee (user) options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function userOptions(int $tenantId): array
    {
        return User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of ticket categories.
     *
     * @return array<int, array<string, string>>
     */
    private function categoryOptions(): array
    {
        $labels = [
            'it' => 'IT',
            'hr' => 'HR',
            'payroll' => 'Payroll',
            'facility' => 'Fasilitas',
            'other' => 'Lainnya',
        ];

        return collect(self::CATEGORIES)
            ->map(fn (string $category): array => [
                'value' => $category,
                'label' => $labels[$category],
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of ticket priorities.
     *
     * @return array<int, array<string, string>>
     */
    private function priorityOptions(): array
    {
        $labels = [
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
        ];

        return collect(self::PRIORITIES)
            ->map(fn (string $priority): array => [
                'value' => $priority,
                'label' => $labels[$priority],
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of ticket statuses.
     *
     * @return array<int, array<string, string>>
     */
    private function statusOptions(): array
    {
        $labels = [
            'open' => 'Terbuka',
            'in_progress' => 'Diproses',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
        ];

        return collect(self::STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $labels[$status],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Ticket $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}
