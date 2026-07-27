<?php

use App\Models\Permission;
use App\Models\Sop;
use App\Models\SopCategory;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenantId = (int) $this->employeeUser->tenant_id;

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('lists only the public SOPs for an employee', function (): void {
    $category = SopCategory::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Kepegawaian',
        'status' => 'active',
    ]);

    Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->tenantId,
        'sop_category_id' => $category->id,
        'title' => 'SOP Pengajuan Cuti',
        'code' => 'SOP-HR-001',
    ]);

    Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Rahasia Direksi',
    ]);

    Sop::factory()->publicVisibility()->inactive()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Lama',
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson('/api/v1/me/sop')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'SOP Pengajuan Cuti')
        ->assertJsonPath('data.0.code', 'SOP-HR-001')
        ->assertJsonPath('data.0.category', 'Kepegawaian')
        ->assertJsonPath('data.0.visibility', 'public');
});

it('includes private SOPs for an employee holding sop.view', function (): void {
    Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Rahasia Direksi',
    ]);

    $this->employeeUser->roles()->first()->permissions()->syncWithoutDetaching(
        Permission::where('code', 'sop.view')->pluck('id'),
    );

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson('/api/v1/me/sop')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'SOP Rahasia Direksi');
});

it('downloads a public SOP PDF', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('sop/'.$this->tenantId.'/umum.pdf', '%PDF-1.4');

    $sop = Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->tenantId,
        'file_path' => 'sop/'.$this->tenantId.'/umum.pdf',
        'file_name' => 'umum.pdf',
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->get('/api/v1/me/sop/'.$sop->id.'/download')
        ->assertOk();
});

it('refuses to download a private SOP whose id was guessed', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('sop/'.$this->tenantId.'/rahasia.pdf', '%PDF-1.4');

    $sop = Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'file_path' => 'sop/'.$this->tenantId.'/rahasia.pdf',
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->get('/api/v1/me/sop/'.$sop->id.'/download')
        ->assertNotFound();
});

it('never leaks another tenant\'s SOP', function (): void {
    $otherSop = Sop::factory()->publicVisibility()->create([
        'title' => 'SOP Tenant Lain',
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson('/api/v1/me/sop')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    ($this->auth)($token)
        ->get('/api/v1/me/sop/'.$otherSop->id.'/download')
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/me/sop')->assertUnauthorized();
});
