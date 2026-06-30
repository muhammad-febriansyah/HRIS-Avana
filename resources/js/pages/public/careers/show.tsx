import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnP, C } from '@/lib/avana';
import PublicCareerController from '@/actions/App/Http/Controllers/PublicCareerController';
import type { FlashProps } from '@/pages/avana/employees/types';

interface Posting {
    id: number;
    title: string;
    department: string | null;
    location: string | null;
    employment_type: string;
    description: string | null;
    posted_date: string | null;
    closing_date: string | null;
}

interface CareersShowProps {
    tenant: { slug: string; name: string };
    posting: Posting;
}

const TYPE_LABEL: Record<string, string> = {
    tetap: 'Tetap',
    kontrak: 'Kontrak',
    magang: 'Magang',
    harian: 'Harian',
};

const labelStyle = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 6, color: C.text } as const;
const inputStyle = { width: '100%', height: 42, padding: '0 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.text, background: '#fff', outline: 'none' } as const;

export default function CareersShow({ tenant, posting }: CareersShowProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({ name: '', email: '', phone: '', linkedin_url: '', portfolio_url: '', notes: '' });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
            form.reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.success]);

    return (
        <>
            <Head title={`${posting.title} — ${tenant.name}`} />
            <div style={{ minHeight: '100vh', background: '#f1f5f9', padding: '32px 20px 64px' }}>
                <div style={{ maxWidth: 760, margin: '0 auto' }}>
                    <Link href={PublicCareerController.index(tenant.slug)} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 13, color: C.muted, textDecoration: 'none', marginBottom: 18 }}>
                        <AIcon name="arrow-left" size={15} color={C.muted} />
                        Semua Lowongan
                    </Link>

                    {/* posting */}
                    <div style={{ background: '#fff', borderRadius: 14, padding: 32, boxShadow: '0 1px 3px rgba(15,23,42,.08)', marginBottom: 18 }}>
                        <div style={{ fontSize: 12.5, color: C.primary, fontWeight: 600 }}>{tenant.name}</div>
                        <h1 style={{ fontSize: 26, fontWeight: 700, color: C.navy, margin: '6px 0 14px' }}>{posting.title}</h1>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, fontSize: 13, color: C.muted }}>
                            {posting.department ? <Meta icon="building-2" text={posting.department} /> : null}
                            {posting.location ? <Meta icon="map-pin" text={posting.location} /> : null}
                            <Meta icon="clock" text={TYPE_LABEL[posting.employment_type] ?? posting.employment_type} />
                            {posting.closing_date ? <Meta icon="calendar" text={`Ditutup ${posting.closing_date}`} /> : null}
                        </div>
                        {posting.description ? (
                            <div style={{ marginTop: 22, paddingTop: 22, borderTop: `1px solid ${C.line}`, fontSize: 14, color: C.text, lineHeight: 1.7, whiteSpace: 'pre-wrap' }}>
                                {posting.description}
                            </div>
                        ) : null}
                    </div>

                    {/* apply form */}
                    <div style={{ background: '#fff', borderRadius: 14, padding: 32, boxShadow: '0 1px 3px rgba(15,23,42,.08)' }}>
                        <h2 style={{ fontSize: 18, fontWeight: 600, color: C.navy, margin: '0 0 18px' }}>Lamar Posisi Ini</h2>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(PublicCareerController.apply([tenant.slug, posting.id]).url, { preserveScroll: true });
                            }}
                            style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}
                        >
                            <Field label="Nama Lengkap" error={form.errors.name}>
                                <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} style={inputStyle} />
                            </Field>
                            <Field label="Email" error={form.errors.email}>
                                <input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} style={inputStyle} />
                            </Field>
                            <Field label="Telepon" error={form.errors.phone}>
                                <input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} style={inputStyle} />
                            </Field>
                            <Field label="LinkedIn (opsional)" error={form.errors.linkedin_url}>
                                <input value={form.data.linkedin_url} onChange={(e) => form.setData('linkedin_url', e.target.value)} style={inputStyle} placeholder="https://" />
                            </Field>
                            <Field label="Portfolio (opsional)" error={form.errors.portfolio_url}>
                                <input value={form.data.portfolio_url} onChange={(e) => form.setData('portfolio_url', e.target.value)} style={inputStyle} placeholder="https://" />
                            </Field>
                            <div style={{ gridColumn: '1 / -1' }}>
                                <label style={labelStyle}>Pesan / Cover Letter (opsional)</label>
                                <textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} style={{ ...inputStyle, height: 100, padding: '11px 13px', resize: 'vertical' }} />
                            </div>
                            <div style={{ gridColumn: '1 / -1', display: 'flex', justifyContent: 'flex-end' }}>
                                <button type="submit" disabled={form.processing} style={btnP}>
                                    <AIcon name="send" size={15} />
                                    Kirim Lamaran
                                </button>
                            </div>
                        </form>
                    </div>

                    <div style={{ textAlign: 'center', marginTop: 32, fontSize: 12.5, color: C.faint }}>
                        Powered by <span style={{ fontWeight: 600, color: C.primary }}>AvanaHR</span>
                    </div>
                </div>
            </div>
        </>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label style={labelStyle}>{label}</label>
            {children}
            {error ? <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{error}</div> : null}
        </div>
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
