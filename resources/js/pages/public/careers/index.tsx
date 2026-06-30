import { Head, Link } from '@inertiajs/react';
import { AIcon, C } from '@/lib/avana';
import PublicCareerController from '@/actions/App/Http/Controllers/PublicCareerController';

interface PostingCard {
    id: number;
    title: string;
    department: string | null;
    location: string | null;
    employment_type: string;
    description: string | null;
    posted_date: string | null;
    closing_date: string | null;
}

interface CareersIndexProps {
    tenant: { slug: string; name: string };
    postings: PostingCard[];
}

const TYPE_LABEL: Record<string, string> = {
    tetap: 'Tetap',
    kontrak: 'Kontrak',
    magang: 'Magang',
    harian: 'Harian',
};

export default function CareersIndex({ tenant, postings }: CareersIndexProps) {
    return (
        <>
            <Head title={`Karir di ${tenant.name}`} />
            <div style={{ minHeight: '100vh', background: '#f1f5f9' }}>
                {/* hero */}
                <div style={{ background: `linear-gradient(135deg, ${C.primary}, #1e3a8a)`, color: '#fff', padding: '64px 20px 72px' }}>
                    <div style={{ maxWidth: 880, margin: '0 auto' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 9, fontSize: 13, opacity: 0.85, marginBottom: 18 }}>
                            <AIcon name="briefcase" size={16} color="#fff" />
                            {tenant.name}
                        </div>
                        <h1 style={{ fontSize: 38, fontWeight: 700, margin: 0, letterSpacing: '-.02em' }}>Bergabung Bersama Kami</h1>
                        <p style={{ fontSize: 16, opacity: 0.9, marginTop: 12, maxWidth: 560, lineHeight: 1.6 }}>
                            Temukan peran yang tepat untukmu. Lihat lowongan terbuka di {tenant.name} dan lamar langsung.
                        </p>
                    </div>
                </div>

                {/* postings */}
                <div style={{ maxWidth: 880, margin: '-40px auto 0', padding: '0 20px 64px' }}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        {postings.length === 0 ? (
                            <div style={{ background: '#fff', borderRadius: 14, padding: 56, textAlign: 'center', boxShadow: '0 1px 3px rgba(15,23,42,.08)' }}>
                                <AIcon name="search-x" size={32} color={C.border} />
                                <div style={{ fontSize: 15, color: C.muted, marginTop: 12 }}>Belum ada lowongan terbuka saat ini.</div>
                            </div>
                        ) : (
                            postings.map((posting) => (
                                <Link
                                    key={posting.id}
                                    href={PublicCareerController.show([tenant.slug, posting.id])}
                                    style={{ display: 'block', background: '#fff', borderRadius: 14, padding: 24, boxShadow: '0 1px 3px rgba(15,23,42,.08)', textDecoration: 'none', transition: 'box-shadow .15s' }}
                                >
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16 }}>
                                        <div>
                                            <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>{posting.title}</div>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginTop: 10, fontSize: 13, color: C.muted }}>
                                                {posting.department ? <Meta icon="building-2" text={posting.department} /> : null}
                                                {posting.location ? <Meta icon="map-pin" text={posting.location} /> : null}
                                                <Meta icon="clock" text={TYPE_LABEL[posting.employment_type] ?? posting.employment_type} />
                                            </div>
                                        </div>
                                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: C.primary, fontSize: 13.5, fontWeight: 600, whiteSpace: 'nowrap' }}>
                                            Lamar <AIcon name="arrow-right" size={15} color={C.primary} />
                                        </span>
                                    </div>
                                </Link>
                            ))
                        )}
                    </div>

                    <div style={{ textAlign: 'center', marginTop: 40, fontSize: 12.5, color: C.faint }}>
                        Powered by <span style={{ fontWeight: 600, color: C.primary }}>AvanaHR</span>
                    </div>
                </div>
            </div>
        </>
    );
}

function Meta({ icon, text }: { icon: string; text: string }) {
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
            <AIcon name={icon} size={14} color={C.faint} />
            {text}
        </span>
    );
}
