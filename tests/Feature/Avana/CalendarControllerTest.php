<?php

use App\Models\CalendarEvent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('renders the calendar index with month-grid props', function (): void {
    CalendarEvent::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Rapat Bulanan',
        'type' => 'meeting',
        'start_date' => Carbon::now()->startOfMonth()->addDays(4)->toDateString(),
        'all_day' => true,
    ]);

    actingAs($this->admin)
        ->get(route('avana.kalender'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kalender/index', false)
            ->where('month', Carbon::now()->format('Y-m'))
            ->has('monthLabel')
            ->has('types')
            ->has('kpis')
            ->has('events', 1, fn (Assert $event) => $event
                ->has('id')
                ->has('title')
                ->has('type')
                ->has('start_date')
                ->has('end_date')
                ->has('all_day')
                ->has('color')
                ->has('description')));
});

it('filters events by the requested month', function (): void {
    CalendarEvent::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Acara Juli',
        'type' => 'event',
        'start_date' => '2026-07-15',
        'all_day' => true,
    ]);

    actingAs($this->admin)
        ->get(route('avana.kalender', ['month' => '2026-07']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('month', '2026-07')->has('events', 1));

    actingAs($this->admin)
        ->get(route('avana.kalender', ['month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('month', '2026-08')->has('events', 0));
});

it('includes spanning events in months they cross into', function (): void {
    CalendarEvent::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Pelatihan Lintas Bulan',
        'type' => 'training',
        'start_date' => '2026-07-28',
        'end_date' => '2026-08-03',
        'all_day' => true,
    ]);

    actingAs($this->admin)
        ->get(route('avana.kalender', ['month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('events', 1));
});

it('creates an event scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kalender.store'), [
            'title' => 'Libur Nasional',
            'type' => 'holiday',
            'start_date' => '2026-08-17',
            'description' => 'HUT RI',
        ])
        ->assertSessionHas('success');

    $event = CalendarEvent::where('title', 'Libur Nasional')->firstOrFail();

    expect($event->tenant_id)->toBe($this->tenant->id);
    expect($event->type)->toBe('holiday');
    expect($event->all_day)->toBeTrue();
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kalender.store'), [
            'title' => '',
            'type' => 'invalid',
            'start_date' => '',
        ])
        ->assertSessionHasErrors(['title', 'type', 'start_date']);
});

it('updates an event', function (): void {
    $event = CalendarEvent::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Lama',
        'type' => 'event',
        'start_date' => '2026-07-10',
        'all_day' => true,
    ]);

    actingAs($this->admin)
        ->put(route('avana.kalender.update', $event), [
            'title' => 'Baru',
            'type' => 'deadline',
            'start_date' => '2026-07-12',
        ])
        ->assertSessionHas('success');

    $event->refresh();

    expect($event->title)->toBe('Baru');
    expect($event->type)->toBe('deadline');
});

it('deletes an event', function (): void {
    $event = CalendarEvent::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Hapus Saya',
        'type' => 'event',
        'start_date' => '2026-07-10',
        'all_day' => true,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.kalender.destroy', $event))
        ->assertSessionHas('success');

    expect(CalendarEvent::find($event->id))->toBeNull();
});

it('returns 404 when updating an event from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = CalendarEvent::create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Asing',
        'type' => 'event',
        'start_date' => '2026-07-10',
        'all_day' => true,
    ]);

    actingAs($this->admin)
        ->put(route('avana.kalender.update', $foreign), [
            'title' => 'Hack',
            'type' => 'event',
            'start_date' => '2026-07-11',
        ])
        ->assertNotFound();
});

it('forbids a plain employee from managing the calendar', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.kalender'))->assertForbidden();
    actingAs($staff)->post(route('avana.kalender.store'), [
        'title' => 'X',
        'type' => 'event',
        'start_date' => '2026-07-10',
    ])->assertForbidden();
});
