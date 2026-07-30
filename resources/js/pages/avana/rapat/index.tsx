import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import MeetingController from '@/actions/App/Http/Controllers/Avana/MeetingController';
import { AIcon, btnOut, C, card, hexA, thCell } from '@/lib/avana';
import type { FlashProps, MeetingIndexProps, MeetingRow } from './types';
import { formatDateTime, STATUS_LABELS } from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 170px',
};

const inputStyle: CSSProperties = {
    height: 40,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const STATUS_TONE: Record<string, string> = {
    recording: C.red,
    processing: C.amber,
    ready: C.green,
    failed: C.muted,
};

function StatusPill({ status }: { status: MeetingRow['status'] }) {
    const tone = STATUS_TONE[status] ?? C.muted;

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '3px 9px',
                borderRadius: 999,
                fontSize: 11.5,
                fontWeight: 600,
                color: tone,
                background: hexA(tone, 0.1),
            }}
        >
            {status === 'recording' && (
                <span
                    style={{
                        width: 6,
                        height: 6,
                        borderRadius: 999,
                        background: tone,
                    }}
                />
            )}
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

export default function MeetingIndex({
    meetings,
    kpis,
    recorderReady,
}: MeetingIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    // Filtering in the browser: the list is one page of a tenant's recordings,
    // and a round trip per keystroke would be slower than the typing.
    const rows = useMemo(() => {
        const term = search.trim().toLowerCase();

        return meetings.filter((meeting) => {
            if (statusFilter !== '' && meeting.status !== statusFilter) {
                return false;
            }

            if (term === '') {
                return true;
            }

            return `${meeting.title} ${meeting.location ?? ''} ${meeting.participants.join(' ')}`
                .toLowerCase()
                .includes(term);
        });
    }, [meetings, search, statusFilter]);

    return (
        <>
            <Head title="Rapat & Transkrip" />

            <div style={{ padding: '28px 32px' }}>
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
                        <span>Layanan</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>
                            Rapat & Transkrip
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
                        Rapat & Transkrip
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Rekaman dibuat dari aplikasi HP (AI Recorder).
                        Transkrip, ringkasan, dan analisisnya dibaca di sini.
                    </div>
                </div>

                {!recorderReady && (
                    <div
                        style={{
                            ...card,
                            padding: '14px 18px',
                            marginBottom: 18,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            background: '#FDF4E7',
                            border: `1px solid ${hexA(C.amber, 0.3)}`,
                        }}
                    >
                        <AIcon name="circle-alert" size={16} color={C.amber} />
                        <span style={{ fontSize: 13.5, color: C.text }}>
                            Perekaman rapat belum aktif. Super Admin perlu
                            mengisi penyedia transkripsi di Pengaturan AI
                            sebelum aplikasi HP bisa merekam.
                        </span>
                    </div>
                )}

                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        flexWrap: 'wrap',
                        marginBottom: 20,
                    }}
                >
                    {[
                        {
                            label: 'Total Rapat',
                            value: kpis.total,
                            icon: 'mic',
                        },
                        {
                            label: 'Siap Dibaca',
                            value: kpis.ready,
                            icon: 'circle-check',
                        },
                        {
                            label: 'Sedang Diproses',
                            value: kpis.processing,
                            icon: 'loader',
                        },
                        {
                            label: 'Total Menit',
                            value: kpis.minutes,
                            icon: 'clock',
                        },
                        {
                            label: 'Token Terpakai',
                            value: kpis.tokens,
                            icon: 'coins',
                        },
                    ].map((kpi) => (
                        <div key={kpi.label} style={kpiCardStyle}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    fontSize: 12,
                                    color: C.muted,
                                    marginBottom: 8,
                                }}
                            >
                                <AIcon
                                    name={kpi.icon}
                                    size={14}
                                    color={C.primary}
                                />
                                {kpi.label}
                            </div>
                            <div
                                style={{
                                    fontSize: 22,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                {kpi.value.toLocaleString('id-ID')}
                            </div>
                        </div>
                    ))}
                </div>

                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            display: 'flex',
                            gap: 12,
                            flexWrap: 'wrap',
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.line}`,
                        }}
                    >
                        <input
                            style={{ ...inputStyle, flex: '1 1 260px' }}
                            placeholder="Cari judul, lokasi, atau peserta…"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <select
                            style={{ ...inputStyle, cursor: 'pointer' }}
                            value={statusFilter}
                            onChange={(event) =>
                                setStatusFilter(event.target.value)
                            }
                        >
                            <option value="">Semua status</option>
                            {Object.entries(STATUS_LABELS).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>

                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 860,
                            }}
                        >
                            <thead>
                                <tr style={{ background: C.surface }}>
                                    <th style={thCell}>Rapat</th>
                                    <th style={thCell}>Waktu</th>
                                    <th style={thCell}>Durasi</th>
                                    <th style={thCell}>Peserta</th>
                                    <th style={thCell}>Tindak Lanjut</th>
                                    <th style={thCell}>Status</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            style={{
                                                padding: '40px 18px',
                                                textAlign: 'center',
                                                color: C.muted,
                                                fontSize: 13.5,
                                            }}
                                        >
                                            Belum ada rapat yang direkam.
                                        </td>
                                    </tr>
                                )}

                                {rows.map((meeting) => (
                                    <tr
                                        key={meeting.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td style={{ padding: '13px 16px' }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.text,
                                                }}
                                            >
                                                {meeting.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {meeting.location ??
                                                    'Tanpa lokasi'}
                                                {meeting.recorded_by
                                                    ? ` · direkam ${meeting.recorded_by}`
                                                    : ''}
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            {formatDateTime(meeting.started_at)}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            {meeting.duration_minutes} menit
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            {meeting.participants.length === 0
                                                ? '-'
                                                : `${meeting.participants.length} orang`}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.muted,
                                            }}
                                        >
                                            {meeting.action_item_count}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusPill
                                                status={meeting.status}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <button
                                                type="button"
                                                style={{
                                                    ...btnOut,
                                                    height: 34,
                                                    padding: '0 12px',
                                                }}
                                                onClick={() =>
                                                    router.visit(
                                                        MeetingController.show(
                                                            meeting.id,
                                                        ).url,
                                                    )
                                                }
                                            >
                                                <AIcon
                                                    name="file-text"
                                                    size={14}
                                                />
                                                Buka
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
