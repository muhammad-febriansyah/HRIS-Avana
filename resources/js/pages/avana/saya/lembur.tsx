import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnP, C, thCell } from '@/lib/avana';
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

interface OvertimeRow {
    id: number;
    date: string | null;
    hours: number;
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

    const form = useForm({ date: '', hours: '', reason: '' });

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
                                    <input
                                        type="date"
                                        value={form.data.date}
                                        onChange={(event) =>
                                            form.setData(
                                                'date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.date,
                                        )}
                                    />
                                </Field>

                                <Field
                                    label="Jumlah Jam"
                                    required
                                    error={form.errors.hours}
                                    hint="Kelipatan 0,5 jam. Maksimal 12 jam."
                                >
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0.5"
                                        max="12"
                                        value={form.data.hours}
                                        onChange={(event) =>
                                            form.setData(
                                                'hours',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="2"
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.hours,
                                        )}
                                    />
                                </Field>

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
                                        ...btnP,
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
                                                    {row.hours.toLocaleString(
                                                        'id-ID',
                                                    )}{' '}
                                                    jam
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
