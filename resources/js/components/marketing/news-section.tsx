import { Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays } from 'lucide-react';
import { Container, Reveal, SectionHeading } from './reveal';

export type NewsItem = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string | null;
    is_featured: boolean;
    published_at: string | null;
    image_url: string | null;
};

const DATE_FORMATTER = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return DATE_FORMATTER.format(new Date(value));
}

/**
 * Latest published articles from the news/berita CMS (`App\Models\News`),
 * shown as an even 3-card grid. Renders nothing when there's no published
 * article yet — never shows an empty section on a fresh install.
 */
export function NewsSection({ news }: { news: NewsItem[] }) {
    if (news.length === 0) {
        return null;
    }

    const items = news.slice(0, 3);

    return (
        <section
            id="berita"
            className="scroll-mt-28 border-t border-[#EDF1F8] bg-white py-20 lg:py-28"
        >
            <Container>
                <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
                    <SectionHeading
                        align="left"
                        eyebrow="Berita &amp; Update"
                        title="Kabar Terbaru dari AvanaHR"
                        description="Tips HR, panduan regulasi, dan perkembangan produk — dirangkum di satu tempat."
                    />

                    <Reveal delay={0.1}>
                        <Link
                            href="/berita"
                            className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-avana-border bg-white px-4 py-2 text-[13.5px] font-semibold text-avana-navy transition-colors hover:border-avana-blue hover:text-avana-blue"
                        >
                            Lihat semua berita
                            <ArrowRight className="h-3.5 w-3.5" aria-hidden />
                        </Link>
                    </Reveal>
                </div>

                <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((item, i) => (
                        <Reveal key={item.id} delay={i * 0.06}>
                            <Link
                                href={`/berita/${item.slug}`}
                                className="group flex h-full flex-col overflow-hidden rounded-2xl border border-avana-border bg-white shadow-sm transition-shadow duration-300 hover:shadow-lift"
                            >
                                <div className="relative aspect-[16/10] w-full overflow-hidden bg-avana-soft">
                                    {item.image_url ? (
                                        <img
                                            src={item.image_url}
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
                                    {formatDate(item.published_at) && (
                                        <span className="flex items-center gap-1.5 text-xs font-medium text-avana-muted">
                                            <CalendarDays
                                                className="h-3.5 w-3.5"
                                                aria-hidden
                                            />
                                            {formatDate(item.published_at)}
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
            </Container>
        </section>
    );
}
