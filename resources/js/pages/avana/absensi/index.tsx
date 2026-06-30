import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, C } from '@/lib/avana';
import { KpiStrip } from './components';
import { RekapTable } from './rekap-table';
import type { AbsensiProps, FlashProps } from './types';

export default function AvanaAbsensi({
    attendances,
    filters,
    date,
    kpis,
}: AbsensiProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = attendances.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const changeDate = (value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, date: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Absensi" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
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
                            <span style={{ color: C.muted }}>Absensi</span>
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
                            Absensi
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {date.display} · Periode harian
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <input
                            type="date"
                            value={filters.date}
                            onChange={(event) => changeDate(event.target.value)}
                            style={{
                                height: 40,
                                padding: '0 12px',
                                background: '#fff',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13.5,
                                color: C.text,
                                outline: 'none',
                                cursor: 'pointer',
                            }}
                        />
                        <a
                            href="/avana/laporan/export/absensi"
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="download" size={16} />
                            Export Rekap
                        </a>
                    </div>
                </div>

                <div>
                    {/* Rekap */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <KpiStrip kpis={kpis} />

                        <RekapTable
                            rows={attendances.data}
                            meta={meta}
                            search={search}
                            onSearchChange={setSearch}
                            onGoToPage={goToPage}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
