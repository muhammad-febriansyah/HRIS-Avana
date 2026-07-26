import { Head } from '@inertiajs/react';
import { C, rp, thCell } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
    StatCard,
} from './components';

interface Travel {
    id: number;
    destination: string | null;
    purpose: string | null;
    transport: string | null;
    start_date: string | null;
    end_date: string | null;
    estimated_cost: number;
    per_diem: number;
    status: string;
    status_label: string;
    notes: string | null;
}

interface Props {
    travels: Travel[];
    summary: {
        total: number;
        approved: number;
        pending: number;
        total_per_diem: number;
    };
}

/** Colour per duty travel status. */
const STATUS_TONE: Record<string, string> = {
    draft: C.faint,
    pending: C.amber,
    approved: C.green,
    rejected: C.red,
    ongoing: C.sky,
    completed: C.green,
    cancelled: C.muted,
};

export default function SayaPerjalananDinas({ travels, summary }: Props) {
    return (
        <>
            <Head title="Perjalanan Dinas Saya" />
            <PageShell>
                <PageHeader
                    title="Perjalanan Dinas Saya"
                    subtitle="Penugasan dinas atas namamu. Pengajuan dan persetujuan dilakukan HR atau atasan."
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
                        label="Total Penugasan"
                        value={summary.total}
                        unit="perjalanan"
                        icon="plane"
                        tone={C.primary}
                    />
                    <StatCard
                        label="Disetujui"
                        value={summary.approved}
                        unit="perjalanan"
                        icon="circle-check"
                        tone={C.green}
                    />
                    <StatCard
                        label="Menunggu"
                        value={summary.pending}
                        unit="perjalanan"
                        icon="hourglass"
                        tone={C.amber}
                    />
                    <StatCard
                        label="Total Uang Harian"
                        value={rp(summary.total_per_diem)}
                        icon="wallet"
                        tone={C.sky}
                    />
                </div>

                <Panel
                    title="Riwayat Perjalanan"
                    subtitle={`${travels.length.toLocaleString('id-ID')} penugasan`}
                    padded={false}
                >
                    {travels.length === 0 ? (
                        <EmptyState
                            icon="plane"
                            message="Belum ada perjalanan dinas atas namamu."
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 820,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={thCell}>Tujuan</th>
                                        <th style={thCell}>Periode</th>
                                        <th style={thCell}>Transport</th>
                                        <th style={thCell}>Estimasi Biaya</th>
                                        <th style={thCell}>Uang Harian</th>
                                        <th style={thCell}>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {travels.map((row) => (
                                        <tr
                                            key={row.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={cell}>
                                                <div
                                                    style={{ fontWeight: 600 }}
                                                >
                                                    {row.destination ?? '—'}
                                                </div>
                                                {row.purpose && (
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                            marginTop: 2,
                                                        }}
                                                    >
                                                        {row.purpose}
                                                    </div>
                                                )}
                                            </td>
                                            <td style={cell}>
                                                {formatDate(row.start_date)} –{' '}
                                                {formatDate(row.end_date)}
                                            </td>
                                            <td style={cell}>
                                                {row.transport ?? '—'}
                                            </td>
                                            <td style={cell}>
                                                {row.estimated_cost > 0
                                                    ? rp(row.estimated_cost)
                                                    : '—'}
                                            </td>
                                            <td style={cell}>
                                                {row.per_diem > 0
                                                    ? rp(row.per_diem)
                                                    : '—'}
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
