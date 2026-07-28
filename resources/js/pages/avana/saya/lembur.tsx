import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnProcess, C, thCell } from '@/lib/avana';
import {
    EmptyState,
    Field,
    formatDate,
    inputStyle,
    PageHeader,
    PageShell,
    Panel,
    StatCard,
    StatusPill,
    textareaStyle,
    withError,
} from './components';

/** Hours between two `HH:MM` values, carrying a range past midnight. */
function hoursBetween(start: string, end: string): number | null {
    if (!/^\d{2}:\d{2}$/.test(start) || !/^\d{2}:\d{2}$/.test(end)) {
        return null;
    }

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const minutes = eh * 60 + em - (sh * 60 + sm);

    // Evening overtime commonly ends after midnight, so an end at or before
    // the start belongs to the next day rather than being negative.
    return (minutes <= 0 ? minutes + 24 * 60 : minutes) / 60;
}

interface OvertimeRow {
    id: number;
    date: string | null;
    hours: number;
    /** Null for requests filed before overtime was captured as a range. */
    time_range: string | null;
    reason: string | null;
    status: string;
}

interface Props {
    requests: OvertimeRow[];
    approvedHours: number;
    pendingCount: number;
}

type FlashProps = { flash?: { success?: string } };

export default function SayaLembur({
    requests,
    approvedHours,
    pendingCount,
}: Props) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        date: '',
        start_time: '',
        end_time: '',
        reason: '',
    });

    // Shown live so the employee sees what they are actually claiming before
    // they send it — the server derives the same number from the same range.
    const duration = hoursBetween(form.data.start_time, form.data.end_time);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/avana/saya/lembur', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Lembur Saya" />
            <PageShell>
                <PageHeader
                    title="Lembur Saya"
                    subtitle="Ajukan jam lembur dan pantau persetujuannya."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(220px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <StatCard
                        label="Jam Disetujui"
                        value={approvedHours.toLocaleString('id-ID')}
                        unit="jam"
                        icon="timer"
                        tone={C.green}
                    />
                    <StatCard
                        label="Menunggu Persetujuan"
                        value={pendingCount}
                        unit="pengajuan"
                        icon="hourglass"
                        tone={C.amber}
                    />
                    <StatCard
                        label="Total Pengajuan"
                        value={requests.length}
                        unit="pengajuan"
                        icon="list"
                        tone={C.primary}
                    />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1.6fr)',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    <form onSubmit={submit}>
                        <Panel title="Ajukan Lembur">
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                }}
                            >
                                <Field
                                    label="Tanggal"
                                    required
                                    error={form.errors.date}
                                >
                                    <DatePicker
                                        value={form.data.date}
                                        onChange={(value) =>
                                            form.setData('date', value)
                                        }
                                        placeholder="Pilih tanggal"
                                        hasError={!!form.errors.date}
                                        width="100%"
                                    />
                                </Field>

                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 12,
                                    }}
                                >
                                    <Field
                                        label="Jam Mulai"
                                        required
                                        error={form.errors.start_time}
                                    >
                                        <input
                                            type="time"
                                            value={form.data.start_time}
                                            onChange={(event) =>
                                                form.setData(
                                                    'start_time',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                inputStyle,
                                                !!form.errors.start_time,
                                            )}
                                        />
                                    </Field>

                                    <Field
                                        label="Jam Selesai"
                                        required
                                        error={form.errors.end_time}
                                    >
                                        <input
                                            type="time"
                                            value={form.data.end_time}
                                            onChange={(event) =>
                                                form.setData(
                                                    'end_time',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                inputStyle,
                                                !!form.errors.end_time,
                                            )}
                                        />
                                    </Field>
                                </div>

                                {duration !== null && (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginTop: -6,
                                        }}
                                    >
                                        Durasi{' '}
                                        <strong style={{ color: C.navy }}>
                                            {duration.toLocaleString('id-ID', {
                                                maximumFractionDigits: 2,
                                            })}{' '}
                                            jam
                                        </strong>
                                        {(duration > 12 || duration < 0.5) &&
                                            ' — di luar batas 0,5–12 jam'}
                                    </div>
                                )}

                                <Field
                                    label="Alasan"
                                    error={form.errors.reason}
                                >
                                    <textarea
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="mis. menyelesaikan rilis fitur"
                                        style={withError(
                                            textareaStyle,
                                            !!form.errors.reason,
                                        )}
                                    />
                                </Field>

                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    style={{
                                        ...btnProcess,
                                        height: 44,
                                        justifyContent: 'center',
                                        opacity: form.processing ? 0.7 : 1,
                                    }}
                                >
                                    <AIcon name="send" size={16} color="#fff" />
                                    Kirim Pengajuan
                                </button>
                            </div>
                        </Panel>
                    </form>

                    <Panel
                        title="Riwayat Lembur"
                        subtitle={`${requests.length.toLocaleString('id-ID')} pengajuan`}
                        padded={false}
                    >
                        {requests.length === 0 ? (
                            <EmptyState
                                icon="timer"
                                message="Belum ada pengajuan lembur."
                            />
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 520,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={thCell}>Tanggal</th>
                                            <th style={thCell}>Jam</th>
                                            <th style={thCell}>Alasan</th>
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
                                                    {formatDate(row.date)}
                                                </td>
                                                <td style={cell}>
                                                    <div>
                                                        {row.hours.toLocaleString(
                                                            'id-ID',
                                                        )}{' '}
                                                        jam
                                                    </div>
                                                    {row.time_range && (
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                                marginTop: 2,
                                                            }}
                                                        >
                                                            {row.time_range}
                                                        </div>
                                                    )}
                                                </td>
                                                <td
                                                    style={{
                                                        ...cell,
                                                        maxWidth: 260,
                                                    }}
                                                >
                                                    {row.reason ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                    }}
                                                >
                                                    <StatusPill
                                                        status={row.status}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
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
