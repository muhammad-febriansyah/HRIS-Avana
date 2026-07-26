import { Head } from '@inertiajs/react';
import { AIcon, C, card, rp, thCell } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
} from './components';

interface Contract {
    id: number;
    contract_number: string | null;
    contract_type: string | null;
    start_date: string | null;
    end_date: string | null;
    basic_salary: number;
    status: string;
    status_label: string;
    notes: string | null;
    days_to_expiry: number | null;
    expiring_soon: boolean;
}

interface Props {
    contracts: Contract[];
    active: Contract | null;
}

/** Colour per contract status. */
const STATUS_TONE: Record<string, string> = {
    active: C.green,
    expired: C.muted,
    terminated: C.red,
};

export default function SayaKontrak({ contracts, active }: Props) {
    // Still marked active while its end date has passed — HR has not renewed or
    // closed it yet, which is worth flagging rather than stating flatly.
    const overdue =
        active !== null &&
        active.days_to_expiry !== null &&
        active.days_to_expiry < 0;

    return (
        <>
            <Head title="Kontrak Saya" />
            <PageShell>
                <PageHeader
                    title="Kontrak Saya"
                    subtitle="Riwayat kontrak kerjamu. Penerbitan dan perubahan dilakukan HR."
                />

                {/* Active contract highlight */}
                {active && (
                    <div
                        style={{
                            ...card,
                            padding: '22px 24px',
                            marginBottom: 16,
                            background: `${C.primary}0a`,
                            border: `1px solid ${C.primary}33`,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-start',
                                justifyContent: 'space-between',
                                flexWrap: 'wrap',
                                gap: 16,
                            }}
                        >
                            <div>
                                <div style={{ fontSize: 12.5, color: C.muted }}>
                                    Kontrak Berjalan
                                </div>
                                <div
                                    style={{
                                        fontSize: 22,
                                        fontWeight: 700,
                                        color: C.navy,
                                        marginTop: 4,
                                        letterSpacing: '-.01em',
                                    }}
                                >
                                    {active.contract_type ?? 'Kontrak'}
                                    {active.contract_number
                                        ? ` · ${active.contract_number}`
                                        : ''}
                                </div>
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: C.muted,
                                        marginTop: 6,
                                    }}
                                >
                                    {formatDate(active.start_date)} –{' '}
                                    {active.end_date
                                        ? formatDate(active.end_date)
                                        : 'tanpa batas akhir'}
                                </div>
                            </div>
                            <div style={{ textAlign: 'right' }}>
                                <Pill
                                    label={active.status_label}
                                    color={
                                        STATUS_TONE[active.status] ?? C.muted
                                    }
                                />
                                {active.days_to_expiry !== null && (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: overdue
                                                ? C.red
                                                : active.expiring_soon
                                                  ? C.amber
                                                  : C.muted,
                                            fontWeight:
                                                overdue || active.expiring_soon
                                                    ? 600
                                                    : 400,
                                            marginTop: 8,
                                        }}
                                    >
                                        {active.days_to_expiry >= 0
                                            ? `Berakhir dalam ${active.days_to_expiry.toLocaleString('id-ID')} hari`
                                            : `Terlewat ${Math.abs(active.days_to_expiry).toLocaleString('id-ID')} hari`}
                                    </div>
                                )}
                            </div>
                        </div>

                        {(active.expiring_soon || overdue) && (
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    marginTop: 14,
                                    padding: '10px 12px',
                                    borderRadius: 8,
                                    background: overdue
                                        ? `${C.red}14`
                                        : `${C.amber}14`,
                                    fontSize: 12.5,
                                    color: overdue ? C.red : C.amber,
                                }}
                            >
                                <AIcon
                                    name="triangle-alert"
                                    size={15}
                                    color={overdue ? C.red : C.amber}
                                />
                                {overdue
                                    ? 'Tanggal berakhir kontrak sudah terlewat tapi statusnya masih aktif. Konfirmasi ke HR.'
                                    : 'Kontrak akan berakhir kurang dari 30 hari lagi. Hubungi HR untuk perpanjangan.'}
                            </div>
                        )}
                    </div>
                )}

                <Panel
                    title="Riwayat Kontrak"
                    subtitle={`${contracts.length.toLocaleString('id-ID')} kontrak`}
                    padded={false}
                >
                    {contracts.length === 0 ? (
                        <EmptyState
                            icon="file-text"
                            message="Belum ada kontrak tercatat."
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 760,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={thCell}>Nomor</th>
                                        <th style={thCell}>Jenis</th>
                                        <th style={thCell}>Periode</th>
                                        <th style={thCell}>Gaji Pokok</th>
                                        <th style={thCell}>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {contracts.map((row) => (
                                        <tr
                                            key={row.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    ...cell,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {row.contract_number ?? '—'}
                                                {row.notes && (
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            fontWeight: 400,
                                                            color: C.faint,
                                                            marginTop: 2,
                                                        }}
                                                    >
                                                        {row.notes}
                                                    </div>
                                                )}
                                            </td>
                                            <td style={cell}>
                                                {row.contract_type ?? '—'}
                                            </td>
                                            <td style={cell}>
                                                {formatDate(row.start_date)} –{' '}
                                                {row.end_date
                                                    ? formatDate(row.end_date)
                                                    : '∞'}
                                            </td>
                                            <td style={cell}>
                                                {row.basic_salary > 0
                                                    ? rp(row.basic_salary)
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
