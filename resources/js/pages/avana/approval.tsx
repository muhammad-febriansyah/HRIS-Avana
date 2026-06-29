import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ApprovalController from '@/actions/App/Http/Controllers/Avana/ApprovalController';
import { AIcon, C, card, statusBadge } from '@/lib/avana';
import type { FlashProps } from './employees/types';

type ApprovalType = 'leave' | 'lembur' | 'izin' | 'wfh' | 'koreksi';
type ApprovalStatus = 'pending' | 'approved' | 'rejected';
type ApprovalStatusLabel = 'Menunggu' | 'Disetujui' | 'Ditolak';

/** The employee summary shared by every approval row. */
interface ApprovalEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

/** A single aggregated approval row as serialized by `ApprovalController`. */
interface ApprovalItem {
    type: ApprovalType;
    id: number;
    employee: ApprovalEmployee | null;
    title: string;
    detail: string;
    reason: string | null;
    requested_at: string | null;
    status: ApprovalStatus;
    status_label: ApprovalStatusLabel;
}

interface ApprovalCounts {
    leave: number;
    lembur: number;
    izin: number;
    wfh: number;
    koreksi: number;
    total: number;
}

interface ApprovalProps {
    pending: ApprovalItem[];
    history: ApprovalItem[];
    counts: ApprovalCounts;
}

/** Per-type presentation metadata: label, icon and accent colour. */
const typeMeta: Record<ApprovalType, { label: string; icon: string; color: string }> = {
    leave: { label: 'Cuti', icon: 'palmtree', color: '#2F54C9' },
    lembur: { label: 'Lembur', icon: 'clock', color: '#D97706' },
    izin: { label: 'Izin', icon: 'door-open', color: '#6E9BE6' },
    wfh: { label: 'WFH', icon: 'house', color: '#16A34A' },
    koreksi: { label: 'Koreksi', icon: 'pencil', color: '#8b5cf6' },
};

type FilterKey = 'all' | ApprovalType;

const filters: { key: FilterKey; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'leave', label: 'Cuti' },
    { key: 'lembur', label: 'Lembur' },
    { key: 'izin', label: 'Izin' },
    { key: 'wfh', label: 'WFH' },
    { key: 'koreksi', label: 'Koreksi' },
];

const headThStyle: CSSProperties = {
    padding: '11px 18px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

/** Coloured pill badge identifying the request type (with its icon). */
function TypeBadge({ type }: { type: ApprovalType }) {
    const meta = typeMeta[type];

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: meta.color,
                background: meta.color + '1a',
            }}
        >
            <AIcon name={meta.icon} size={12} color={meta.color} />
            {meta.label}
        </span>
    );
}

/** The avatar + name + employee number cell shared by both tables. */
function EmployeeCell({ employee }: { employee: ApprovalEmployee | null }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div
                style={{
                    width: 32,
                    height: 32,
                    borderRadius: '50%',
                    flex: 'none',
                    background: employee?.avatar_color ?? C.faint,
                    color: '#fff',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: 11.5,
                    fontWeight: 600,
                }}
            >
                {employee?.initials ?? '?'}
            </div>
            <div>
                <div style={{ fontSize: 13, fontWeight: 500, color: C.text }}>
                    {employee?.name ?? '—'}
                </div>
                <div style={{ fontSize: 11.5, color: C.faint }}>
                    {employee?.employee_number ?? ''}
                </div>
            </div>
        </div>
    );
}

/** The title + detail stacked cell for a request. */
function DetailCell({ item }: { item: ApprovalItem }) {
    return (
        <div>
            <div style={{ fontSize: 13, fontWeight: 500, color: C.text }}>
                {item.title}
            </div>
            <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>
                {item.detail}
            </div>
        </div>
    );
}

export default function AvanaApproval({ pending, history, counts }: ApprovalProps) {
    const { flash } = usePage<FlashProps>().props;
    const [filter, setFilter] = useState<FilterKey>('all');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const visiblePending =
        filter === 'all' ? pending : pending.filter((item) => item.type === filter);

    const approve = (item: ApprovalItem) =>
        router.post(
            ApprovalController.approve({ type: item.type, id: item.id }).url,
            {},
            { preserveScroll: true },
        );

    const reject = (item: ApprovalItem) =>
        router.post(
            ApprovalController.reject({ type: item.type, id: item.id }).url,
            {},
            { preserveScroll: true },
        );

    const stats: { key: FilterKey; label: string; value: number; icon: string; color: string }[] = [
        { key: 'all', label: 'Total Menunggu', value: counts.total, icon: 'inbox', color: C.navy },
        { key: 'leave', label: 'Cuti', value: counts.leave, icon: typeMeta.leave.icon, color: typeMeta.leave.color },
        { key: 'lembur', label: 'Lembur', value: counts.lembur, icon: typeMeta.lembur.icon, color: typeMeta.lembur.color },
        { key: 'izin', label: 'Izin', value: counts.izin, icon: typeMeta.izin.icon, color: typeMeta.izin.color },
        { key: 'wfh', label: 'WFH', value: counts.wfh, icon: typeMeta.wfh.icon, color: typeMeta.wfh.color },
        { key: 'koreksi', label: 'Koreksi', value: counts.koreksi, icon: typeMeta.koreksi.icon, color: typeMeta.koreksi.color },
    ];

    return (
        <>
            <Head title="Pusat Persetujuan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span style={{ color: C.muted }}>Persetujuan</span>
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
                        Pusat Persetujuan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Tinjau dan proses semua pengajuan tim dari satu halaman
                    </div>
                </div>

                {/* Stat cards */}
                <div
                    className="avn-stat"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(6,1fr)',
                        gap: 14,
                        marginBottom: 20,
                    }}
                >
                    {stats.map((stat) => (
                        <div key={stat.key} style={{ ...card, padding: '16px 18px' }}>
                            <div
                                style={{
                                    width: 36,
                                    height: 36,
                                    borderRadius: 10,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    background: stat.color + '1a',
                                    color: stat.color,
                                    marginBottom: 12,
                                }}
                            >
                                <AIcon name={stat.icon} size={18} color={stat.color} />
                            </div>
                            <div style={{ fontSize: 26, fontWeight: 700, color: C.navy, lineHeight: 1 }}>
                                {stat.value}
                            </div>
                            <div style={{ fontSize: 12.5, color: C.muted, marginTop: 5 }}>
                                {stat.label}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Filter chips */}
                <div
                    style={{
                        display: 'inline-flex',
                        flexWrap: 'wrap',
                        gap: 4,
                        background: C.surface,
                        padding: 4,
                        borderRadius: 10,
                        marginBottom: 18,
                    }}
                >
                    {filters.map((item) => {
                        const active = filter === item.key;
                        const badgeCount =
                            item.key === 'all' ? counts.total : counts[item.key];

                        return (
                            <button
                                key={item.key}
                                onClick={() => setFilter(item.key)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    height: 34,
                                    padding: '0 14px',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: active ? '#fff' : C.muted,
                                    background: active ? C.primary : 'transparent',
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                {item.label}
                                <span
                                    style={{
                                        fontSize: 11,
                                        fontWeight: 700,
                                        padding: '1px 7px',
                                        borderRadius: 100,
                                        color: active ? '#fff' : C.faint,
                                        background: active
                                            ? 'rgba(255,255,255,.22)'
                                            : C.line,
                                    }}
                                >
                                    {badgeCount}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Pending table */}
                <div style={{ ...card, overflow: 'hidden', marginBottom: 24 }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Menunggu Persetujuan
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 760 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Jenis</th>
                                    <th style={headThStyle}>Detail</th>
                                    <th style={headThStyle}>Alasan</th>
                                    <th style={headThStyle}>Diajukan</th>
                                    <th style={{ ...headThStyle, textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visiblePending.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            colSpan={6}
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon name="check-check" size={28} color={C.faint} />
                                                <div>Tidak ada pengajuan yang menunggu persetujuan.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {visiblePending.map((item) => (
                                    <tr
                                        key={`${item.type}-${item.id}`}
                                        style={{ borderTop: `1px solid ${C.line}` }}
                                    >
                                        <td style={{ padding: '12px 18px' }}>
                                            <EmployeeCell employee={item.employee} />
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <TypeBadge type={item.type} />
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <DetailCell item={item} />
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.muted,
                                                maxWidth: 220,
                                            }}
                                        >
                                            {item.reason ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.text,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {item.requested_at ?? '—'}
                                        </td>
                                        <td style={{ padding: '12px 18px', textAlign: 'right' }}>
                                            <div style={{ display: 'inline-flex', gap: 6 }}>
                                                <button
                                                    onClick={() => approve(item)}
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 5,
                                                        height: 30,
                                                        padding: '0 11px',
                                                        border: 'none',
                                                        borderRadius: 7,
                                                        background: 'rgba(22,163,74,.1)',
                                                        color: C.green,
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        cursor: 'pointer',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon name="check" size={14} color={C.green} />
                                                    Setujui
                                                </button>
                                                <button
                                                    onClick={() => reject(item)}
                                                    style={{
                                                        width: 30,
                                                        height: 30,
                                                        border: 'none',
                                                        borderRadius: 7,
                                                        background: 'rgba(220,38,38,.08)',
                                                        color: C.red,
                                                        cursor: 'pointer',
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon name="x" size={14} color={C.red} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* History table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Riwayat Persetujuan
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 760 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Jenis</th>
                                    <th style={headThStyle}>Detail</th>
                                    <th style={headThStyle}>Alasan</th>
                                    <th style={headThStyle}>Diajukan</th>
                                    <th style={headThStyle}>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            colSpan={6}
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon name="history" size={28} color={C.faint} />
                                                <div>Belum ada riwayat persetujuan.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {history.map((item) => {
                                    const badge = statusBadge(item.status_label);

                                    return (
                                        <tr
                                            key={`${item.type}-${item.id}`}
                                            style={{ borderTop: `1px solid ${C.line}` }}
                                        >
                                            <td style={{ padding: '12px 18px' }}>
                                                <EmployeeCell employee={item.employee} />
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                <TypeBadge type={item.type} />
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                <DetailCell item={item} />
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                    maxWidth: 220,
                                                }}
                                            >
                                                {item.reason ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12.5,
                                                    color: C.text,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {item.requested_at ?? '—'}
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                <span
                                                    style={{
                                                        padding: '3px 10px',
                                                        borderRadius: 100,
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                        color: badge.color,
                                                        background: badge.bg,
                                                    }}
                                                >
                                                    {badge.label}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
