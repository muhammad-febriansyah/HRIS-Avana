<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\News;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        $news = News::query()
            ->where('status', 'published')
            ->latestFirst()
            ->limit(3)
            ->get()
            ->map(fn (News $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'excerpt' => $item->excerpt,
                'category' => $item->category,
                'is_featured' => $item->is_featured,
                'published_at' => $item->published_at?->toDateTimeString(),
                'image_url' => $item->imageUrl(),
            ]);

        $faqs = Faq::query()
            ->latest('id')
            ->get(['id', 'question', 'answer'])
            ->map(fn (Faq $faq): array => [
                'q' => $faq->question,
                'a' => $faq->answer,
            ])
            ->values();

        return Inertia::render('welcome', [
            'news' => $news,
            'faqs' => $faqs,
        ]);
    }
}
