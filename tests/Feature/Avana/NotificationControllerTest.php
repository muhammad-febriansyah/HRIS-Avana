<?php

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

function makeNotification(int $tenantId, int $userId, array $overrides = []): Notification
{
    return Notification::create(array_merge([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'type' => 'attendance',
        'title' => 'Clock-in tercatat',
        'body' => 'Masuk pukul 08:00',
    ], $overrides));
}

it('shares the current user notifications and unread count', function (): void {
    makeNotification($this->tenant->id, $this->admin->id);
    makeNotification($this->tenant->id, $this->admin->id, ['read_at' => now()]);

    actingAs($this->admin)
        ->get('/avana/absensi')
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 1)
            ->has('notifications.items', 2)
        );
});

it('marks a single notification read', function (): void {
    $n = makeNotification($this->tenant->id, $this->admin->id);

    actingAs($this->admin)
        ->post("/avana/notifications/{$n->id}/read")
        ->assertRedirect();

    expect($n->fresh()->read_at)->not->toBeNull();
});

it('does not let a user mark another users notification read', function (): void {
    $other = User::where('id', '!=', $this->admin->id)->firstOrFail();
    $n = makeNotification($other->tenant_id, $other->id);

    actingAs($this->admin)
        ->post("/avana/notifications/{$n->id}/read")
        ->assertNotFound();

    expect($n->fresh()->read_at)->toBeNull();
});

it('marks all unread notifications read', function (): void {
    makeNotification($this->tenant->id, $this->admin->id);
    makeNotification($this->tenant->id, $this->admin->id);

    actingAs($this->admin)
        ->post('/avana/notifications/read-all')
        ->assertRedirect();

    expect(Notification::where('user_id', $this->admin->id)->whereNull('read_at')->count())
        ->toBe(0);
});
