import { Head, router } from '@inertiajs/react';
import { MonthPicker } from '@/components/avana/date-picker';
import { AIcon, C, thCell } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
    StatCard,
} from './components';

interface AttendanceRow {
    id: number;
    date: string | null;
    clock_in: string | null;
    clock_out: string | null;
    late_minutes: number;
    work_minutes: number;
    status: string;
    status_label: string;
    work_mode: string | null;
}

interface Props {
    month: string;
    records: AttendanceRow[];
    summary: {
        present: number;
        late: number;
        absent: number;
        leave: number;
        work_hours: number;
    };
}

/** Colour per attendance status, matching the admin recap. */
const STATUS_TONE: Record<string, string> = {
    present: C.green,
    late: C.amber,
    absent: C.red,
    leave: C.primary,
    permit: C.primary,
    sick: C.primary,
    holiday: C.muted,
    wfh: C.sky,
};

export default function SayaAbsensi({ month, records, summary }: Props) {
    const changeMonth = (value: string) =>
        router.get(
            '/avana/saya/absensi',
            { month: value },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title="Absensi Saya" />
            <PageShell>
                <PageHeader
                    title="Absensi Saya"
                    subtitle="Riwayat kehadiranmu. Clock in/out dilakukan lewat aplikasi mobile."
                    action={
                        <MonthPicker value={month} onChange={changeMonth} />
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(190px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <StatCard
                        label="Hadir"
                        value={summary.present}
                        unit="hari"
                        icon="circle-check"
                        tone={C.green}
                    />
                    <StatCard
                        label="Terlambat"
                        value={summary.late}
                        unit="hari"
                        icon="clock-alert"
                        tone={C.amber}
                    />
                    <StatCard
                        label="Tidak Hadir"
                        value={summary.absent}
                        unit="hari"
                        icon="circle-x"
                        tone={C.red}
                    />
                    <StatCard
                        label="Total Jam Kerja"
                        value={summary.work_hours.toLocaleString('id-ID')}
                        unit="jam"
                        icon="clock"
                        tone={C.primary}
                    />
                </div>

                <Panel
                    title="Riwayat Kehadiran"
                    subtitle={`${records.length.toLocaleString('id-ID')} hari tercatat`}
                    padded={false}
                >
                    {records.length === 0 ? (
                        <EmptyState
                            icon="fingerprint"
                            message="Belum ada catatan absensi di bulan ini."
                        />
                    ) : (
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
                                        <th style={thCell}>Tanggal</th>
                                        <th style={thCell}>Masuk</th>
                                        <th style={thCell}>Pulang</th>
                                        <th style={thCell}>Terlambat</th>
                                        <th style={thCell}>Jam Kerja</th>
                                        <th style={thCell}>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.map((row) => (
                                        <tr
                                            key={row.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={cell}>
                                                {formatDate(row.date)}
                                            </td>
                                            <td style={cell}>
                                                {row.clock_in ?? '—'}
                                            </td>
                                            <td style={cell}>
                                                {row.clock_out ?? '—'}
                                            </td>
                                            <td style={cell}>
                                                {row.late_minutes > 0 ? (
                                                    <span
                                                        style={{
                                                            color: C.amber,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {row.late_minutes} mnt
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td style={cell}>
                                                {row.work_minutes > 0
                                                    ? `${(row.work_minutes / 60).toFixed(1).replace('.', ',')} jam`
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

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        marginTop: 14,
                        fontSize: 12.5,
                        color: C.faint,
                    }}
                >
                    <AIcon name="info" size={14} color={C.faint} />
                    Ada jam yang keliru? Ajukan lewat menu Koreksi Absensi.
                </div>
            </PageShell>
        </>
    );
}

const cell = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
} as const;
