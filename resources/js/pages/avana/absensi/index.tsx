import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, C } from '@/lib/avana';
import { KpiStrip } from './components';
import { RekapTable } from './rekap-table';
import type { AbsensiProps, Attendance, FlashProps } from './types';

const STATUS_OPTIONS = [
    { value: 'present', label: 'Hadir' },
    { value: 'late', label: 'Terlambat' },
    { value: 'leave', label: 'Cuti / Izin' },
    { value: 'absent', label: 'Alpa' },
    { value: 'incomplete', label: 'Belum Lengkap' },
];

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

export default function AvanaAbsensi({
    attendances,
    filters,
    range,
    kpis,
    branches,
}: AbsensiProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = attendances.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
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

    const changeFilter = (
        key: 'branch_id' | 'status' | 'date_from' | 'date_to',
        value: string,
    ) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToday = () => {
        router.get(
            window.location.pathname,
            {
                ...filters,
                date_from: undefined,
                date_to: undefined,
                page: 1,
            },
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

    const openDetail = (row: Attendance) => {
        router.visit(`/avana/absensi/${row.id}`);
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
                            {range.display} ·{' '}
                            {range.is_range ? 'Rekap periode' : 'Periode harian'}
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            flexWrap: 'wrap',
                        }}
                    >
                        <select
                            value={filters.branch_id ?? ''}
                            onChange={(event) =>
                                changeFilter('branch_id', event.target.value)
                            }
                            style={filterControl}
                        >
                            <option value="">Semua cabang</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={String(branch.id)}>
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                changeFilter('status', event.target.value)
                            }
                            style={filterControl}
                        >
                            <option value="">Semua status</option>
                            {STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <DatePicker
                            label="Dari"
                            value={filters.date_from}
                            onChange={(value) =>
                                changeFilter('date_from', value)
                            }
                        />
                        <DatePicker
                            label="Sampai"
                            value={filters.date_to}
                            onChange={(value) => changeFilter('date_to', value)}
                        />
                        <button onClick={goToday} style={btnOut} type="button">
                            <AIcon name="calendar-check" size={16} />
                            Hari ini
                        </button>
                        <a
                            href={`/avana/laporan/export/absensi?date_from=${filters.date_from}&date_to=${filters.date_to}`}
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
                            onRowClick={openDetail}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
