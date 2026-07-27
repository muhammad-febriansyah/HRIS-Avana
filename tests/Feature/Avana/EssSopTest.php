<?php

use App\Models\Permission;
use App\Models\Sop;
use App\Models\SopCategory;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenantId = (int) $this->employee->tenant_id;
});

it('lets a plain employee open the AI assistant', function (): void {
    expect($this->employee->hasPermissionTo('ai.view'))->toBeTrue();

    actingAs($this->employee)
        ->get(route('avana.ai'))
        ->assertOk();
});

it('shows an employee only the public SOPs on the self-service page', function (): void {
    $category = SopCategory::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Kepegawaian',
        'status' => 'active',
    ]);

    Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->tenantId,
        'sop_category_id' => $category->id,
        'title' => 'SOP Pengajuan Cuti',
    ]);

    Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Rahasia Direksi',
    ]);

    Sop::factory()->publicVisibility()->inactive()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Lama',
    ]);

    actingAs($this->employee)
        ->get(route('avana.saya.sop'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/saya/sop', false)
            ->has('sops', 1)
            ->where('sops.0.title', 'SOP Pengajuan Cuti')
            ->where('sops.0.category', 'Kepegawaian'));
});

it('shows private SOPs on the self-service page to a holder of sop.view', function (): void {
    Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Rahasia Direksi',
    ]);

    // The HR admin has no employee record, so the self-service page is closed
    // to them by design — an employee granted sop.view sees the private ones.
    $this->employee->roles()->first()->permissions()->syncWithoutDetaching(
        Permission::where('code', 'sop.view')->pluck('id'),
    );

    actingAs($this->employee->fresh())
        ->get(route('avana.saya.sop'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('sops', 1));
});

it('refuses to serve a private SOP to an employee who guesses its id', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('sop/'.$this->tenantId.'/rahasia.pdf', '%PDF-1.4');

    $private = Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'file_path' => 'sop/'.$this->tenantId.'/rahasia.pdf',
    ]);

    actingAs($this->employee)
        ->get(route('avana.saya.sop.download', $private))
        ->assertNotFound();
});

it('serves a public SOP PDF to an employee', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('sop/'.$this->tenantId.'/umum.pdf', '%PDF-1.4');

    $public = Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->tenantId,
        'file_path' => 'sop/'.$this->tenantId.'/umum.pdf',
        'file_name' => 'umum.pdf',
    ]);

    actingAs($this->employee)
        ->get(route('avana.saya.sop.download', $public))
        ->assertOk();
});

it('passes only readable SOP codes to the chat for citation links', function (): void {
    Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->tenantId,
        'code' => 'SOP-UMUM-01',
        'file_path' => 'sop/'.$this->tenantId.'/umum.pdf',
    ]);

    Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'code' => 'SOP-RAHASIA-01',
        'file_path' => 'sop/'.$this->tenantId.'/rahasia.pdf',
    ]);

    actingAs($this->employee)
        ->get(route('avana.ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sopCitations', 1)
            ->where('sopCitations.0.code', 'SOP-UMUM-01'));
});
