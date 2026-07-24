<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('returns the assistant session with the tenant token meter', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson('/api/v1/me/ai')
        ->assertOk()
        ->assertJsonStructure([
            'ready',
            'usage' => ['used', 'quota', 'period'],
            'conversations',
            'suggestions',
        ])
        ->assertJsonPath('usage.quota', 500000);
});

it('persists both turns and returns a reply plus token usage', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $response = ($this->auth)($token)
        ->postJson('/api/v1/me/ai/chat', ['message' => 'Berapa sisa cuti saya tahun ini?'])
        ->assertOk()
        ->assertJsonStructure([
            'conversation_id',
            'title',
            'reply' => ['id', 'role', 'content'],
            'usage' => ['used', 'quota', 'period'],
        ]);

    $conversationId = $response->json('conversation_id');

    expect($response->json('reply.content'))->not->toBeEmpty();
    expect($response->json('reply.role'))->toBe('assistant');

    $conversation = AiConversation::findOrFail($conversationId);
    expect($conversation->user_id)->toBe($this->employeeUser->id);
    expect($conversation->messages()->where('role', 'user')->count())->toBe(1);
    expect($conversation->messages()->where('role', 'assistant')->count())->toBe(1);
});

it('loads message history for an owned conversation', function (): void {
    $conversation = AiConversation::create([
        'tenant_id' => $this->employeeUser->tenant_id,
        'user_id' => $this->employeeUser->id,
        'title' => 'Uji',
    ]);
    AiMessage::create(['conversation_id' => $conversation->id, 'tenant_id' => $this->employeeUser->tenant_id, 'user_id' => $this->employeeUser->id, 'role' => 'user', 'content' => 'Halo']);
    AiMessage::create(['conversation_id' => $conversation->id, 'tenant_id' => $this->employeeUser->tenant_id, 'user_id' => $this->employeeUser->id, 'role' => 'assistant', 'content' => 'Hai']);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson("/api/v1/me/ai/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('id', $conversation->id)
        ->assertJsonCount(2, 'messages');
});

it('cannot open or delete another user conversation', function (): void {
    $other = User::factory()->create(['tenant_id' => $this->employeeUser->tenant_id]);
    $conversation = AiConversation::create([
        'tenant_id' => $other->tenant_id,
        'user_id' => $other->id,
        'title' => 'Rahasia',
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson("/api/v1/me/ai/conversations/{$conversation->id}")
        ->assertNotFound();

    ($this->auth)($token)
        ->deleteJson("/api/v1/me/ai/conversations/{$conversation->id}")
        ->assertNotFound();
});
