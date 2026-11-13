import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import { BulkCorrectionModal } from './bulk-correction-modal';
import type { BulkTarget } from './bulk-correction-modal';
import { KpiStrip } from './components';
import { CorrectionModal } from './correction-modal';
import { RekapTable, rowKey } from './rekap-table';
import type { RowSelection } from './rekap-table';
import type { AbsensiProps, Attendance, FlashProps } from './types';

const STATUS_OPTIONS = [
    { value: 'present', label: 'Hadir' },
    { value: 'late', label: 'Terlambat' },
    { value: 'leave', label: 'Cuti / Izin' },
    { value: 'absent', label: 'Alpa' },
    { value: 'incomplete', label: 'Belum Lengkap' },
    { value: 'not_in', label: 'Belum Absen / Belum Tercatat' },
];

/** Mirrors AttendanceController::BULK_CORRECTION_LIMIT. */
const BULK_LIMIT = 500;

type SelectedRow = { employee_id: number; date: string };

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
    can_correct: canCorrect,
    today,
}: AbsensiProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = attendances.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [correcting, setCorrecting] = useState<Attendance | null>(null);
    const [selected, setSelected] = useState<Map<string, SelectedRow>>(
        () => new Map(),
    );
    // Every row the filters show, across pages — resolved by the server.
    const [allMatching, setAllMatching] = useState(false);
    const [bulkTarget, setBulkTarget] = useState<BulkTarget | null>(null);
    const isFirstSearch = useRef(true);

    const clearSelection = () => {
        setSelected(new Map());
        setAllMatching(false);
    };

    // A ticked row belongs to the filters it was ticked under; a new filter
    // is a new list. Paging keeps the ticks so HR can pick across pages.
    // Reset while rendering (React's "adjust state on prop change"), not in
    // an effect, so the stale ticks never paint.
    const filterKey = [
        filters.date_from,
        filters.date_to,
        filters.status,
        filters.branch_id,
        filters.search,
    ].join('|');
    const [selectionFilterKey, setSelectionFilterKey] = useState(filterKey);

    if (selectionFilterKey !== filterKey) {
        setSelectionFilterKey(filterKey);
        clearSelection();
    }

    const selection = useMemo<RowSelection>(
        () => ({
            isSelected: (row) => allMatching || selected.has(rowKey(row)),
            toggle: (row) => {
                if (row.employee === null || row.date_raw === null) {
                    return;
                }

                setAllMatching(false);
                setSelected((current) => {
                    const next = new Map(current);
                    const key = rowKey(row);

                    if (next.has(key)) {
                        next.delete(key);
                    } else {
                        next.set(key, {
                            employee_id: row.employee!.id,
                            date: row.date_raw!,
                        });
                    }

                    return next;
                });
            },
            togglePage: (rows, select) => {
                setAllMatching(false);
                setSelected((current) => {
                    const next = new Map(current);

                    rows.forEach((row) => {
                        if (row.employee === null || row.date_raw === null) {
                            return;
                        }

                        if (select) {
                            next.set(rowKey(row), {
                                employee_id: row.employee.id,
                                date: row.date_raw,
                            });
                        } else {
                            next.delete(rowKey(row));
                        }
                    });

                    return next;
                });
            },
        }),
        [selected, allMatching],
    );

    const selectedCount = allMatching ? meta.total : selected.size;
    const pageRows = attendances.data.filter((row) => row.employee !== null);
    const pageFullySelected =
        pageRows.length > 0 &&
        pageRows.every((row) => selected.has(rowKey(row)));

    const openBulk = () => {
        setBulkTarget(
            allMatching
                ? { kind: 'filter', total: meta.total, filters }
                : { kind: 'items', items: Array.from(selected.values()) },
        );
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    // A correction inside a locked payroll period: the payslip will not
    // follow, so the note has to stay up long enough to act on.
    useEffect(() => {
        if (flash?.warning) {
            toast.warning(flash.warning, {
                id: flash.warning,
                duration: 12000,
            });
        }
    }, [flash?.warning]);

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

    const changePerPage = (perPage: number) => {
        router.get(
            window.location.pathname,
            { ...filters, per_page: String(perPage), page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
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
                            {range.is_range
                                ? 'Rekap periode'
                                : 'Periode harian'}
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
                                <option
                                    key={branch.id}
                                    value={String(branch.id)}
                                >
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
                        <KpiStrip
                            kpis={kpis}
                            onShowNotYetIn={() =>
                                changeFilter('status', 'not_in')
                            }
                        />

                        {canCorrect && selectedCount > 0 && (
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    flexWrap: 'wrap',
                                    gap: 10,
                                    padding: '10px 14px',
                                    background: '#EEF2FF',
                                    border: `1px solid ${C.primary}33`,
                                    borderRadius: 10,
                                    fontSize: 13.5,
                                    color: C.text,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        flexWrap: 'wrap',
                                        gap: 8,
                                    }}
                                >
                                    <strong>
                                        {selectedCount.toLocaleString('id-ID')}{' '}
                                        baris dipilih
                                        {allMatching
                                            ? ' (semua sesuai filter)'
                                            : ''}
                                    </strong>
                                    {!allMatching &&
                                        pageFullySelected &&
                                        meta.total > selected.size && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setAllMatching(true)
                                                }
                                                style={{
                                                    background: 'none',
                                                    border: 'none',
                                                    padding: 0,
                                                    color: C.primary,
                                                    fontWeight: 600,
                                                    cursor: 'pointer',
                                                    fontSize: 13.5,
                                                }}
                                            >
                                                Pilih semua{' '}
                                                {meta.total.toLocaleString(
                                                    'id-ID',
                                                )}{' '}
                                                baris sesuai filter
                                            </button>
                                        )}
                                    {selectedCount > BULK_LIMIT && (
                                        <span style={{ color: C.red }}>
                                            Maksimal {BULK_LIMIT} baris sekali
                                            koreksi — persempit filternya.
                                        </span>
                                    )}
                                </div>
                                <div style={{ display: 'flex', gap: 8 }}>
                                    <button
                                        type="button"
                                        onClick={clearSelection}
                                        style={{ ...btnOut, height: 36 }}
                                    >
                                        Batal pilih
                                    </button>
                                    <button
                                        type="button"
                                        onClick={openBulk}
                                        disabled={selectedCount > BULK_LIMIT}
                                        style={{
                                            ...btnP,
                                            height: 36,
                                            opacity:
                                                selectedCount > BULK_LIMIT
                                                    ? 0.5
                                                    : 1,
                                        }}
                                    >
                                        <AIcon
                                            name="pencil"
                                            size={15}
                                            color="#fff"
                                        />
                                        Koreksi Terpilih
                                    </button>
                                </div>
                            </div>
                        )}

                        <RekapTable
                            rows={attendances.data}
                            meta={meta}
                            search={search}
                            onSearchChange={setSearch}
                            onGoToPage={goToPage}
                            onRowClick={openDetail}
                            onCorrect={canCorrect ? setCorrecting : undefined}
                            selection={canCorrect ? selection : undefined}
                            onPerPage={changePerPage}
                        />
                    </div>
                </div>
            </div>

            {bulkTarget !== null && (
                <BulkCorrectionModal
                    target={bulkTarget}
                    onClose={() => setBulkTarget(null)}
                    onDone={clearSelection}
                />
            )}

            {correcting !== null && (
                <CorrectionModal
                    row={correcting}
                    today={today}
                    onClose={() => setCorrecting(null)}
                />
            )}
        </>
    );
}
