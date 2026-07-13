<?php

use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmDealMember;
use App\Models\CrmTask;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a CRM contact for the given tenant.
 */
function makeCrmContact(int $tenantId, array $overrides = []): CrmContact
{
    return CrmContact::create(array_merge([
        'tenant_id' => $tenantId,
        'name' => 'Kontak '.fake()->lastName(),
        'company' => fake()->company(),
    ], $overrides));
}

/**
 * Create a CRM deal for the given tenant.
 */
function makeCrmDeal(int $tenantId, array $overrides = []): CrmDeal
{
    return CrmDeal::create(array_merge([
        'tenant_id' => $tenantId,
        'title' => 'Deal '.fake()->word(),
        'value' => 5000000,
        'stage' => 'lead',
    ], $overrides));
}

it('renders the CRM index with the expected props', function (): void {
    makeCrmContact($this->tenant->id);
    makeCrmDeal($this->tenant->id, ['stage' => 'qualified', 'value' => 7500000]);

    actingAs($this->admin)
        ->get(route('avana.crm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/crm/index', false)
            ->has('pipeline.qualified.0', fn (Assert $row) => $row
                ->has('id')
                ->has('title')
                ->has('value')
                ->has('stage')
                ->has('contact_id')
                ->has('contact')
                ->has('company')
                ->has('owner_id')
                ->has('owner')
                ->has('expected_close')
                ->has('notes')
                ->has('activities_count')
                ->has('open_tasks_count'))
            ->has('contacts.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('company')
                ->has('email')
                ->has('phone')
                ->has('notes')
                ->has('deals_count'))
            ->has('contactOptions')
            ->has('owners')
            ->has('stages')
            ->has('kpis'));
});

it('only lists deals that belong to the current tenant', function (): void {
    makeCrmDeal($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeCrmDeal($otherTenant->id);

    $tenantTotal = CrmDeal::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.crm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.total_deals', $tenantTotal));
});

it('stores a contact scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.crm.contact.store'), [
            'name' => 'PT Maju Jaya',
            'company' => 'Maju Jaya',
            'email' => 'sales@maju.test',
            'phone' => '08123456789',
            'notes' => 'Prospek hangat',
        ])
        ->assertRedirect(route('avana.crm'))
        ->assertSessionHas('success');

    $contact = CrmContact::where('name', 'PT Maju Jaya')->firstOrFail();

    expect($contact->tenant_id)->toBe($this->tenant->id);
    expect($contact->email)->toBe('sales@maju.test');
});

it('validates the contact request', function (): void {
    actingAs($this->admin)
        ->post(route('avana.crm.contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['name', 'email']);
});

it('stores a deal scoped to the current tenant', function (): void {
    $contact = makeCrmContact($this->tenant->id);
    $owner = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.crm.deal.store'), [
            'contact_id' => $contact->id,
            'title' => 'Implementasi HRIS',
            'value' => 25000000,
            'stage' => 'proposal',
            'owner_id' => $owner->id,
            'expected_close' => '2026-09-01',
            'notes' => 'Demo selesai',
        ])
        ->assertRedirect(route('avana.crm'))
        ->assertSessionHas('success');

    $deal = CrmDeal::where('title', 'Implementasi HRIS')->firstOrFail();

    expect($deal->tenant_id)->toBe($this->tenant->id);
    expect($deal->stage)->toBe('proposal');
    expect((float) $deal->value)->toBe(25000000.0);
    expect($deal->owner_id)->toBe($owner->id);
});

it('validates the deal request and rejects cross-tenant relations', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignContact = makeCrmContact($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.crm.deal.store'), [
            'contact_id' => $foreignContact->id,
            'title' => '',
            'value' => 'abc',
            'stage' => 'invalid',
        ])
        ->assertSessionHasErrors(['contact_id', 'title', 'value', 'stage']);
});

it('updates an existing deal', function (): void {
    $deal = makeCrmDeal($this->tenant->id, ['title' => 'Old', 'value' => 1000000]);

    actingAs($this->admin)
        ->put(route('avana.crm.deal.update', $deal), [
            'contact_id' => null,
            'title' => 'New',
            'value' => 3000000,
            'stage' => 'won',
            'owner_id' => null,
            'expected_close' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('avana.crm'))
        ->assertSessionHas('success');

    $deal->refresh();

    expect($deal->title)->toBe('New');
    expect($deal->stage)->toBe('won');
    expect((float) $deal->value)->toBe(3000000.0);
});

it('moves a deal to a different stage', function (): void {
    $deal = makeCrmDeal($this->tenant->id, ['stage' => 'lead']);

    actingAs($this->admin)
        ->post(route('avana.crm.deal.stage', $deal), ['stage' => 'won'])
        ->assertSessionHas('success');

    $deal->refresh();

    expect($deal->stage)->toBe('won');
});

it('deletes a deal', function (): void {
    $deal = makeCrmDeal($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.crm.deal.destroy', $deal))
        ->assertSessionHas('success');

    expect(CrmDeal::find($deal->id))->toBeNull();
});

it('returns 404 when updating a deal from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeCrmDeal($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.crm.deal.update', $foreign), [
            'title' => 'Hack',
            'value' => 1,
            'stage' => 'lead',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a deal from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeCrmDeal($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.crm.deal.destroy', $foreign))
        ->assertNotFound();

    expect(CrmDeal::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from accessing the CRM', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.crm'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.crm.deal.store'), [
            'title' => 'Tidak Boleh',
            'value' => 1,
            'stage' => 'lead',
        ])
        ->assertForbidden();
});

it('renders the deal detail page with follow-up, tasks, and members', function (): void {
    $deal = makeCrmDeal($this->tenant->id, ['stage' => 'proposal']);
    $employee = Employee::where('tenant_id', $this->tenant->id)->firstOrFail();

    CrmActivity::create([
        'tenant_id' => $this->tenant->id,
        'deal_id' => $deal->id,
        'type' => 'call',
        'note' => 'Telepon awal',
        'activity_date' => now()->toDateString(),
    ]);
    CrmTask::create([
        'tenant_id' => $this->tenant->id,
        'deal_id' => $deal->id,
        'title' => 'Kirim proposal',
        'status' => 'pending',
    ]);
    CrmDealMember::create([
        'tenant_id' => $this->tenant->id,
        'deal_id' => $deal->id,
        'employee_id' => $employee->id,
        'role' => 'Teknis',
    ]);

    actingAs($this->admin)
        ->get(route('avana.crm.deal.show', $deal))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/crm/deal', false)
            ->has('activities', 1)
            ->has('tasks', 1)
            ->has('members', 1)
            ->has('employeeOptions')
            ->has('projectOptions')
            ->where('activities.0.type', 'call'));
});

it('records a follow-up activity against a deal', function (): void {
    $deal = makeCrmDeal($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.crm.deal.activity.store', $deal), [
            'type' => 'meeting',
            'note' => 'Ketemu di kantor',
            'activity_date' => now()->toDateString(),
            'outcome' => 'Tertarik',
        ])
        ->assertRedirect();

    expect(CrmActivity::where('deal_id', $deal->id)->where('type', 'meeting')->exists())->toBeTrue();
});

it('validates the follow-up activity type', function (): void {
    $deal = makeCrmDeal($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.crm.deal.activity.store', $deal), [
            'type' => 'bogus',
            'note' => 'x',
            'activity_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('type');
});

it('creates and toggles a sales task', function (): void {
    $deal = makeCrmDeal($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.crm.deal.task.store', $deal), ['title' => 'Follow up harga'])
        ->assertRedirect();

    $task = CrmTask::where('deal_id', $deal->id)->firstOrFail();
    expect($task->status)->toBe('pending');

    actingAs($this->admin)
        ->post(route('avana.crm.task.toggle', $task))
        ->assertRedirect();

    $task->refresh();
    expect($task->status)->toBe('done')
        ->and($task->completed_at)->not->toBeNull();
});

it('adds a collaborating member without duplicating', function (): void {
    $deal = makeCrmDeal($this->tenant->id);
    $employee = Employee::where('tenant_id', $this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.crm.deal.member.store', $deal), [
            'employee_id' => $employee->id,
            'role' => 'Pre-sales',
        ])
        ->assertRedirect();

    actingAs($this->admin)
        ->post(route('avana.crm.deal.member.store', $deal), [
            'employee_id' => $employee->id,
        ])
        ->assertRedirect();

    expect(CrmDealMember::where('deal_id', $deal->id)->count())->toBe(1);
});

it('links a deal to a delivery project', function (): void {
    $deal = makeCrmDeal($this->tenant->id);
    $project = Project::create(['tenant_id' => $this->tenant->id, 'name' => 'Proyek Onboarding']);

    actingAs($this->admin)
        ->post(route('avana.crm.deal.project', $deal), ['project_id' => $project->id])
        ->assertRedirect();

    expect($deal->fresh()->project_id)->toBe($project->id);
});

it('rejects a cross-tenant project link', function (): void {
    $deal = makeCrmDeal($this->tenant->id);
    $otherTenant = Tenant::create(['name' => 'PT Asing CRM', 'slug' => 'pt-asing-crm']);
    $foreignProject = Project::create(['tenant_id' => $otherTenant->id, 'name' => 'Proyek Asing']);

    actingAs($this->admin)
        ->post(route('avana.crm.deal.project', $deal), ['project_id' => $foreignProject->id])
        ->assertSessionHasErrors('project_id');
});

it('returns 404 viewing a deal from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain CRM', 'slug' => 'pt-lain-crm']);
    $foreign = makeCrmDeal($otherTenant->id);

    actingAs($this->admin)
        ->get(route('avana.crm.deal.show', $foreign))
        ->assertNotFound();
});

it('renders the CRM insights dashboard', function (): void {
    makeCrmDeal($this->tenant->id, ['stage' => 'won', 'value' => 10000000]);
    makeCrmDeal($this->tenant->id, ['stage' => 'lost', 'value' => 4000000]);
    makeCrmDeal($this->tenant->id, ['stage' => 'proposal', 'value' => 8000000]);

    actingAs($this->admin)
        ->get(route('avana.crm.insights'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/crm/insights', false)
            ->has('funnel', 5)
            ->has('byOwner')
            ->where('kpis.total_deals', 3)
            ->where('kpis.win_rate', fn ($value): bool => (float) $value === 50.0));
});
