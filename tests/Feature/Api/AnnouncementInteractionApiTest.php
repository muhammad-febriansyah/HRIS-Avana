<?php

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenantId = $this->user->employee->tenant_id;

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->announcement = Announcement::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Libur Nasional',
        'body' => 'Kantor tutup Jumat.',
        'category' => 'umum',
        'status' => 'published',
        'published_at' => now(),
        'pinned' => false,
    ]);
});

it('lists announcements with read + comment counts', function (): void {
    ($this->auth)()->getJson('/api/v1/me/announcements')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'title', 'is_read', 'read_count', 'comment_count', 'attachment']]]);
});

it('exposes the attachment so the app can render or download it', function (): void {
    $this->announcement->update([
        'attachment_path' => 'announcements/'.$this->tenantId.'/poster.png',
        'attachment_name' => 'poster.png',
        'attachment_mime' => 'image/png',
        'attachment_size' => 2048,
    ]);

    ($this->auth)()->getJson('/api/v1/me/announcements/'.$this->announcement->id)
        ->assertOk()
        ->assertJsonPath('data.attachment.name', 'poster.png')
        ->assertJsonPath('data.attachment.is_image', true)
        ->assertJsonPath('data.attachment.size', 2048)
        ->assertJsonPath('data.attachment.url', $this->announcement->attachmentUrl());
});

it('reports a null attachment when the announcement has none', function (): void {
    ($this->auth)()->getJson('/api/v1/me/announcements/'.$this->announcement->id)
        ->assertOk()
        ->assertJsonPath('data.attachment', null);
});

it('marks an announcement as read idempotently', function (): void {
    $id = $this->announcement->id;

    ($this->auth)()->postJson("/api/v1/me/announcements/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.is_read', true)
        ->assertJsonPath('data.read_count', 1);

    // A second confirmation does not double-count.
    ($this->auth)()->postJson("/api/v1/me/announcements/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_count', 1);

    ($this->auth)()->getJson("/api/v1/me/announcements/{$id}")
        ->assertOk()
        ->assertJsonPath('data.is_read', true);
});

it('posts and lists comments', function (): void {
    $id = $this->announcement->id;

    ($this->auth)()->postJson("/api/v1/me/announcements/{$id}/comments", ['body' => 'Terima kasih infonya'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Terima kasih infonya')
        ->assertJsonPath('data.author.name', fn (?string $n): bool => $n !== null && $n !== '');

    ($this->auth)()->getJson("/api/v1/me/announcements/{$id}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    ($this->auth)()->getJson("/api/v1/me/announcements/{$id}")
        ->assertOk()
        ->assertJsonPath('data.comment_count', 1);
});

it('requires a comment body', function (): void {
    $id = $this->announcement->id;

    ($this->auth)()->postJson("/api/v1/me/announcements/{$id}/comments", ['body' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

it('404s a draft announcement', function (): void {
    $draft = Announcement::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Draf',
        'body' => 'belum terbit',
        'status' => 'draft',
        'pinned' => false,
    ]);

    ($this->auth)()->postJson("/api/v1/me/announcements/{$draft->id}/read")->assertNotFound();
    ($this->auth)()->getJson("/api/v1/me/announcements/{$draft->id}/comments")->assertNotFound();
});
