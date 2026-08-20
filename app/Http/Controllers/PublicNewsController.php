<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public-facing news listing/detail — no auth, published articles only.
 *
 * Separate from `Avana\NewsController`, which is the super-admin CMS gated
 * by `NewsPolicy`. This controller never touches drafts.
 */
class PublicNewsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->toString();
        $query = News::query()->where('status', 'published')->latestFirst();

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return Inertia::render('public/berita/index', [
            'news' => $query->paginate(6)->withQueryString()->through(fn (News $news): array => $this->transform($news)),
            'filters' => ['q' => $search],
        ]);
    }

    public function show(News $news): Response
    {
        abort_unless($news->status === 'published', 404);

        $related = News::query()
            ->where('status', 'published')
            ->where('id', '!=', $news->id)
            ->latestFirst()
            ->limit(3)
            ->get()
            ->map(fn (News $item): array => $this->transform($item));

        return Inertia::render('public/berita/show', [
            'news' => [...$this->transform($news), 'url' => route('berita.show', $news)],
            'related' => $related,
        ]);
    }

    /** @return array<string, mixed> */
    private function transform(News $news): array
    {
        return [
            'id' => $news->id,
            'title' => $news->title,
            'slug' => $news->slug,
            'excerpt' => $news->excerpt,
            'body' => $news->body,
            'category' => $news->category,
            'is_featured' => $news->is_featured,
            'published_at' => $news->published_at?->toDateTimeString(),
            'image_url' => $news->imageUrl(),
        ];
    }
}
