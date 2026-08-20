<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', News::class);

        $search = $request->string('q')->trim()->toString();
        $query = News::query()->latestFirst();

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return Inertia::render('avana/berita/index', [
            'news' => $query->paginate(6)->withQueryString()->through(fn (News $news): array => $this->transform($news)),
            'filters' => ['q' => $search],
        ]);
    }

    public function show(News $news): Response
    {
        $this->authorize('view', $news);

        return Inertia::render('avana/berita/show', [
            'news' => $this->transform($news),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', News::class);
        $data = $this->validateNews($request);
        unset($data['image'], $data['remove_image']);
        $data['body'] = HtmlSanitizer::clean($data['body']);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);
        $data['image_path'] = $this->storeImage($request->file('image'));
        $data['published_at'] = $data['status'] === 'published' ? now() : null;
        News::create($data);

        return back()->with('success', 'Berita berhasil dibuat');
    }

    public function update(Request $request, News $news): RedirectResponse
    {
        $this->authorize('update', $news);
        $data = $this->validateNews($request, $news);
        unset($data['image'], $data['remove_image']);
        $data['body'] = HtmlSanitizer::clean($data['body']);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title'], $news);

        if ($request->file('image') instanceof UploadedFile) {
            $news->deleteImage();
            $data['image_path'] = $this->storeImage($request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $news->deleteImage();
            $data['image_path'] = null;
        }

        $data['published_at'] = $data['status'] === 'published' ? ($news->published_at ?? now()) : null;
        $news->update($data);

        return back()->with('success', 'Berita berhasil diperbarui');
    }

    public function destroy(News $news): RedirectResponse
    {
        $this->authorize('delete', $news);
        $news->deleteImage();
        $news->delete();

        return back()->with('success', 'Berita berhasil dihapus');
    }

    /** @return array<string, mixed> */
    private function validateNews(Request $request, ?News $news = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:news,slug,'.($news?->id ?? 'NULL')],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:draft,published'],
            'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ], ['image.max' => 'Gambar maksimal 5 MB.']);
    }

    private function uniqueSlug(string $value, ?News $news = null): string
    {
        $base = Str::slug($value) ?: 'berita';
        $slug = $base;
        $suffix = 2;

        while (News::where('slug', $slug)->when($news, fn ($query) => $query->where('id', '!=', $news->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        return $image?->store('news', News::DISK);
    }

    /** @return array<string, mixed> */
    private function transform(News $news): array
    {
        return [
            'id' => $news->id, 'title' => $news->title, 'slug' => $news->slug,
            'excerpt' => $news->excerpt, 'body' => $news->body, 'category' => $news->category,
            'status' => $news->status, 'is_featured' => $news->is_featured,
            'published_at' => $news->published_at?->toDateTimeString(), 'image_url' => $news->imageUrl(),
        ];
    }
}
