import { Head } from '@inertiajs/react';
import { C, thCell } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
    StatCard,
} from './components';

interface Enrollment {
    id: number;
    title: string | null;
    category: string | null;
    type: string | null;
    instructor: string | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    status_label: string;
    score: number | null;
    certificate_no: string | null;
    completed_date: string | null;
}

interface CatalogueItem {
    id: number;
    title: string;
    category: string | null;
    type: string | null;
    instructor: string | null;
    start_date: string | null;
    end_date: string | null;
    quota: number | null;
}

interface Props {
    enrollments: Enrollment[];
    catalogue: CatalogueItem[];
    summary: { total: number; completed: number; certificates: number };
}

/** Colour per enrolment status. */
const STATUS_TONE: Record<string, string> = {
    registered: C.primary,
    enrolled: C.primary,
    in_progress: C.amber,
    ongoing: C.amber,
    completed: C.green,
    passed: C.green,
    failed: C.red,
    cancelled: C.muted,
};

export default function SayaPembelajaran({
    enrollments,
    catalogue,
    summary,
}: Props) {
    return (
        <>
            <Head title="Pembelajaran Saya" />
            <PageShell>
                <PageHeader
                    title="Pembelajaran Saya"
                    subtitle="Pelatihan yang kamu ikuti. Pendaftaran diatur oleh HR."
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
                        label="Pelatihan Diikuti"
                        value={summary.total}
                        unit="program"
                        icon="graduation-cap"
                        tone={C.primary}
                    />
                    <StatCard
                        label="Selesai"
                        value={summary.completed}
                        unit={`dari ${summary.total}`}
                        icon="circle-check"
                        tone={C.green}
                    />
                    <StatCard
                        label="Sertifikat"
                        value={summary.certificates}
                        unit="terbit"
                        icon="award"
                        tone={C.amber}
                    />
                </div>

                <Panel
                    title="Riwayat Pelatihan"
                    subtitle={`${enrollments.length.toLocaleString('id-ID')} program`}
                    padded={false}
                >
                    {enrollments.length === 0 ? (
                        <EmptyState
                            icon="graduation-cap"
                            message="Belum ada pelatihan yang kamu ikuti."
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
                                        <th style={thCell}>Pelatihan</th>
                                        <th style={thCell}>Jadwal</th>
                                        <th style={thCell}>Nilai</th>
                                        <th style={thCell}>Sertifikat</th>
                                        <th style={thCell}>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {enrollments.map((row) => (
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
                                                    {row.title ?? '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    {[
                                                        row.category,
                                                        row.type,
                                                        row.instructor,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') || '—'}
                                                </div>
                                            </td>
                                            <td style={cell}>
                                                {formatDate(row.start_date)}
                                                {row.end_date &&
                                                    ` – ${formatDate(row.end_date)}`}
                                            </td>
                                            <td style={cell}>
                                                {row.score !== null
                                                    ? row.score.toLocaleString(
                                                          'id-ID',
                                                      )
                                                    : '—'}
                                            </td>
                                            <td style={cell}>
                                                {row.certificate_no ?? '—'}
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

                {catalogue.length > 0 && (
                    <div style={{ marginTop: 16 }}>
                        <Panel
                            title="Pelatihan Tersedia"
                            subtitle="Tertarik ikut? Hubungi HR untuk didaftarkan."
                            padded={false}
                        >
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 640,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={thCell}>Pelatihan</th>
                                            <th style={thCell}>Jadwal</th>
                                            <th style={thCell}>Kuota</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {catalogue.map((row) => (
                                            <tr
                                                key={row.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td style={cell}>
                                                    <div
                                                        style={{
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {row.title}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                            marginTop: 2,
                                                        }}
                                                    >
                                                        {[
                                                            row.category,
                                                            row.type,
                                                            row.instructor,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') || '—'}
                                                    </div>
                                                </td>
                                                <td style={cell}>
                                                    {formatDate(row.start_date)}
                                                    {row.end_date &&
                                                        ` – ${formatDate(row.end_date)}`}
                                                </td>
                                                <td style={cell}>
                                                    {row.quota !== null
                                                        ? `${row.quota.toLocaleString('id-ID')} orang`
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Panel>
                    </div>
                )}
            </PageShell>
        </>
    );
}

const cell = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
} as const;
