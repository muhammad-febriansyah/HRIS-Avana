import { Head, Link, router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { AIcon, btnOut, C, card } from '@/lib/avana';
import { CategoryChip, KpiCard, ScoreBar } from './components';
import type { Category } from './components';

interface Row {
    id: number;
    name: string;
    employee_number: string | null;
    department: string | null;
    branch: string | null;
    position: string | null;
    initials: string;
    avatar_color: string;
    score: number;
    category: Category;
    coverage: number;
    top_factors: string[];
}

interface AttritionProps {
    rows: Row[];
    kpis: {
        total: number;
        high: number;
        medium: number;
        low: number;
        avg: number;
    };
    filters: { search: string | null; category: Category | null };
}

const filterControl: CSSProperties = {
    height: 40,
    padding: '0 12px',
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
};

const CATEGORIES = [
    { value: '', label: 'Semua' },
    { value: 'high', label: 'Tinggi' },
    { value: 'medium', label: 'Sedang' },
    { value: 'low', label: 'Rendah' },
];

const HEADERS = [
    'Karyawan',
    'Departemen',
    'Skor Risiko',
    'Kategori',
    'Faktor Utama',
];

export default function AttritionIndex({
    rows,
    kpis,
    filters,
}: AttritionProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirst = useRef(true);

    useEffect(() => {
        if (isFirst.current) {
            isFirst.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const setCategory = (value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, category: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Prediksi Risiko Resign" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-end',
                        flexWrap: 'wrap',
                        gap: 12,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Prediksi Resign
                            </span>
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Prediksi Risiko Resign
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Skor risiko keluar tiap karyawan dari 9 faktor ·
                            model scoring
                        </div>
                    </div>
                    <Link
                        href="/avana/attrition/settings"
                        style={{ ...btnOut, textDecoration: 'none' }}
                    >
                        <AIcon name="settings" size={16} />
                        Pengaturan
                    </Link>
                </div>

                <div
                    className="avn-kpi"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(5,1fr)',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <KpiCard
                        label="Total Karyawan"
                        value={kpis.total}
                        icon="users"
                        color={C.primary}
                    />
                    <KpiCard
                        label="Risiko Tinggi"
                        value={kpis.high}
                        icon="trending-up"
                        color={C.red}
                    />
                    <KpiCard
                        label="Risiko Sedang"
                        value={kpis.medium}
                        icon="minus"
                        color={C.amber}
                    />
                    <KpiCard
                        label="Risiko Rendah"
                        value={kpis.low}
                        icon="trending-down"
                        color={C.green}
                    />
                    <KpiCard
                        label="Skor Rata-rata"
                        value={kpis.avg}
                        icon="activity"
                        color={C.navy}
                    />
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 10,
                        marginBottom: 14,
                        flexWrap: 'wrap',
                        alignItems: 'center',
                    }}
                >
                    <div style={{ display: 'flex', gap: 8 }}>
                        {CATEGORIES.map((c) => {
                            const active = (filters.category ?? '') === c.value;

                            return (
                                <button
                                    key={c.value}
                                    onClick={() => setCategory(c.value)}
                                    type="button"
                                    style={{
                                        height: 40,
                                        padding: '0 16px',
                                        borderRadius: 100,
                                        border: 'none',
                                        cursor: 'pointer',
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: active ? '#fff' : C.muted,
                                        background: active
                                            ? C.primary
                                            : C.surface,
                                    }}
                                >
                                    {c.label}
                                </button>
                            );
                        })}
                    </div>
                    <div style={{ flex: 1 }} />
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama / NIK…"
                        style={{ ...filterControl, minWidth: 220 }}
                    />
                </div>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {rows.length === 0 ? (
                        <div
                            style={{
                                padding: 40,
                                textAlign: 'center',
                                color: C.faint,
                                fontSize: 13,
                            }}
                        >
                            Tidak ada data yang cocok.
                        </div>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                }}
                            >
                                <thead>
                                    <tr style={{ background: C.surface }}>
                                        {HEADERS.map((h) => (
                                            <th
                                                key={h}
                                                style={{
                                                    textAlign: 'left',
                                                    padding: '12px 16px',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.muted,
                                                    textTransform: 'uppercase',
                                                    letterSpacing: '.03em',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((r) => (
                                        <tr
                                            key={r.id}
                                            onClick={() =>
                                                router.visit(
                                                    `/avana/attrition/${r.id}`,
                                                )
                                            }
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            <td
                                                style={{ padding: '12px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 11,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            width: 36,
                                                            height: 36,
                                                            borderRadius: 9,
                                                            flex: 'none',
                                                            display: 'flex',
                                                            alignItems:
                                                                'center',
                                                            justifyContent:
                                                                'center',
                                                            background:
                                                                r.avatar_color,
                                                            color: '#fff',
                                                            fontSize: 12.5,
                                                            fontWeight: 700,
                                                        }}
                                                    >
                                                        {r.initials}
                                                    </div>
                                                    <div>
                                                        <div
                                                            style={{
                                                                fontSize: 13.5,
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {r.name}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 12,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {r.employee_number}{' '}
                                                            ·{' '}
                                                            {r.position ?? '—'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                }}
                                            >
                                                {r.department ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    minWidth: 180,
                                                }}
                                            >
                                                <ScoreBar
                                                    score={r.score}
                                                    category={r.category}
                                                />
                                            </td>
                                            <td
                                                style={{ padding: '12px 16px' }}
                                            >
                                                <CategoryChip
                                                    category={r.category}
                                                />
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12,
                                                    color: C.muted,
                                                    maxWidth: 280,
                                                }}
                                            >
                                                {r.top_factors.length > 0 ? (
                                                    r.top_factors.join(' · ')
                                                ) : (
                                                    <span
                                                        style={{
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
