<?php

use App\Models\Employee;
use App\Models\Notification;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostLike;
use App\Models\SocialPostReport;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->employeeUser->employee;
    $this->tenantId = (int) $this->employeeUser->tenant_id;

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };

    $this->token = ($this->tokenFor)('bagus.p@nusantara.co.id');
});

it('lists the seeded categories, active ones only', function (): void {
    SocialCategory::forTenant($this->tenantId)->where('name', 'Sports Day')->update(['status' => 'inactive']);

    $response = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/categories')
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Ide Perbaikan')
        ->and($names)->not->toContain('Sports Day');
});

it('creates a post with a photo and returns it published', function (): void {
    Storage::fake('local');

    $category = SocialCategory::forTenant($this->tenantId)->firstOrFail();

    $response = ($this->auth)($this->token)
        ->post('/api/v1/me/social/posts', [
            'social_category_id' => $category->id,
            'body' => 'Usul: tambah dispenser air panas di pantry lantai 3.',
            'image' => UploadedFile::fake()->image('ide.jpg'),
        ])
        ->assertCreated();

    $post = SocialPost::forTenant($this->tenantId)->firstOrFail();

    expect($post->body)->toContain('dispenser')
        ->and($post->status)->toBe('published')
        ->and($post->employee_id)->toBe($this->employee->id)
        ->and($response->json('data.is_mine'))->toBeTrue()
        ->and($response->json('data.image_url'))->not->toBeNull();

    Storage::disk('local')->assertExists($post->image_path);
    Storage::disk('public')->assertMissing($post->image_path);
});

it('rejects an empty post and one over 500 characters', function (): void {
    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts', ['body' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts', ['body' => str_repeat('a', 501)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

it('shows the feed newest first and hides taken-down posts', function (): void {
    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Post lama',
    ]);

    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Post baru',
    ]);

    SocialPost::factory()->hidden()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Post disembunyikan',
    ]);

    $response = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/feed')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0.body'))->toBe('Post baru')
        ->and(collect($response->json('data'))->pluck('body'))
        ->not->toContain('Post disembunyikan');
});

it('never shows another tenant\'s posts', function (): void {
    $other = User::where('tenant_id', '!=', $this->tenantId)->whereNotNull('tenant_id')->first();

    if ($other?->employee !== null) {
        SocialPost::factory()->create([
            'tenant_id' => $other->tenant_id,
            'employee_id' => $other->employee->id,
            'body' => 'Post tenant lain',
        ]);
    }

    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Post tenant saya',
    ]);

    $response = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/feed')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('body'))
        ->toContain('Post tenant saya')
        ->not->toContain('Post tenant lain');
});

it('toggles a like and keeps the counter in step', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/like')
        ->assertOk()
        ->assertJsonPath('data.liked', true)
        ->assertJsonPath('data.likes_count', 1);

    expect($post->refresh()->likes_count)->toBe(1)
        ->and(SocialPostLike::where('social_post_id', $post->id)->count())->toBe(1);

    // Liking again removes it — one like per employee per post.
    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/like')
        ->assertOk()
        ->assertJsonPath('data.liked', false)
        ->assertJsonPath('data.likes_count', 0);

    expect($post->refresh()->likes_count)->toBe(0)
        ->and(SocialPostLike::where('social_post_id', $post->id)->count())->toBe(0);
});

it('adds and removes a comment, keeping the counter in step', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    $created = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', ['body' => 'Setuju!'])
        ->assertCreated()
        ->assertJsonPath('data.is_mine', true);

    expect($post->refresh()->comments_count)->toBe(1);

    ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/posts/'.$post->id.'/comments')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    ($this->auth)($this->token)
        ->deleteJson('/api/v1/me/social/comments/'.$created->json('data.id'))
        ->assertOk();

    expect($post->refresh()->comments_count)->toBe(0);
});

it('lets an employee delete their own post but not someone else\'s', function (): void {
    $mine = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    $others = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => Employee::forTenant($this->tenantId)
            ->where('id', '!=', $this->employee->id)
            ->firstOrFail()->id,
    ]);

    ($this->auth)($this->token)
        ->deleteJson('/api/v1/me/social/posts/'.$mine->id)
        ->assertOk();

    ($this->auth)($this->token)
        ->deleteJson('/api/v1/me/social/posts/'.$others->id)
        ->assertForbidden();

    expect(SocialPost::whereKey($others->id)->exists())->toBeTrue();
});

it('deletes the likes and comments along with the post', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    ($this->auth)($this->token)->postJson('/api/v1/me/social/posts/'.$post->id.'/like')->assertOk();
    ($this->auth)($this->token)->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', ['body' => 'Halo'])->assertCreated();

    ($this->auth)($this->token)->deleteJson('/api/v1/me/social/posts/'.$post->id)->assertOk();

    expect(SocialPostLike::where('social_post_id', $post->id)->count())->toBe(0)
        ->and(SocialPostComment::withTrashed()->where('social_post_id', $post->id)->count())->toBe(0);
});

it('ranks the leaderboard by points earned from posts, likes and comments', function (): void {
    $other = Employee::forTenant($this->tenantId)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    // 1 post + 10 likes + 0 comments = 5 + 20 = 25 points.
    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $other->id,
        'likes_count' => 10,
    ]);

    // 2 posts + 1 like + 1 comment = 10 + 2 + 1 = 13 points.
    SocialPost::factory()->count(2)->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);
    SocialPost::forTenant($this->tenantId)->where('employee_id', $this->employee->id)
        ->first()->update(['likes_count' => 1, 'comments_count' => 1]);

    $response = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/leaderboard')
        ->assertOk();

    $rows = collect($response->json('data'));

    expect($rows->first()['employee_id'])->toBe($other->id)
        ->and($rows->first()['points'])->toBe(25)
        ->and($rows->firstWhere('employee_id', $this->employee->id)['points'])->toBe(13)
        ->and($rows->firstWhere('employee_id', $this->employee->id)['is_me'])->toBeTrue();
});

it('reports a post once per employee, however often they tap it', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => Employee::forTenant($this->tenantId)
            ->where('id', '!=', $this->employee->id)->firstOrFail()->id,
    ]);

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/report', ['reason' => 'Tidak pantas'])
        ->assertOk();

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/report')
        ->assertOk();

    expect(SocialPostReport::where('social_post_id', $post->id)->count())->toBe(1);
});

it('notifies the author when someone comments, but not when they comment on their own post', function (): void {
    $author = Employee::forTenant($this->tenantId)
        ->whereNotNull('user_id')
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    $theirs = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $author->id,
    ]);

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$theirs->id.'/comments', ['body' => 'Keren!'])
        ->assertCreated();

    expect(Notification::where('user_id', $author->user_id)->where('type', 'social')->count())->toBe(1);

    $mine = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$mine->id.'/comments', ['body' => 'Menambahkan'])
        ->assertCreated();

    // Telling someone about their own comment is noise, not news.
    expect(Notification::where('user_id', $this->employeeUser->id)->where('type', 'social')->count())->toBe(0);
});

it('edits your own post while keeping its likes and comments', function (): void {
    Storage::fake('local');

    $category = SocialCategory::forTenant($this->tenantId)->firstOrFail();

    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Salah tulis',
        'likes_count' => 3,
        'comments_count' => 2,
    ]);

    ($this->auth)($this->token)
        ->post('/api/v1/me/social/posts/'.$post->id.'/update', [
            'body' => 'Sudah diperbaiki',
            'social_category_id' => $category->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.body', 'Sudah diperbaiki')
        ->assertJsonPath('data.edited', true);

    $post->refresh();

    // The post keeps its id, so the conversation under it survives the edit.
    expect($post->body)->toBe('Sudah diperbaiki')
        ->and($post->social_category_id)->toBe($category->id)
        ->and($post->likes_count)->toBe(3)
        ->and($post->comments_count)->toBe(2);
});

it('refuses to edit someone else\'s post', function (): void {
    $others = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => Employee::forTenant($this->tenantId)
            ->where('id', '!=', $this->employee->id)->firstOrFail()->id,
        'body' => 'Punya orang lain',
    ]);

    ($this->auth)($this->token)
        ->post('/api/v1/me/social/posts/'.$others->id.'/update', ['body' => 'Dibajak'])
        ->assertForbidden();

    expect($others->refresh()->body)->toBe('Punya orang lain');
});

it('clears the photo when asked to remove it', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('social/'.$this->tenantId.'/foto.jpg', 'x');

    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'image_path' => 'social/'.$this->tenantId.'/foto.jpg',
    ]);

    ($this->auth)($this->token)
        ->post('/api/v1/me/social/posts/'.$post->id.'/update', [
            'body' => 'Tanpa foto',
            'remove_image' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.image_url', null);

    expect($post->refresh()->image_path)->toBeNull();
    Storage::disk('local')->assertMissing('social/'.$this->tenantId.'/foto.jpg');
});

it('serves a single post for the detail screen but not a hidden one', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Detail ini',
    ]);

    ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/posts/'.$post->id)
        ->assertOk()
        ->assertJsonPath('data.body', 'Detail ini')
        ->assertJsonPath('data.is_mine', true);

    $post->update(['status' => SocialPost::STATUS_HIDDEN]);

    ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/posts/'.$post->id)
        ->assertNotFound();
});

it('nests a reply under the comment it answers', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    $parentId = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', ['body' => 'Komentar utama'])
        ->assertCreated()
        ->json('data.id');

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', [
            'body' => 'Balasan untuk Yoga',
            'parent_id' => $parentId,
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Balasan terkirim')
        ->assertJsonPath('data.parent_id', $parentId);

    $response = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/posts/'.$post->id.'/comments')
        ->assertOk()
        // The reply hangs off its parent, not beside it.
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(1, 'data.0.replies');

    expect($response->json('data.0.replies.0.body'))->toBe('Balasan untuk Yoga')
        // Both still count towards the post's comment tally.
        ->and($post->refresh()->comments_count)->toBe(2);
});

it('keeps threads one level deep', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    $parentId = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', ['body' => 'Induk'])
        ->json('data.id');

    $replyId = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', [
            'body' => 'Balasan',
            'parent_id' => $parentId,
        ])
        ->json('data.id');

    // Replying to a reply lands beside it, under the same parent — otherwise a
    // thread marches off the right edge of a phone.
    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', [
            'body' => 'Balasan atas balasan',
            'parent_id' => $replyId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parentId);

    ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/posts/'.$post->id.'/comments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(2, 'data.0.replies');
});

it('refuses a parent comment from a different post', function (): void {
    $postA = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    $postB = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    $foreignParentId = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$postA->id.'/comments', ['body' => 'Di post A'])
        ->json('data.id');

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$postB->id.'/comments', [
            'body' => 'Nyasar',
            'parent_id' => $foreignParentId,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');
});

it('stores emoji intact, including ZWJ sequences and skin tones', function (): void {
    $body = 'Ide bagus 🎉🔥 tim: 👨‍👩‍👧‍👦 bendera: 🇮🇩 jempol: 👍🏽';

    $id = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts', ['body' => $body])
        ->assertCreated()
        ->json('data.id');

    // utf8mb4 end to end — a 3-byte utf8 column would mangle these.
    expect(SocialPost::findOrFail($id)->body)->toBe($body);

    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$id.'/comments', ['body' => 'Setuju 👏🏼✨'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Setuju 👏🏼✨');
});

it('ranks the trending feed by interaction within the last week', function (): void {
    // Popular but stale: without the window it would sit on top forever.
    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Viral tahun lalu',
        'likes_count' => 500,
        'created_at' => now()->subDays(60),
    ]);

    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Ramai minggu ini',
        'likes_count' => 8,
        'comments_count' => 4,
        'created_at' => now()->subDays(2),
    ]);

    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Baru tapi sepi',
        'created_at' => now()->subHour(),
    ]);

    $trending = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/feed?sort=trending')
        ->assertOk()
        ->assertJsonPath('meta.sort', 'trending');

    $bodies = collect($trending->json('data'))->pluck('body');

    expect($bodies->first())->toBe('Ramai minggu ini')
        ->and($bodies)->not->toContain('Viral tahun lalu');

    // The default stays chronological, so a quiet new post is still seen.
    $latest = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/feed')
        ->assertOk()
        ->assertJsonPath('meta.sort', 'latest');

    expect(collect($latest->json('data'))->pluck('body')->first())->toBe('Baru tapi sepi');
});

it('names the person a reply answers, but only when the indent cannot', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    $parentId = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', ['body' => 'Saya ikut ya.'])
        ->json('data.id');

    // A reply to the top-level comment sits directly under it, so the indent
    // already says who it answers.
    $replyId = ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', [
            'body' => 'ayo',
            'parent_id' => $parentId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.reply_to', null)
        ->json('data.id');

    // Replying to that reply lands beside it, not under it — hence the name.
    ($this->auth)($this->token)
        ->postJson('/api/v1/me/social/posts/'.$post->id.'/comments', [
            'body' => 'oke',
            'parent_id' => $replyId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parentId)
        ->assertJsonPath('data.reply_to', $this->employee->full_name);

    $thread = ($this->auth)($this->token)
        ->getJson('/api/v1/me/social/posts/'.$post->id.'/comments')
        ->assertOk();

    expect($thread->json('data.0.replies.1.reply_to'))->toBe($this->employee->full_name);
});
