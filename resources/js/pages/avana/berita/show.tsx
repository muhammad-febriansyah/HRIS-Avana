import { Head, Link } from '@inertiajs/react';
import NewsController from '@/actions/App/Http/Controllers/Avana/NewsController';
import { AIcon, btnOut, C, card } from '@/lib/avana';

type News = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    category: string | null;
    status: 'draft' | 'published';
    is_featured: boolean;
    published_at: string | null;
    image_url: string | null;
};

export default function Show({ news }: { news: News }) {
    return (
        <>
            <Head title={news.title} />
            <div
                style={{
                    width: '100%',
                    maxWidth: 'none',
                    boxSizing: 'border-box',
                    padding: '28px 32px 56px',
                }}
            >
                <Link
                    href={NewsController.index().url}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 7,
                        color: C.muted,
                        fontSize: 13,
                        textDecoration: 'none',
                        marginBottom: 20,
                    }}
                >
                    <AIcon name="arrow-left" size={15} /> Kembali ke Berita
                </Link>
                <article style={{ ...card, overflow: 'hidden' }}>
                    {news.image_url && (
                        <img
                            src={news.image_url}
                            alt={news.title}
                            style={{
                                display: 'block',
                                width: '100%',
                                height: 'min(420px, 32vw)',
                                minHeight: 240,
                                objectFit: 'cover',
                                objectPosition: 'center',
                            }}
                        />
                    )}
                    <div
                        style={{ padding: '30px clamp(20px, 6vw, 72px) 54px' }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                alignItems: 'center',
                                flexWrap: 'wrap',
                                marginBottom: 14,
                            }}
                        >
                            <span
                                style={{
                                    color: C.primary,
                                    fontSize: 12,
                                    fontWeight: 600,
                                }}
                            >
                                {news.category ?? 'Berita'}
                            </span>
                            <span style={{ color: C.faint }}>·</span>
                            <span
                                style={{
                                    color:
                                        news.status === 'published'
                                            ? C.green
                                            : C.muted,
                                    fontSize: 12,
                                    fontWeight: 600,
                                }}
                            >
                                {news.status === 'published'
                                    ? 'Terbit'
                                    : 'Draft'}
                            </span>
                            {news.is_featured && (
                                <span
                                    style={{
                                        color: C.amber,
                                        fontSize: 12,
                                        fontWeight: 600,
                                    }}
                                >
                                    · Unggulan
                                </span>
                            )}
                        </div>
                        <h1
                            style={{
                                margin: 0,
                                color: C.navy,
                                fontSize: 'clamp(28px, 5vw, 46px)',
                                lineHeight: 1.1,
                                letterSpacing: '-.025em',
                            }}
                        >
                            {news.title}
                        </h1>
                        {news.excerpt && (
                            <p
                                style={{
                                    color: C.muted,
                                    fontSize: 17,
                                    lineHeight: 1.6,
                                    margin: '18px 0 0',
                                }}
                            >
                                {news.excerpt}
                            </p>
                        )}
                        <div
                            className="avn-rte article-body"
                            dangerouslySetInnerHTML={{ __html: news.body }}
                        />
                    </div>
                </article>
                <Link
                    href={NewsController.index().url}
                    style={{ ...btnOut, marginTop: 18, textDecoration: 'none' }}
                >
                    <AIcon name="arrow-left" size={15} /> Kembali ke daftar
                </Link>
            </div>
        </>
    );
}
