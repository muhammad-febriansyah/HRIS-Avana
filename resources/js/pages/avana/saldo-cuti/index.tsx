import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import LeaveBalanceController from '@/actions/App/Http/Controllers/Avana/LeaveBalanceController';
import {
    AIcon,
    btnExport,
    btnOut,
    btnP,
    btnProcess,
    btnSave,
    C,
    card,
    thCell,
} from '@/lib/avana';
import type {
    BalanceCell,
    BalanceRow,
    FlashProps,
    SaldoCutiProps,
} from './types';

const input: CSSProperties = {
    height: 38,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const tdCell: CSSProperties = {
    padding: '12px 16px',
    fontSize: 13,
    color: C.text,
    verticalAlign: 'middle',
};

/** Days render without trailing zeros: 12 rather than 12.00, 12,5 stays 12,5. */
function days(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

export default function SaldoCutiIndex({
    rows,
    leaveTypes,
    departments,
    filters,
    years,
    kpis,
}: SaldoCutiProps) {
    const { flash } = usePage<FlashProps>().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<{
        employeeId: number;
        leaveTypeId: number;
    } | null>(null);
    const [draft, setDraft] = useState('');
    const [carryOpen, setCarryOpen] = useState(false);
    const [carryMax, setCarryMax] = useState('');
    const fileInput = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    /** Keep the current filters when only one of them changes. */
    const reload = (overrides: Record<string, string | number | null>) =>
        router.get(
            LeaveBalanceController.index().url,
            {
                search: search || null,
                department_id: filters.department_id || null,
                year: filters.year,
                per_page: filters.per_page,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        reload({ search: search || null });
    };

    const openEditor = (employeeId: number, cell: BalanceCell) => {
        setEditing({ employeeId, leaveTypeId: cell.leave_type_id });
        setDraft(cell.quota === null ? '' : String(cell.quota));
    };

    const saveQuota = () => {
        if (!editing) {
            return;
        }

        const quota = Number(draft.replace(',', '.'));

        if (!Number.isFinite(quota) || quota < 0) {
            toast.error('Kuota harus berupa angka hari, minimal 0.');

            return;
        }

        router.put(
            LeaveBalanceController.update().url,
            {
                employee_id: editing.employeeId,
                leave_type_id: editing.leaveTypeId,
                year: filters.year,
                quota,
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
                onError: () => toast.error('Saldo gagal disimpan.'),
            },
        );
    };

    const generate = () =>
        router.post(
            LeaveBalanceController.generate().url,
            { year: filters.year },
            { preserveScroll: true },
        );

    const carryOver = () =>
        router.post(
            LeaveBalanceController.carryOver().url,
            {
                year: filters.year,
                max_days: carryMax === '' ? null : Number(carryMax),
            },
            {
                preserveScroll: true,
                onSuccess: () => setCarryOpen(false),
            },
        );

    const importFile = (file: File) =>
        router.post(
            LeaveBalanceController.import().url,
            { year: filters.year, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(errors.file ?? 'Berkas gagal diimpor.'),
                onFinish: () => {
                    if (fileInput.current) {
                        fileInput.current.value = '';
                    }
                },
            },
        );

    return (
        <>
            <Head title="Saldo Cuti" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
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
                            <Link
                                href="/avana/cuti"
                                style={{
                                    color: C.faint,
                                    textDecoration: 'none',
                                }}
                            >
                                Cuti &amp; Lembur
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Saldo Cuti</span>
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
                            Saldo Cuti {filters.year}
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Jatah cuti tahunan tiap karyawan. Hari terpakai
                            dihitung otomatis dari cuti yang disetujui.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button onClick={generate} style={btnProcess}>
                            <AIcon
                                name="wand-sparkles"
                                size={15}
                                color="#fff"
                            />
                            Buka Saldo {filters.year}
                        </button>
                        <button
                            onClick={() => setCarryOpen(true)}
                            style={btnOut}
                        >
                            <AIcon name="arrow-right-left" size={15} />
                            Carry-over {filters.year - 1}
                        </button>
                        <a
                            href={
                                LeaveBalanceController.template().url +
                                `?year=${filters.year}`
                            }
                            style={{ ...btnExport, textDecoration: 'none' }}
                        >
                            <AIcon name="download" size={15} color="#fff" />
                            Template
                        </a>
                        <button
                            onClick={() => fileInput.current?.click()}
                            style={btnP}
                        >
                            <AIcon name="upload" size={15} color="#fff" />
                            Impor
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            style={{ display: 'none' }}
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    importFile(file);
                                }
                            }}
                        />
                    </div>
                </div>

                {/* KPI strip */}
                <div
                    className="avn-3col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <Kpi
                        label="Karyawan aktif"
                        value={kpis.employees.toLocaleString('id-ID')}
                        icon="users"
                        tone={C.primary}
                    />
                    <Kpi
                        label="Sudah punya saldo"
                        value={kpis.covered.toLocaleString('id-ID')}
                        icon="check-circle"
                        tone={C.green}
                    />
                    <Kpi
                        label="Belum punya saldo"
                        value={kpis.uncovered.toLocaleString('id-ID')}
                        icon="alert-triangle"
                        tone={kpis.uncovered > 0 ? C.amber : C.faint}
                    />
                    <Kpi
                        label="Sisa hari (semua)"
                        value={days(kpis.remaining)}
                        icon="palmtree"
                        tone={C.violet}
                    />
                </div>

                {kpis.uncovered > 0 && (
                    <div
                        role="alert"
                        style={{
                            display: 'flex',
                            gap: 10,
                            alignItems: 'flex-start',
                            marginBottom: 18,
                            padding: '12px 14px',
                            border: `1px solid ${C.amber}`,
                            borderRadius: 10,
                            background: 'rgba(217,119,6,.07)',
                            fontSize: 12.5,
                            lineHeight: 1.55,
                            color: C.text,
                        }}
                    >
                        <AIcon
                            name="alert-triangle"
                            size={17}
                            color={C.amber}
                            style={{ flex: 'none', marginTop: 1 }}
                        />
                        <div>
                            <strong>
                                {kpis.uncovered.toLocaleString('id-ID')}{' '}
                                karyawan
                            </strong>{' '}
                            belum punya saldo {filters.year}. Selama saldonya
                            kosong, layar Cuti mereka menampilkan "Belum ada
                            data saldo" dan cuti yang disetujui tidak memotong
                            jatah apa pun. Klik "Buka Saldo {filters.year}"
                            untuk membuatkan.
                        </div>
                    </div>
                )}

                {/* Filters */}
                <form
                    onSubmit={submitSearch}
                    style={{
                        display: 'flex',
                        gap: 10,
                        flexWrap: 'wrap',
                        marginBottom: 14,
                    }}
                >
                    <input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama atau nomor karyawan…"
                        style={{ ...input, minWidth: 260, flex: 1 }}
                    />
                    <select
                        name="year"
                        value={filters.year}
                        onChange={(event) =>
                            reload({ year: Number(event.target.value) })
                        }
                        style={{ ...input, cursor: 'pointer' }}
                    >
                        {years.map((year) => (
                            <option key={year} value={year}>
                                Tahun {year}
                            </option>
                        ))}
                    </select>
                    <select
                        name="department_id"
                        value={filters.department_id ?? ''}
                        onChange={(event) =>
                            reload({
                                department_id: event.target.value || null,
                            })
                        }
                        style={{ ...input, cursor: 'pointer' }}
                    >
                        <option value="">Semua divisi</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                    <button type="submit" style={{ ...btnOut, height: 38 }}>
                        <AIcon name="search" size={15} />
                        Cari
                    </button>
                </form>

                {/* Table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 720,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Divisi</th>
                                    {leaveTypes.map((type) => (
                                        <th key={type.id} style={thCell}>
                                            {type.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.data.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={leaveTypes.length + 2}
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            Tidak ada karyawan yang cocok.
                                        </td>
                                    </tr>
                                )}
                                {rows.data.map((row: BalanceRow) => (
                                    <tr
                                        key={row.employee_id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td style={tdCell}>
                                            <div
                                                style={{
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {row.name}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.faint,
                                                }}
                                            >
                                                {row.employee_number ?? '—'}
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                ...tdCell,
                                                color: C.muted,
                                            }}
                                        >
                                            {row.department ?? '—'}
                                        </td>
                                        {row.balances.map((cell) => {
                                            const isEditing =
                                                editing?.employeeId ===
                                                    row.employee_id &&
                                                editing?.leaveTypeId ===
                                                    cell.leave_type_id;

                                            return (
                                                <td
                                                    key={cell.leave_type_id}
                                                    style={tdCell}
                                                >
                                                    {isEditing ? (
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                gap: 6,
                                                                alignItems:
                                                                    'center',
                                                            }}
                                                        >
                                                            <input
                                                                autoFocus
                                                                value={draft}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDraft(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                aria-label={`Kuota ${cell.leave_type} untuk ${row.name}`}
                                                                style={{
                                                                    ...input,
                                                                    height: 32,
                                                                    width: 72,
                                                                }}
                                                            />
                                                            <button
                                                                onClick={
                                                                    saveQuota
                                                                }
                                                                title="Simpan"
                                                                style={{
                                                                    ...btnSave,
                                                                    height: 32,
                                                                    padding:
                                                                        '0 10px',
                                                                }}
                                                            >
                                                                <AIcon
                                                                    name="check"
                                                                    size={14}
                                                                    color="#fff"
                                                                />
                                                            </button>
                                                            <button
                                                                onClick={() =>
                                                                    setEditing(
                                                                        null,
                                                                    )
                                                                }
                                                                title="Batal"
                                                                style={{
                                                                    ...btnOut,
                                                                    height: 32,
                                                                    padding:
                                                                        '0 10px',
                                                                }}
                                                            >
                                                                <AIcon
                                                                    name="x"
                                                                    size={14}
                                                                />
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <button
                                                            onClick={() =>
                                                                openEditor(
                                                                    row.employee_id,
                                                                    cell,
                                                                )
                                                            }
                                                            aria-label={`Ubah kuota ${cell.leave_type} untuk ${row.name}`}
                                                            style={{
                                                                border: 'none',
                                                                background:
                                                                    'none',
                                                                padding: 0,
                                                                cursor: 'pointer',
                                                                textAlign:
                                                                    'left',
                                                            }}
                                                        >
                                                            <span
                                                                style={{
                                                                    fontWeight: 600,
                                                                    color: cell.has_balance
                                                                        ? C.navy
                                                                        : C.faint,
                                                                }}
                                                            >
                                                                {days(
                                                                    cell.remaining,
                                                                )}
                                                            </span>
                                                            <span
                                                                style={{
                                                                    fontSize: 12,
                                                                    color: C.faint,
                                                                }}
                                                            >
                                                                {' '}
                                                                /{' '}
                                                                {days(
                                                                    cell.quota,
                                                                )}{' '}
                                                                hari
                                                            </span>
                                                            {cell.has_balance && (
                                                                <div
                                                                    style={{
                                                                        fontSize: 11.5,
                                                                        color: C.faint,
                                                                    }}
                                                                >
                                                                    terpakai{' '}
                                                                    {days(
                                                                        cell.used,
                                                                    )}
                                                                </div>
                                                            )}
                                                        </button>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {rows.meta.last_page > 1 && (
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                padding: '12px 18px',
                                borderTop: `1px solid ${C.line}`,
                                fontSize: 12.5,
                                color: C.muted,
                            }}
                        >
                            <div>
                                Halaman {rows.meta.current_page} dari{' '}
                                {rows.meta.last_page} ·{' '}
                                {rows.meta.total.toLocaleString('id-ID')}{' '}
                                karyawan
                            </div>
                            <div style={{ display: 'flex', gap: 8 }}>
                                <button
                                    disabled={rows.meta.current_page <= 1}
                                    onClick={() =>
                                        reload({
                                            page: rows.meta.current_page - 1,
                                        })
                                    }
                                    style={{ ...btnOut, height: 32 }}
                                >
                                    Sebelumnya
                                </button>
                                <button
                                    disabled={
                                        rows.meta.current_page >=
                                        rows.meta.last_page
                                    }
                                    onClick={() =>
                                        reload({
                                            page: rows.meta.current_page + 1,
                                        })
                                    }
                                    style={{ ...btnOut, height: 32 }}
                                >
                                    Berikutnya
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {carryOpen && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(14,26,58,.35)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                        zIndex: 60,
                    }}
                >
                    <div style={{ ...card, padding: 22, maxWidth: 440 }}>
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Carry-over sisa {filters.year - 1}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginTop: 6,
                                lineHeight: 1.55,
                            }}
                        >
                            Sisa cuti {filters.year - 1} ditambahkan ke kuota{' '}
                            {filters.year}. Kuota ditulis ulang sebagai "jatah
                            dasar + sisa", jadi menjalankannya dua kali tidak
                            menumpuk.
                        </div>
                        <label
                            style={{
                                display: 'block',
                                fontSize: 12.5,
                                color: C.muted,
                                margin: '16px 0 6px',
                            }}
                        >
                            Maksimal hari yang boleh dibawa (kosongkan = tanpa
                            batas)
                        </label>
                        <input
                            value={carryMax}
                            onChange={(event) =>
                                setCarryMax(event.target.value)
                            }
                            placeholder="mis. 6"
                            aria-label="Maksimal hari carry-over"
                            style={{ ...input, width: '100%' }}
                        />
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                                marginTop: 20,
                            }}
                        >
                            <button
                                onClick={() => setCarryOpen(false)}
                                style={btnOut}
                            >
                                Batal
                            </button>
                            <button onClick={carryOver} style={btnP}>
                                Jalankan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function Kpi({
    label,
    value,
    icon,
    tone,
}: {
    label: string;
    value: string;
    icon: string;
    tone: string;
}) {
    return (
        <div style={{ ...card, padding: '16px 18px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <div
                    style={{
                        width: 34,
                        height: 34,
                        borderRadius: 9,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: tone + '1a',
                    }}
                >
                    <AIcon name={icon} size={17} color={tone} />
                </div>
                <div style={{ fontSize: 12.5, color: C.muted }}>{label}</div>
            </div>
            <div
                style={{
                    fontSize: 24,
                    fontWeight: 700,
                    color: C.navy,
                    marginTop: 10,
                }}
            >
                {value}
            </div>
        </div>
    );
}
