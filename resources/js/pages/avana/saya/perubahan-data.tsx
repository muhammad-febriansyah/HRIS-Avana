import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnP, C, thCell } from '@/lib/avana';
import {
    EmptyState,
    PageHeader,
    PageShell,
    Panel,
    Pill,
    StatCard,
} from './components';

interface ChangedField {
    field: string;
    label: string;
    group: string;
    old: string | null;
    new: string | null;
}

interface ChangeRequest {
    id: number;
    changes: ChangedField[];
    reason: string | null;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    requested_at: string | null;
    decided_at: string | null;
    approver: string | null;
}

interface Props {
    requests: ChangeRequest[];
    summary: {
        total: number;
        pending: number;
        approved: number;
        rejected: number;
    };
}

type FlashProps = { flash?: { success?: string } };

/** Colour per request status. */
const STATUS_TONE: Record<string, string> = {
    pending: C.amber,
    approved: C.green,
    rejected: C.red,
};

/** An empty stored value reads as "(kosong)" rather than as a blank cell. */
const shown = (value: string | null) =>
    value === null || value === '' ? '(kosong)' : value;

export default function SayaPerubahanData({ requests, summary }: Props) {
    const { flash } = usePage<FlashProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    return (
        <>
            <Head title="Perubahan Data Saya" />
            <PageShell>
                <PageHeader
                    title="Perubahan Data Saya"
                    subtitle="Ajukan koreksi data pribadimu. Perubahan berlaku setelah disetujui."
                    action={
                        <Link
                            href="/avana/saya/perubahan-data/ajukan"
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Ajukan Perubahan
                        </Link>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(210px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <StatCard
                        label="Total Pengajuan"
                        value={summary.total}
                        unit="pengajuan"
                        icon="user-round-cog"
                        tone={C.primary}
                    />
                    <StatCard
                        label="Menunggu"
                        value={summary.pending}
                        unit="pengajuan"
                        icon="hourglass"
                        tone={C.amber}
                    />
                    <StatCard
                        label="Disetujui"
                        value={summary.approved}
                        unit="pengajuan"
                        icon="circle-check"
                        tone={C.green}
                    />
                    <StatCard
                        label="Ditolak"
                        value={summary.rejected}
                        unit="pengajuan"
                        icon="circle-x"
                        tone={C.red}
                    />
                </div>

                <Panel
                    title="Riwayat Pengajuan"
                    subtitle={`${requests.length.toLocaleString('id-ID')} pengajuan`}
                    padded={false}
                >
                    {requests.length === 0 ? (
                        <EmptyState
                            icon="user-round-cog"
                            message="Belum ada pengajuan perubahan data."
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 780,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={thCell}>Perubahan</th>
                                        <th style={thCell}>Alasan</th>
                                        <th style={thCell}>Diajukan</th>
                                        <th style={thCell}>Diputuskan</th>
                                        <th style={thCell}>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.map((row) => (
                                        <tr
                                            key={row.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={cell}>
                                                {row.changes.map((change) => (
                                                    <div
                                                        key={change.field}
                                                        style={{
                                                            marginBottom: 3,
                                                        }}
                                                    >
                                                        <span
                                                            style={{
                                                                fontWeight: 600,
                                                            }}
                                                        >
                                                            {change.label}
                                                        </span>
                                                        <span
                                                            style={{
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {' '}
                                                            {shown(
                                                                change.old,
                                                            )}{' '}
                                                            →{' '}
                                                        </span>
                                                        <span
                                                            style={{
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {shown(change.new)}
                                                        </span>
                                                    </div>
                                                ))}
                                            </td>
                                            <td style={cell}>
                                                {row.reason ?? '—'}
                                                {row.rejection_reason && (
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.red,
                                                            marginTop: 3,
                                                        }}
                                                    >
                                                        Ditolak:{' '}
                                                        {row.rejection_reason}
                                                    </div>
                                                )}
                                            </td>
                                            <td style={cell}>
                                                {row.requested_at ?? '—'}
                                            </td>
                                            <td style={cell}>
                                                {row.decided_at ?? '—'}
                                                {row.approver && (
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                            marginTop: 2,
                                                        }}
                                                    >
                                                        oleh {row.approver}
                                                    </div>
                                                )}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <Pill
                                                    label={row.status_label}
                                                    color={
                                                        STATUS_TONE[
                                                            row.status
                                                        ] ?? C.muted
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </PageShell>
        </>
    );
}

const cell = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
} as const;
