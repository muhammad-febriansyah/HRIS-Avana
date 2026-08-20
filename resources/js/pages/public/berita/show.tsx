import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays } from 'lucide-react';
import { Container, Reveal } from '@/components/marketing/reveal';
import { ShareButtons } from '@/components/marketing/share-buttons';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

type NewsItem = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    category: string | null;
    is_featured: boolean;
    published_at: string | null;
    image_url: string | null;
    url: string;
};

const DATE_FORMATTER = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function formatDate(value: string | null): string | null {
    return value ? DATE_FORMATTER.format(new Date(value)) : null;
}

/** Public article detail — read-only, published only (see `PublicNewsController`). */
export default function BeritaShow({
    news,
    related,
}: {
    news: NewsItem;
    related: NewsItem[];
}) {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';

    return (
        <>
            <Head title={`${news.title} — ${brand}`}>
                {news.excerpt && (
                    <meta name="description" content={news.excerpt} />
                )}
                <meta property="og:type" content="article" />
                <meta property="og:title" content={news.title} />
                {news.excerpt && (
                    <meta property="og:description" content={news.excerpt} />
                )}
                {news.image_url && (
                    <meta property="og:image" content={news.image_url} />
                )}
            </Head>

            <div className="min-h-dvh bg-white font-sans text-[#1A2333] antialiased">
                <SiteNavbar brand={brand} logo={logo} anchorPrefix="/" />

                <main>
                    <article className="py-14 lg:py-20">
                        <Container className="max-w-3xl">
                            <Reveal>
                                <Link
                                    href="/berita"
                                    className="inline-flex items-center gap-1.5 text-[13.5px] font-semibold text-avana-blue hover:text-avana-blue-hover"
                                >
                                    <ArrowLeft
                                        className="h-3.5 w-3.5"
                                        aria-hidden
                                    />
                                    Kembali ke Berita
                                </Link>

                                <div className="mt-5 flex flex-wrap items-center gap-3 text-xs text-avana-muted">
                                    {news.category && (
                                        <span className="rounded-full bg-avana-light px-2.5 py-1 text-[11px] font-bold tracking-wide text-avana-blue uppercase">
                                            {news.category}
                                        </span>
                                    )}
                                    {formatDate(news.published_at) && (
                                        <span className="flex items-center gap-1.5">
                                            <CalendarDays
                                                className="h-3.5 w-3.5"
                                                aria-hidden
                                            />
                                            {formatDate(news.published_at)}
                                        </span>
                                    )}
                                </div>

                                <h1 className="mt-4 text-[28px] leading-[1.2] font-bold tracking-[-0.01em] text-avana-navy sm:text-4xl">
                                    {news.title}
                                </h1>

                                {news.excerpt && (
                                    <p className="mt-4 text-[16px] leading-relaxed text-avana-text/85 sm:text-[18px]">
                                        {news.excerpt}
                                    </p>
                                )}

                                <div className="mt-6 border-t border-avana-border pt-5">
                                    <ShareButtons
                                        url={news.url}
                                        title={news.title}
                                    />
                                </div>
                            </Reveal>

                            {news.image_url && (
                                <Reveal delay={0.05}>
                                    <div className="mt-8 overflow-hidden rounded-3xl border border-avana-border">
                                        <img
                                            src={news.image_url}
                                            alt={news.title}
                                            className="h-auto w-full object-cover"
                                        />
                                    </div>
                                </Reveal>
                            )}

                            <Reveal delay={0.1}>
                                <div
                                    className="article-body mt-10"
                                    // Sanitised server-side via HtmlSanitizer before storage.
                                    dangerouslySetInnerHTML={{
                                        __html: news.body,
                                    }}
                                />
                            </Reveal>

                            <Reveal delay={0.12}>
                                <div className="mt-10 border-t border-avana-border pt-6">
                                    <ShareButtons
                                        url={news.url}
                                        title={news.title}
                                    />
                                </div>
                            </Reveal>
                        </Container>
                    </article>

                    {related.length > 0 && (
                        <section className="border-t border-[#EDF1F8] bg-avana-soft py-16 lg:py-20">
                            <Container>
                                <h2 className="text-xl font-bold text-avana-navy sm:text-2xl">
                                    Berita lainnya
                                </h2>

                                <div className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
                                    {related.map((item, i) => (
                                        <Reveal
                                            key={item.id}
                                            delay={i * 0.05}
                                        >
                                            <Link
                                                href={`/berita/${item.slug}`}
                                                className="group flex h-full flex-col overflow-hidden rounded-2xl border border-avana-border bg-white shadow-sm transition-shadow duration-300 hover:shadow-lift"
                                            >
                                                <div className="relative aspect-[16/10] w-full overflow-hidden bg-white">
                                                    {item.image_url ? (
                                                        <img
                                                            src={
                                                                item.image_url
                                                            }
                                                            alt={item.title}
                                                            loading="lazy"
                                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center text-avana-muted">
                                                            AvanaHR
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="p-4">
                                                    <h3 className="line-clamp-2 text-[14.5px] leading-snug font-bold text-avana-navy transition-colors group-hover:text-avana-blue">
                                                        {item.title}
                                                    </h3>
                                                </div>
                                            </Link>
                                        </Reveal>
                                    ))}
                                </div>
                            </Container>
                        </section>
                    )}
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />

                <WhatsAppFab />
            </div>
        </>
    );
}
