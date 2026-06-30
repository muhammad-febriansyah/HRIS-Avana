<?php

use App\Models\Role;
use App\Models\Tenant;
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

it('renders the assistant index with empty conversation props', function (): void {
    actingAs($this->admin)
        ->get(route('avana.ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/ai/index', false)
            ->where('question', null)
            ->where('answer', null));
});

it('answers a payroll question with a canned reply', function (): void {
    actingAs($this->admin)
        ->post(route('avana.ai.ask'), ['message' => 'Bagaimana cara menjalankan payroll gaji?'])
        ->assertSessionHas('question', 'Bagaimana cara menjalankan payroll gaji?')
        ->assertSessionHas('answer', fn (string $answer): bool => str_contains($answer, 'Payroll'));
});

it('answers a leave question with a leave-help reply', function (): void {
    actingAs($this->admin)
        ->post(route('avana.ai.ask'), ['message' => 'Bagaimana mengajukan cuti?'])
        ->assertSessionHas('answer', fn (string $answer): bool => str_contains($answer, 'cuti'));
});

it('falls back to a generic demo reply for unknown topics', function (): void {
    actingAs($this->admin)
        ->post(route('avana.ai.ask'), ['message' => 'halo apa kabar'])
        ->assertSessionHas('answer', fn (string $answer): bool => str_contains($answer, 'mode demo'));
});

it('validates that a message is required', function (): void {
    actingAs($this->admin)
        ->post(route('avana.ai.ask'), ['message' => ''])
        ->assertSessionHasErrors('message');
});

it('forbids a plain employee from using the assistant', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.ai'))->assertForbidden();
    actingAs($staff)->post(route('avana.ai.ask'), ['message' => 'gaji'])->assertForbidden();
});
