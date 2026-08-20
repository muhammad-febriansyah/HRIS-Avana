import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Search } from 'lucide-react';
import {  useState } from 'react';
import type {FormEvent} from 'react';
import { Container, Reveal, SectionHeading } from '@/components/marketing/reveal';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

type NewsItem = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string | null;
    is_featured: boolean;
    published_at: string | null;
    image_url: string | null;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
};

const DATE_FORMATTER = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function formatDate(value: string | null): string | null {
    return value ? DATE_FORMATTER.format(new Date(value)) : null;
}

/**
 * Public news listing — read-only, published articles only (see
 * `PublicNewsController`). Same chrome as the landing page so it reads as
 * part of the AvanaHR site rather than a separate blog.
 */
export default function BeritaIndex({
    news,
    filters,
}: {
    news: Paginated<NewsItem>;
    filters: { q: string };
}) {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const [search, setSearch] = useState(filters.q);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/berita',
            search ? { q: search } : {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={`Berita — ${brand}`}>
                <meta
                    name="description"
                    content="Tips HR, panduan regulasi, dan update produk dari AvanaHR."
                />
            </Head>

            <div className="min-h-dvh bg-white font-sans text-[#1A2333] antialiased">
                <SiteNavbar brand={brand} logo={logo} anchorPrefix="/" />

                <main>
                    <section className="border-b border-[#EDF1F8] bg-avana-soft py-16 lg:py-20">
                        <Container>
                            <SectionHeading
                                eyebrow="Berita &amp; Update"
                                title="Kabar Terbaru dari AvanaHR"
                                description="Tips HR, panduan regulasi, dan perkembangan produk — dirangkum di satu tempat."
                            />

                            <Reveal delay={0.1} className="mx-auto mt-8 max-w-md">
                                <form
                                    onSubmit={submitSearch}
                                    className="flex items-center gap-2 rounded-full border border-avana-border bg-white p-1.5 shadow-avana-subtle"
                                >
                                    <Search
                                        className="ml-3 h-4 w-4 shrink-0 text-avana-muted"
                                        aria-hidden
                                    />
                                    <input
                                        type="search"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        placeholder="Cari berita…"
                                        className="w-full bg-transparent py-1.5 text-[14px] text-avana-navy placeholder:text-avana-muted focus:outline-none"
                                    />
                                    <button
                                        type="submit"
                                        className="shrink-0 rounded-full bg-avana-blue px-4 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-avana-blue-hover"
                                    >
                                        Cari
                                    </button>
                                </form>
                            </Reveal>
                        </Container>
                    </section>

                    <section className="py-16 lg:py-20">
                        <Container>
                            {news.data.length === 0 ? (
                                <p className="py-20 text-center text-avana-muted">
                                    Belum ada berita
                                    {filters.q ? ` untuk "${filters.q}"` : ''}
                                    .
                                </p>
                            ) : (
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {news.data.map((item, i) => (
                                        <Reveal
                                            key={item.id}
                                            delay={(i % 6) * 0.05}
                                        >
                                            <Link
                                                href={`/berita/${item.slug}`}
                                                className="group flex h-full flex-col overflow-hidden rounded-2xl border border-avana-border bg-white shadow-sm transition-shadow duration-300 hover:shadow-lift"
                                            >
                                                <div className="relative aspect-[16/10] w-full overflow-hidden bg-avana-soft">
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
                                                    {item.category && (
                                                        <span className="absolute top-3 left-3 rounded-full bg-avana-navy/90 px-2.5 py-1 text-[10.5px] font-bold tracking-wide text-white uppercase backdrop-blur">
                                                            {item.category}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex flex-1 flex-col p-5">
                                                    {formatDate(
                                                        item.published_at,
                                                    ) && (
                                                        <span className="flex items-center gap-1.5 text-xs font-medium text-avana-muted">
                                                            <CalendarDays
                                                                className="h-3.5 w-3.5"
                                                                aria-hidden
                                                            />
                                                            {formatDate(
                                                                item.published_at,
                                                            )}
                                                        </span>
                                                    )}
                                                    <h3 className="mt-2.5 line-clamp-2 text-[16px] leading-snug font-bold text-avana-navy transition-colors group-hover:text-avana-blue">
                                                        {item.title}
                                                    </h3>
                                                    {item.excerpt && (
                                                        <p className="mt-2 line-clamp-2 text-[13.5px] leading-relaxed text-avana-text/80">
                                                            {item.excerpt}
                                                        </p>
                                                    )}
                                                    <span className="mt-auto inline-flex items-center gap-1.5 pt-4 text-[13px] font-semibold text-avana-blue">
                                                        Baca selengkapnya
                                                        <ArrowRight
                                                            className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                                                            aria-hidden
                                                        />
                                                    </span>
                                                </div>
                                            </Link>
                                        </Reveal>
                                    ))}
                                </div>
                            )}

                            {news.last_page > 1 && (
                                <div className="mt-12 flex flex-wrap items-center justify-center gap-2">
                                    {news.links.map((link, i) => (
                                        <Link
                                            key={`${link.label}-${i}`}
                                            href={link.url ?? '#'}
                                            preserveScroll
                                            className={`rounded-full px-3.5 py-2 text-[13.5px] font-medium transition-colors ${
                                                link.active
                                                    ? 'bg-avana-blue text-white'
                                                    : link.url
                                                      ? 'text-avana-navy hover:bg-avana-soft'
                                                      : 'cursor-not-allowed text-avana-muted/50'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ))}
                                </div>
                            )}
                        </Container>
                    </section>
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />

                <WhatsAppFab />
            </div>
        </>
    );
}
