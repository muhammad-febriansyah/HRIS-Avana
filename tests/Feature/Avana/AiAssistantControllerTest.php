<?php

use App\Models\AiMessage;
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

it('renders the assistant with the user conversation history', function (): void {
    AiMessage::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'user', 'content' => 'Halo']);
    AiMessage::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'assistant', 'content' => 'Hai, ada yang bisa dibantu?']);

    actingAs($this->admin)
        ->get(route('avana.ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/ai/index', false)
            ->has('messages', 2)
            ->where('messages.0.role', 'user')
            ->where('messages.1.role', 'assistant')
            ->has('ready'));
});

it('validates that a message is required to stream', function (): void {
    actingAs($this->admin)
        ->post(route('avana.ai.stream'), ['message' => ''])
        ->assertSessionHasErrors('message');
});

it('streams a reply and persists both turns of the conversation', function (): void {
    // The test env forces an empty OPENAI key, so the controller streams its
    // deterministic no-key fallback instead of calling the real API.
    $response = actingAs($this->admin)->post(route('avana.ai.stream'), ['message' => 'Bagaimana cara payroll?']);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/plain');

    $body = $response->streamedContent();
    expect($body)->not->toBeEmpty();

    expect(AiMessage::forUser($this->admin->id)->where('role', 'user')->count())->toBe(1);
    expect(AiMessage::forUser($this->admin->id)->where('role', 'assistant')->count())->toBe(1);
});

it('clears the conversation history', function (): void {
    AiMessage::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'user', 'content' => 'x']);
    AiMessage::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'assistant', 'content' => 'y']);

    actingAs($this->admin)
        ->post(route('avana.ai.clear'))
        ->assertRedirect();

    expect(AiMessage::forUser($this->admin->id)->count())->toBe(0);
});

it('forbids a plain employee from the assistant', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.ai'))->assertForbidden();
});
