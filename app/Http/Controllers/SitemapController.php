<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * SEO sitemap — static marketing pages plus every published news article.
 * Cached because the news list is scanned on every crawl.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function build(): string
    {
        $urls = [
            ['loc' => URL::route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => URL::route('live-tracking'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => URL::route('security'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => URL::route('partnership'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => URL::route('partner-registration.create'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => URL::route('referral.lead.create'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => URL::route('berita'), 'changefreq' => 'daily', 'priority' => '0.7'],
            ['loc' => URL::route('privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => URL::route('terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        News::query()
            ->where('status', 'published')
            ->latestFirst()
            ->each(function (News $news) use (&$urls): void {
                $urls[] = [
                    'loc' => URL::route('berita.show', $news),
                    'lastmod' => ($news->published_at ?? $news->updated_at)->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.e($url['loc']).'</loc>'."\n";
            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            }
            $xml .= '    <changefreq>'.$url['changefreq'].'</changefreq>'."\n";
            $xml .= '    <priority>'.$url['priority'].'</priority>'."\n";
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
