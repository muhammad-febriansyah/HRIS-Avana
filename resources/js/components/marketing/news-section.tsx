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
 * shown as a 3-column card grid. Renders nothing when there's no published
 * article yet — never shows an empty section on a fresh install.
 */
export function NewsSection({ news }: { news: NewsItem[] }) {
    if (news.length === 0) {
        return null;
    }

    const [featured, ...rest] = news;

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

                <div className="mt-12 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Reveal delay={0.05} className="lg:col-span-2 lg:row-span-2">
                        <Link
                            href={`/berita/${featured.slug}`}
                            className="group flex h-full flex-col overflow-hidden rounded-3xl border border-avana-border bg-white shadow-avana-card transition-shadow duration-300 hover:shadow-avana-hover"
                        >
                            <div className="relative aspect-[16/9] w-full overflow-hidden bg-avana-soft lg:aspect-[16/10]">
                                {featured.image_url ? (
                                    <img
                                        src={featured.image_url}
                                        alt={featured.title}
                                        loading="lazy"
                                        className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-avana-muted">
                                        AvanaHR
                                    </div>
                                )}
                                {featured.category && (
                                    <span className="absolute top-4 left-4 rounded-full bg-avana-navy/90 px-3 py-1 text-[11px] font-bold tracking-wide text-white uppercase backdrop-blur">
                                        {featured.category}
                                    </span>
                                )}
                            </div>
                            <div className="flex flex-1 flex-col p-6 sm:p-7">
                                {formatDate(featured.published_at) && (
                                    <span className="flex items-center gap-1.5 text-xs font-medium text-avana-muted">
                                        <CalendarDays
                                            className="h-3.5 w-3.5"
                                            aria-hidden
                                        />
                                        {formatDate(featured.published_at)}
                                    </span>
                                )}
                                <h3 className="mt-3 text-xl leading-snug font-bold text-avana-navy transition-colors group-hover:text-avana-blue sm:text-2xl">
                                    {featured.title}
                                </h3>
                                {featured.excerpt && (
                                    <p className="mt-3 line-clamp-2 text-[14.5px] leading-relaxed text-avana-text/80 sm:text-[15px]">
                                        {featured.excerpt}
                                    </p>
                                )}
                                <span className="mt-auto inline-flex items-center gap-1.5 pt-5 text-[13.5px] font-semibold text-avana-blue">
                                    Baca selengkapnya
                                    <ArrowRight
                                        className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                                        aria-hidden
                                    />
                                </span>
                            </div>
                        </Link>
                    </Reveal>

                    {rest.slice(0, 4).map((item, i) => (
                        <Reveal key={item.id} delay={0.08 + i * 0.05}>
                            <Link
                                href={`/berita/${item.slug}`}
                                className="group flex h-full gap-4 rounded-2xl border border-avana-border bg-white p-4 shadow-sm transition-shadow duration-300 hover:shadow-lift"
                            >
                                <div className="relative h-20 w-24 shrink-0 overflow-hidden rounded-xl bg-avana-soft sm:h-24 sm:w-28">
                                    {item.image_url ? (
                                        <img
                                            src={item.image_url}
                                            alt={item.title}
                                            loading="lazy"
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        />
                                    ) : (
                                        <div className="flex h-full w-full items-center justify-center text-[10px] text-avana-muted">
                                            AvanaHR
                                        </div>
                                    )}
                                </div>
                                <div className="flex min-w-0 flex-1 flex-col">
                                    {item.category && (
                                        <span className="text-[11px] font-bold tracking-wide text-avana-blue uppercase">
                                            {item.category}
                                        </span>
                                    )}
                                    <h4 className="mt-1 line-clamp-2 text-[14px] leading-snug font-semibold text-avana-navy transition-colors group-hover:text-avana-blue sm:text-[15px]">
                                        {item.title}
                                    </h4>
                                    {formatDate(item.published_at) && (
                                        <span className="mt-auto pt-2 text-[11.5px] text-avana-muted">
                                            {formatDate(item.published_at)}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        </Reveal>
                    ))}
                </div>
            </Container>
        </section>
    );
}
