<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a ticket scoped to the given tenant, defaulting the requester to one
 * of the tenant's seeded employees.
 */
function makeTicket(int $tenantId, array $overrides = []): Ticket
{
    $requesterId = $overrides['requester_id']
        ?? Employee::forTenant($tenantId)->value('id');

    return Ticket::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'requester_id' => $requesterId,
    ], $overrides));
}

it('renders the helpdesk index with the expected props', function (): void {
    makeTicket($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.helpdesk'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/helpdesk/index', false)
            ->has('tickets.0', fn (Assert $row) => $row
                ->has('id')
                ->has('ticket_no')
                ->has('requester_id')
                ->has('requester')
                ->has('requester_number')
                ->has('category')
                ->has('subject')
                ->has('description')
                ->has('priority')
                ->has('status')
                ->has('assignee_id')
                ->has('assignee')
                ->has('resolved_at')
                ->has('created_at')
                ->has('replies_count'))
            ->has('employees')
            ->has('users')
            ->has('categories')
            ->has('priorities')
            ->has('statuses')
            ->has('kpis'));
});

it('only lists tickets that belong to the current tenant', function (): void {
    makeTicket($this->tenant->id);

    Ticket::factory()->create();

    $tenantTotal = Ticket::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.helpdesk'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', $tenantTotal));
});

it('creates a ticket with an auto-generated number scoped to the tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.helpdesk.store'), [
            'requester_id' => $employee->id,
            'category' => 'it',
            'subject' => 'Laptop rusak',
            'description' => 'Tidak bisa menyala sejak pagi',
            'priority' => 'high',
            'assignee_id' => $this->admin->id,
        ])
        ->assertRedirect(route('avana.helpdesk'))
        ->assertSessionHas('success');

    $ticket = Ticket::where('subject', 'Laptop rusak')->firstOrFail();

    expect($ticket->tenant_id)->toBe($this->tenant->id);
    expect($ticket->status)->toBe('open');
    expect($ticket->priority)->toBe('high');
    expect($ticket->ticket_no)->toStartWith('TIK-');
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.helpdesk.store'), [
            'requester_id' => 99999,
            'category' => 'invalid',
            'subject' => '',
            'description' => '',
            'priority' => 'invalid',
        ])
        ->assertSessionHasErrors(['requester_id', 'category', 'subject', 'description', 'priority']);
});

it('updates an existing ticket', function (): void {
    $ticket = makeTicket($this->tenant->id, ['subject' => 'Lama']);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.helpdesk.update', $ticket), [
            'requester_id' => $employee->id,
            'category' => 'payroll',
            'subject' => 'Baru',
            'description' => 'Deskripsi diperbarui',
            'priority' => 'urgent',
            'assignee_id' => null,
        ])
        ->assertRedirect(route('avana.helpdesk'))
        ->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->subject)->toBe('Baru');
    expect($ticket->category)->toBe('payroll');
    expect($ticket->priority)->toBe('urgent');
});

it('deletes a ticket', function (): void {
    $ticket = makeTicket($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.helpdesk.destroy', $ticket))
        ->assertSessionHas('success');

    expect(Ticket::find($ticket->id))->toBeNull();
});

it('assigns a user to a ticket', function (): void {
    $ticket = makeTicket($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.helpdesk.assign', $ticket), [
            'assignee_id' => $this->admin->id,
        ])
        ->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->assignee_id)->toBe($this->admin->id);
});

it('changes a ticket status and stamps resolved_at when resolved', function (): void {
    $ticket = makeTicket($this->tenant->id, ['status' => 'open']);

    actingAs($this->admin)
        ->post(route('avana.helpdesk.status', $ticket), [
            'status' => 'resolved',
        ])
        ->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->status)->toBe('resolved');
    expect($ticket->resolved_at)->not->toBeNull();
});

it('adds a reply to a ticket thread', function (): void {
    $ticket = makeTicket($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.helpdesk.reply', $ticket), [
            'message' => 'Sedang kami tindak lanjuti.',
        ])
        ->assertSessionHas('success');

    expect($ticket->replies()->count())->toBe(1);

    $reply = $ticket->replies()->firstOrFail();

    expect($reply->message)->toBe('Sedang kami tindak lanjuti.');
    expect($reply->user_id)->toBe($this->admin->id);
    expect($reply->tenant_id)->toBe($this->tenant->id);
});

it('returns 404 when updating a ticket from another tenant', function (): void {
    $foreign = Ticket::factory()->create();

    actingAs($this->admin)
        ->put(route('avana.helpdesk.update', $foreign), [
            'requester_id' => $foreign->requester_id,
            'category' => 'it',
            'subject' => 'Hack',
            'description' => 'x',
            'priority' => 'low',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a ticket from another tenant', function (): void {
    $foreign = Ticket::factory()->create();

    actingAs($this->admin)
        ->delete(route('avana.helpdesk.destroy', $foreign))
        ->assertNotFound();

    expect(Ticket::find($foreign->id))->not->toBeNull();
});

it('returns 404 when assigning a ticket from another tenant', function (): void {
    $foreign = Ticket::factory()->create();

    actingAs($this->admin)
        ->post(route('avana.helpdesk.assign', $foreign), [
            'assignee_id' => $this->admin->id,
        ])
        ->assertNotFound();
});

it('returns 404 when changing the status of a ticket from another tenant', function (): void {
    $foreign = Ticket::factory()->create(['status' => 'open']);

    actingAs($this->admin)
        ->post(route('avana.helpdesk.status', $foreign), [
            'status' => 'resolved',
        ])
        ->assertNotFound();

    $foreign->refresh();

    expect($foreign->status)->toBe('open');
});

it('returns 404 when replying to a ticket from another tenant', function (): void {
    $foreign = Ticket::factory()->create();

    actingAs($this->admin)
        ->post(route('avana.helpdesk.reply', $foreign), [
            'message' => 'Tidak boleh',
        ])
        ->assertNotFound();
});

it('forbids a plain employee from listing or creating tickets', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.helpdesk'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.helpdesk.store'), [
            'requester_id' => Employee::forTenant($this->tenant->id)->value('id'),
            'category' => 'it',
            'subject' => 'Tidak Boleh',
            'description' => 'x',
            'priority' => 'low',
        ])
        ->assertForbidden();
});
