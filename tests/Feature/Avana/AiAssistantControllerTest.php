<?php

use App\Models\AiConversation;
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

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a conversation with messages for the admin user.
 */
function makeConversation(object $ctx, array $overrides = []): AiConversation
{
    $conversation = AiConversation::create(array_merge([
        'tenant_id' => $ctx->tenant->id,
        'user_id' => $ctx->admin->id,
        'title' => 'Percakapan uji',
    ], $overrides));

    AiMessage::create(['conversation_id' => $conversation->id, 'tenant_id' => $ctx->tenant->id, 'user_id' => $ctx->admin->id, 'role' => 'user', 'content' => 'Halo']);
    AiMessage::create(['conversation_id' => $conversation->id, 'tenant_id' => $ctx->tenant->id, 'user_id' => $ctx->admin->id, 'role' => 'assistant', 'content' => 'Hai']);

    return $conversation;
}

it('renders the assistant with the conversation history sidebar', function (): void {
    $conversation = makeConversation($this);

    actingAs($this->admin)
        ->get(route('avana.ai', ['c' => $conversation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/ai/index', false)
            ->has('conversations', 1)
            ->where('activeId', $conversation->id)
            ->has('messages', 2)
            ->has('ready'));
});

it('shows an empty active conversation when none is selected', function (): void {
    makeConversation($this);

    actingAs($this->admin)
        ->get(route('avana.ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeId', null)
            ->has('messages', 0)
            ->has('conversations', 1));
});

it('reports monthly token usage against the tenant quota', function (): void {
    $conversation = AiConversation::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'title' => 'Pemakaian token',
    ]);

    // Counted: assistant replies this month.
    AiMessage::create(['conversation_id' => $conversation->id, 'tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'assistant', 'content' => 'A', 'prompt_tokens' => 1200, 'completion_tokens' => 800, 'total_tokens' => 2000]);
    AiMessage::create(['conversation_id' => $conversation->id, 'tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'assistant', 'content' => 'B', 'prompt_tokens' => 500, 'completion_tokens' => 300, 'total_tokens' => 800]);

    // Excluded: a reply logged in a previous month.
    AiMessage::create(['conversation_id' => $conversation->id, 'tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'assistant', 'content' => 'Lama', 'total_tokens' => 9999, 'created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

    actingAs($this->admin)
        ->get(route('avana.ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tokenUsage.used', 2800)
            ->where('tokenUsage.quota', 500000)
            ->has('tokenUsage.period'));
});

it('validates that a message is required to stream', function (): void {
    actingAs($this->admin)
        ->post(route('avana.ai.stream'), ['message' => ''])
        ->assertSessionHasErrors('message');
});

it('creates a conversation and persists both turns when streaming', function (): void {
    $response = actingAs($this->admin)->post(route('avana.ai.stream'), ['message' => 'Bagaimana cara payroll?']);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/plain');

    $conversationId = (int) $response->headers->get('X-Conversation-Id');
    expect($conversationId)->toBeGreaterThan(0);
    expect($response->streamedContent())->not->toBeEmpty();

    $conversation = AiConversation::findOrFail($conversationId);
    expect($conversation->user_id)->toBe($this->admin->id);
    expect($conversation->messages()->where('role', 'user')->count())->toBe(1);
    expect($conversation->messages()->where('role', 'assistant')->count())->toBe(1);
});

it('appends to an existing conversation', function (): void {
    $conversation = makeConversation($this);

    $response = actingAs($this->admin)
        ->post(route('avana.ai.stream'), ['message' => 'Lanjut', 'conversation_id' => $conversation->id]);

    $response->assertOk();
    $response->streamedContent();

    expect($conversation->messages()->count())->toBe(4);
});

it('deletes a conversation', function (): void {
    $conversation = makeConversation($this);

    actingAs($this->admin)
        ->delete(route('avana.ai.conversation.destroy', $conversation))
        ->assertRedirect();

    expect(AiConversation::find($conversation->id))->toBeNull();
    expect(AiMessage::where('conversation_id', $conversation->id)->count())->toBe(0);
});

it('returns 404 deleting another user conversation', function (): void {
    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $conversation = AiConversation::create(['tenant_id' => $this->tenant->id, 'user_id' => $other->id, 'title' => 'x']);

    actingAs($this->admin)
        ->delete(route('avana.ai.conversation.destroy', $conversation))
        ->assertNotFound();
});

it('forbids a plain employee from the assistant', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.ai'))->assertForbidden();
});
