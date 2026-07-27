<?php

use App\Models\Employee;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostReport;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    Storage::fake('public');

    // A plain karyawan, not HR: this screen is employee self-service.
    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;
    $this->tenantId = (int) $this->employee->tenant_id;
});

function makeWallPost(int $tenantId, int $employeeId, array $overrides = []): SocialPost
{
    return SocialPost::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employeeId,
        'body' => 'Ide penghematan kertas di gudang',
        'status' => SocialPost::STATUS_PUBLISHED,
    ], $overrides));
}

it('renders the wall with live posts, categories and the ranking', function (): void {
    makeWallPost($this->tenantId, $this->employee->id, ['body' => 'Postingan uji dinding']);

    actingAs($this->user)
        ->get('/avana/saya/sosmed')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/saya/sosmed')
            ->has('posts.data')
            ->has('categories')
            ->has('leaderboard')
            ->has('weights'));
});

it('filters the feed by category', function (): void {
    $category = SocialCategory::forTenant($this->tenantId)->firstOrFail();

    makeWallPost($this->tenantId, $this->employee->id, [
        'body' => 'Masuk kategori',
        'social_category_id' => $category->id,
    ]);
    makeWallPost($this->tenantId, $this->employee->id, ['body' => 'Tanpa kategori']);

    actingAs($this->user)
        ->get("/avana/saya/sosmed?category={$category->id}")
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $bodies = collect($page->toArray()['props']['posts']['data'])->pluck('body');

            expect($bodies)->toContain('Masuk kategori');
            expect($bodies)->not->toContain('Tanpa kategori');
        });
});

it('posts to the wall with an image', function (): void {
    $category = SocialCategory::forTenant($this->tenantId)->firstOrFail();

    actingAs($this->user)
        ->post('/avana/saya/sosmed', [
            'body' => 'Halo dari web',
            'social_category_id' => $category->id,
            'image' => UploadedFile::fake()->image('ide.jpg'),
        ])
        ->assertRedirect();

    $post = SocialPost::where('body', 'Halo dari web')->firstOrFail();

    expect((int) $post->employee_id)->toBe((int) $this->employee->id);
    expect((int) $post->tenant_id)->toBe($this->tenantId);
    expect($post->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($post->image_path);
});

it('rejects an empty post body', function (): void {
    actingAs($this->user)
        ->post('/avana/saya/sosmed', ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('toggles a like on and off, keeping the counter in step', function (): void {
    $post = makeWallPost($this->tenantId, $this->employee->id);

    actingAs($this->user)->post("/avana/saya/sosmed/{$post->id}/suka")->assertRedirect();
    expect((int) $post->fresh()->likes_count)->toBe(1);

    actingAs($this->user)->post("/avana/saya/sosmed/{$post->id}/suka")->assertRedirect();
    expect((int) $post->fresh()->likes_count)->toBe(0);
});

it('comments on a post and lists the thread as json', function (): void {
    $post = makeWallPost($this->tenantId, $this->employee->id);

    actingAs($this->user)
        ->post("/avana/saya/sosmed/{$post->id}/komentar", ['body' => 'Setuju banget'])
        ->assertRedirect();

    expect((int) $post->fresh()->comments_count)->toBe(1);

    actingAs($this->user)
        ->getJson("/avana/saya/sosmed/{$post->id}/komentar")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Setuju banget')
        ->assertJsonPath('data.0.is_mine', true);
});

it('deletes only your own post', function (): void {
    $mine = makeWallPost($this->tenantId, $this->employee->id);
    $other = Employee::forTenant($this->tenantId)->where('id', '!=', $this->employee->id)->firstOrFail();
    $theirs = makeWallPost($this->tenantId, $other->id, ['body' => 'Punya orang lain']);

    actingAs($this->user)->delete("/avana/saya/sosmed/{$mine->id}")->assertRedirect();
    expect(SocialPost::find($mine->id))->toBeNull();

    actingAs($this->user)->delete("/avana/saya/sosmed/{$theirs->id}")->assertForbidden();
    expect(SocialPost::find($theirs->id))->not->toBeNull();
});

it('deletes only your own comment', function (): void {
    $post = makeWallPost($this->tenantId, $this->employee->id);
    $other = Employee::forTenant($this->tenantId)->where('id', '!=', $this->employee->id)->firstOrFail();

    $theirs = SocialPostComment::create([
        'tenant_id' => $this->tenantId,
        'social_post_id' => $post->id,
        'employee_id' => $other->id,
        'body' => 'Komentar orang lain',
    ]);

    actingAs($this->user)->delete("/avana/saya/sosmed/komentar/{$theirs->id}")->assertForbidden();
});

it('reports a post for HR, and reporting twice does not duplicate', function (): void {
    $other = Employee::forTenant($this->tenantId)->where('id', '!=', $this->employee->id)->firstOrFail();
    $post = makeWallPost($this->tenantId, $other->id);

    actingAs($this->user)
        ->post("/avana/saya/sosmed/{$post->id}/lapor", ['reason' => 'Tidak pantas'])
        ->assertRedirect();
    actingAs($this->user)
        ->post("/avana/saya/sosmed/{$post->id}/lapor", ['reason' => 'Masih tidak pantas'])
        ->assertRedirect();

    expect(SocialPostReport::where('social_post_id', $post->id)
        ->where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('never shows or touches another tenant\'s wall', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Seberang', 'slug' => 'pt-seberang-wall']);
    $stranger = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-6001',
        'full_name' => 'Karyawan Seberang',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreignPost = makeWallPost((int) $otherTenant->id, $stranger->id, ['body' => 'Rahasia tenant lain']);

    actingAs($this->user)
        ->get('/avana/saya/sosmed')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $bodies = collect($page->toArray()['props']['posts']['data'])->pluck('body');

            expect($bodies)->not->toContain('Rahasia tenant lain');
        });

    actingAs($this->user)->post("/avana/saya/sosmed/{$foreignPost->id}/suka")->assertNotFound();
    actingAs($this->user)->getJson("/avana/saya/sosmed/{$foreignPost->id}/komentar")->assertNotFound();
    actingAs($this->user)->post("/avana/saya/sosmed/{$foreignPost->id}/lapor")->assertNotFound();
});

it('shares one wall between the web page and the mobile API', function (): void {
    actingAs($this->user)->post('/avana/saya/sosmed', ['body' => 'Ditulis dari web'])->assertRedirect();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->app['auth']->forgetGuards();

    $bodies = collect($this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/social/feed')
        ->assertOk()
        ->json('data'))->pluck('body');

    expect($bodies)->toContain('Ditulis dari web');
});

it('replies to a comment and nests it under the parent', function (): void {
    $post = makeWallPost($this->tenantId, $this->employee->id);

    actingAs($this->user)
        ->post("/avana/saya/sosmed/{$post->id}/komentar", ['body' => 'Komentar utama'])
        ->assertRedirect();

    $parent = SocialPostComment::where('social_post_id', $post->id)->firstOrFail();

    actingAs($this->user)
        ->post("/avana/saya/sosmed/{$post->id}/komentar", [
            'body' => 'Balasannya',
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    actingAs($this->user)
        ->getJson("/avana/saya/sosmed/{$post->id}/komentar")
        ->assertOk()
        // Only the parent is top-level; the reply hangs off it.
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Komentar utama')
        ->assertJsonPath('data.0.replies.0.body', 'Balasannya');
});

it('refuses a reply whose parent belongs to another post', function (): void {
    $post = makeWallPost($this->tenantId, $this->employee->id);
    $otherPost = makeWallPost($this->tenantId, $this->employee->id, ['body' => 'Postingan lain']);

    actingAs($this->user)
        ->post("/avana/saya/sosmed/{$otherPost->id}/komentar", ['body' => 'Milik postingan lain'])
        ->assertRedirect();

    $foreignParent = SocialPostComment::where('social_post_id', $otherPost->id)->firstOrFail();

    actingAs($this->user)
        ->post("/avana/saya/sosmed/{$post->id}/komentar", [
            'body' => 'Nyasar',
            'parent_id' => $foreignParent->id,
        ])
        ->assertSessionHasErrors('parent_id');
});
