import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AIcon, C } from '@/lib/avana';
import { EmptyState, PageHeader, PageShell, Panel } from './components';

interface SopRow {
    id: number;
    title: string;
    code: string | null;
    category: string;
    summary: string | null;
    version: string | null;
    visibility: 'public' | 'private';
    effective_date: string | null;
    file_name: string | null;
    has_file: boolean;
}

interface SayaSopProps {
    sops: SopRow[];
    categories: string[];
}

export default function SayaSop({ sops, categories }: SayaSopProps) {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');

    const visible = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        return sops.filter((sop) => {
            if (category && sop.category !== category) {
                return false;
            }

            if (!keyword) {
                return true;
            }

            return [sop.title, sop.code, sop.summary]
                .filter(Boolean)
                .some((field) => String(field).toLowerCase().includes(keyword));
        });
    }, [sops, search, category]);

    return (
        <>
            <Head title="SOP Perusahaan" />
            <PageShell>
                <PageHeader
                    title="SOP Perusahaan"
                    subtitle="Prosedur resmi yang berlaku untuk Anda. Unduh PDF-nya, atau tanyakan langsung ke AI Assistant."
                />

                <Panel padded={false}>
                    <div
                        style={{
                            display: 'flex',
                            gap: 10,
                            flexWrap: 'wrap',
                            padding: '14px 16px',
                            borderBottom: `1px solid ${C.line}`,
                        }}
                    >
                        <input
                            name="cari_sop"
                            aria-label="Cari SOP"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari SOP…"
                            style={{
                                flex: '1 1 220px',
                                height: 40,
                                padding: '0 13px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13.5,
                                color: C.text,
                                outline: 'none',
                            }}
                        />
                        <select
                            name="filter_jenis"
                            aria-label="Filter jenis SOP"
                            value={category}
                            onChange={(event) =>
                                setCategory(event.target.value)
                            }
                            style={{
                                height: 40,
                                padding: '0 13px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13.5,
                                color: C.text,
                                background: '#fff',
                                cursor: 'pointer',
                            }}
                        >
                            <option value="">Semua jenis</option>
                            {categories.map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {visible.length === 0 ? (
                        <div style={{ padding: 20 }}>
                            <EmptyState
                                icon="book-open"
                                message={
                                    sops.length === 0
                                        ? 'Belum ada SOP yang dipublikasikan untuk Anda.'
                                        : 'Tidak ada SOP yang cocok dengan pencarian.'
                                }
                            />
                        </div>
                    ) : (
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(300px, 1fr))',
                                gap: 12,
                                padding: 16,
                            }}
                        >
                            {visible.map((sop) => (
                                <div
                                    key={sop.id}
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 8,
                                        padding: 14,
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 12,
                                        background: '#fff',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'flex-start',
                                            gap: 10,
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: 34,
                                                height: 34,
                                                flex: 'none',
                                                borderRadius: 9,
                                                display: 'grid',
                                                placeItems: 'center',
                                                background:
                                                    'rgba(47,84,201,.09)',
                                            }}
                                        >
                                            <AIcon
                                                name="book-open"
                                                size={17}
                                                color={C.primary}
                                            />
                                        </div>
                                        <div style={{ minWidth: 0, flex: 1 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    lineHeight: 1.35,
                                                }}
                                            >
                                                {sop.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {sop.category}
                                                {sop.code
                                                    ? ` · ${sop.code}`
                                                    : ''}
                                                {sop.version
                                                    ? ` · v${sop.version}`
                                                    : ''}
                                            </div>
                                        </div>
                                    </div>

                                    {sop.summary ? (
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: C.muted,
                                                lineHeight: 1.5,
                                            }}
                                        >
                                            {sop.summary}
                                        </div>
                                    ) : null}

                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 8,
                                            marginTop: 'auto',
                                            paddingTop: 6,
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                            }}
                                        >
                                            {sop.effective_date
                                                ? `Berlaku ${sop.effective_date}`
                                                : 'Tanpa tanggal berlaku'}
                                        </span>
                                        {sop.has_file ? (
                                            <a
                                                href={`/avana/saya/sop/${sop.id}/unduh`}
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    height: 30,
                                                    padding: '0 12px',
                                                    borderRadius: 8,
                                                    border: `1px solid ${C.border}`,
                                                    background: '#fff',
                                                    fontSize: 12,
                                                    fontWeight: 600,
                                                    color: C.primary,
                                                    textDecoration: 'none',
                                                }}
                                            >
                                                <AIcon
                                                    name="download"
                                                    size={13}
                                                    color={C.primary}
                                                />
                                                Unduh
                                            </a>
                                        ) : null}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>
            </PageShell>
        </>
    );
}
