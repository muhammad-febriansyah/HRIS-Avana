<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostLike;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A wall with something on it: ten posts spread across the categories and the
 * employees, with likes and comments so the feed, the counters and the
 * leaderboard all have real numbers to show.
 *
 * Idempotent by post body — running it twice does not duplicate the wall.
 */
class SocialDemoSeeder extends Seeder
{
    /**
     * [category name, body, likes, comments, days ago]
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: int, 4: int}>
     */
    private const POSTS = [
        ['Ide Perbaikan', 'Usul: tambah dispenser air panas di pantry lantai 3. Yang sering lembur pasti terbantu.', 12, 3, 0],
        ['Ide Perbaikan', 'Gimana kalau form reimbursement bisa upload foto struk langsung dari kamera? Sekarang harus simpan dulu ke galeri.', 9, 4, 1],
        ['Sports Day', 'Fun run Sabtu pagi jadi ya? Yang ikut absen di sini biar gampang hitung kaosnya.', 15, 6, 1],
        ['Employee of the Month', 'Salut buat tim Finance yang lembur tutup buku minggu lalu. Kelihatan capek tapi tetap ramah.', 21, 5, 2],
        ['Ide Perbaikan', 'Ruang meeting lantai 2 sering bentrok. Mungkin perlu booking lewat aplikasi biar kelihatan siapa pakai jam berapa.', 7, 2, 3],
        ['Sports Day', 'Foto-foto badminton kemarin sudah saya upload ke drive. Seru banget, next bulan lagi dong.', 18, 4, 4],
        ['Ide Perbaikan', 'Parkiran motor penuh terus jam 8. Kalau digeser sebagian ke sisi belakang kayaknya masih muat.', 5, 1, 5],
        ['Employee of the Month', 'Terima kasih tim IT, laptop saya yang mati total kemarin beres dalam sehari.', 14, 2, 6],
        ['Sports Day', 'Ada yang mau gabung sepeda santai Minggu? Rute pendek saja, 15 km.', 11, 7, 8],
        ['Ide Perbaikan', 'Saran: notifikasi cuti disetujui masuk ke aplikasi juga, bukan cuma email. Sering kelewat.', 8, 3, 10],
    ];

    /**
     * Comment lines cycled through, so a thread reads like a conversation.
     *
     * @var array<int, string>
     */
    private const COMMENTS = [
        'Setuju banget!',
        'Ini yang saya tunggu dari kemarin.',
        'Boleh nih diusulkan ke HR.',
        'Sudah pernah dibahas belum ya?',
        'Saya ikut ya.',
        'Mantap idenya.',
        'Kalau bisa secepatnya sih.',
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->command?->warn('Belum ada tenant. Jalankan AvanaDemoSeeder dulu.');

            return;
        }

        $employees = Employee::forTenant($tenant->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'tenant_id']);

        if ($employees->isEmpty()) {
            $this->command?->warn('Belum ada karyawan aktif pada tenant ini.');

            return;
        }

        $categories = SocialCategory::forTenant($tenant->id)->get()->keyBy('name');

        foreach (self::POSTS as $index => [$categoryName, $body, $likes, $comments, $daysAgo]) {
            $author = $employees[$index % $employees->count()];
            $postedAt = Carbon::now()->subDays($daysAgo)->subHours($index);

            $post = SocialPost::firstOrCreate(
                ['tenant_id' => $tenant->id, 'body' => $body],
                [
                    'social_category_id' => $categories[$categoryName]->id ?? null,
                    'employee_id' => $author->id,
                    'status' => SocialPost::STATUS_PUBLISHED,
                    'created_at' => $postedAt,
                    'updated_at' => $postedAt,
                ],
            );

            if ($post->wasRecentlyCreated === false) {
                continue;
            }

            $this->seedLikes($post, $employees, $likes);
            $this->seedComments($post, $employees, $comments, $postedAt);
        }

        $this->command?->info('Wall siap: '.count(self::POSTS).' postingan.');
    }

    /**
     * Likes come from different employees each time — the unique index means a
     * single employee can only like a post once anyway.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function seedLikes(SocialPost $post, $employees, int $wanted): void
    {
        $count = min($wanted, $employees->count());

        for ($i = 0; $i < $count; $i++) {
            SocialPostLike::firstOrCreate([
                'social_post_id' => $post->id,
                'employee_id' => $employees[($post->id + $i) % $employees->count()]->id,
            ], ['tenant_id' => $post->tenant_id]);
        }

        $post->update(['likes_count' => $post->likes()->count()]);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedComments(SocialPost $post, $employees, int $wanted, Carbon $postedAt): void
    {
        for ($i = 0; $i < $wanted; $i++) {
            $at = $postedAt->copy()->addMinutes(($i + 1) * 37);

            SocialPostComment::create([
                'social_post_id' => $post->id,
                'employee_id' => $employees[($post->id + $i + 1) % $employees->count()]->id,
                'tenant_id' => $post->tenant_id,
                'body' => self::COMMENTS[($post->id + $i) % count(self::COMMENTS)],
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $post->update(['comments_count' => $post->comments()->count()]);
    }
}
