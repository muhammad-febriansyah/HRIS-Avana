import { Head } from '@inertiajs/react';
import { C, card, rp } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
    StatCard,
} from './components';

interface BenefitRow {
    id: number;
    name: string | null;
    code: string | null;
    type: string | null;
    value: number;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    status_label: string;
    notes: string | null;
    is_running: boolean;
}

interface Props {
    benefits: BenefitRow[];
    summary: { total: number; running: number; total_value: number };
}

export default function SayaBenefit({ benefits, summary }: Props) {
    return (
        <>
            <Head title="Benefit Saya" />
            <PageShell>
                <PageHeader
                    title="Benefit Saya"
                    subtitle="Tunjangan dan fasilitas yang melekat padamu. Penetapan dilakukan HR."
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
                        label="Benefit Berjalan"
                        value={summary.running}
                        unit={`dari ${summary.total}`}
                        icon="gift"
                        tone={C.green}
                    />
                    <StatCard
                        label="Total Nilai Berjalan"
                        value={rp(summary.total_value)}
                        icon="wallet"
                        tone={C.primary}
                    />
                </div>

                {benefits.length === 0 ? (
                    <Panel padded={false}>
                        <EmptyState
                            icon="gift"
                            message="Belum ada benefit yang ditetapkan untukmu."
                        />
                    </Panel>
                ) : (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(300px, 1fr))',
                            gap: 14,
                        }}
                    >
                        {benefits.map((benefit) => (
                            <div
                                key={benefit.id}
                                style={{
                                    ...card,
                                    padding: '18px 20px',
                                    opacity: benefit.is_running ? 1 : 0.72,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        justifyContent: 'space-between',
                                        gap: 10,
                                        marginBottom: 10,
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: 14.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {benefit.name ?? '—'}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                                marginTop: 2,
                                            }}
                                        >
                                            {[benefit.code, benefit.type]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </div>
                                    </div>
                                    <Pill
                                        label={benefit.status_label}
                                        color={
                                            benefit.is_running
                                                ? C.green
                                                : C.muted
                                        }
                                    />
                                </div>

                                {benefit.value > 0 && (
                                    <div
                                        style={{
                                            fontSize: 20,
                                            fontWeight: 700,
                                            color: C.navy,
                                            marginBottom: 10,
                                        }}
                                    >
                                        {rp(benefit.value)}
                                    </div>
                                )}

                                {benefit.description && (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginBottom: 10,
                                        }}
                                    >
                                        {benefit.description}
                                    </div>
                                )}

                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: C.faint,
                                        paddingTop: 10,
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    Berlaku {formatDate(benefit.start_date)} –{' '}
                                    {benefit.end_date
                                        ? formatDate(benefit.end_date)
                                        : 'tanpa batas akhir'}
                                </div>

                                {benefit.notes && (
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.muted,
                                            marginTop: 6,
                                        }}
                                    >
                                        {benefit.notes}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </PageShell>
        </>
    );
}
