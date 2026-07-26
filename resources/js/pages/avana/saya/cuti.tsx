import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnP, C, card, thCell } from '@/lib/avana';
import {
    EmptyState,
    Field,
    formatDate,
    inputStyle,
    PageHeader,
    PageShell,
    Panel,
    selectStyle,
    StatusPill,
    textareaStyle,
    withError,
} from './components';

interface Balance {
    leave_type_id: number;
    leave_type: string | null;
    code: string | null;
    entitled: number;
    used: number;
    pending: number;
    available: number;
}

interface LeaveRow {
    id: number;
    leave_type: string | null;
    start_date: string | null;
    end_date: string | null;
    total_days: number;
    reason: string | null;
    status: string;
}

interface Props {
    year: number;
    balances: Balance[];
    requests: LeaveRow[];
    leaveTypes: { id: number; name: string; requires_attachment: boolean }[];
}

type FlashProps = { flash?: { success?: string } };

export default function SayaCuti({
    year,
    balances,
    requests,
    leaveTypes,
}: Props) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/avana/saya/cuti', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Cuti Saya" />
            <PageShell>
                <PageHeader
                    title="Cuti Saya"
                    subtitle={`Saldo dan pengajuan cuti tahun ${year}.`}
                />

                {/* Balance cards */}
                {balances.length > 0 && (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(220px, 1fr))',
                            gap: 14,
                            marginBottom: 18,
                        }}
                    >
                        {balances.map((balance) => {
                            const pct =
                                balance.entitled > 0
                                    ? Math.min(
                                          (balance.used / balance.entitled) *
                                              100,
                                          100,
                                      )
                                    : 0;

                            return (
                                <div
                                    key={balance.leave_type_id}
                                    style={{ ...card, padding: '18px 20px' }}
                                >
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            fontWeight: 600,
                                            color: C.navy,
                                            marginBottom: 10,
                                        }}
                                    >
                                        {balance.leave_type ?? '—'}
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'baseline',
                                            gap: 6,
                                            marginBottom: 10,
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 26,
                                                fontWeight: 700,
                                                color: C.navy,
                                            }}
                                        >
                                            {balance.available.toLocaleString(
                                                'id-ID',
                                            )}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 12.5,
                                                color: C.muted,
                                            }}
                                        >
                                            dari{' '}
                                            {balance.entitled.toLocaleString(
                                                'id-ID',
                                            )}{' '}
                                            hari
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            height: 7,
                                            borderRadius: 999,
                                            background: C.line,
                                            overflow: 'hidden',
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: `${pct}%`,
                                                height: '100%',
                                                background: C.primary,
                                            }}
                                        />
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                            marginTop: 8,
                                        }}
                                    >
                                        Terpakai{' '}
                                        {balance.used.toLocaleString('id-ID')} ·
                                        Menunggu{' '}
                                        {balance.pending.toLocaleString(
                                            'id-ID',
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1.6fr)',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    <form onSubmit={submit}>
                        <Panel title="Ajukan Cuti">
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                }}
                            >
                                <Field
                                    label="Jenis Cuti"
                                    required
                                    error={form.errors.leave_type_id}
                                >
                                    <select
                                        value={form.data.leave_type_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'leave_type_id',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!form.errors.leave_type_id,
                                        )}
                                    >
                                        <option value="">— Pilih —</option>
                                        {leaveTypes.map((type) => (
                                            <option
                                                key={type.id}
                                                value={String(type.id)}
                                            >
                                                {type.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 12,
                                    }}
                                >
                                    <Field
                                        label="Mulai"
                                        required
                                        error={form.errors.start_date}
                                    >
                                        <input
                                            type="date"
                                            value={form.data.start_date}
                                            onChange={(event) =>
                                                form.setData(
                                                    'start_date',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                inputStyle,
                                                !!form.errors.start_date,
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        label="Selesai"
                                        required
                                        error={form.errors.end_date}
                                    >
                                        <input
                                            type="date"
                                            value={form.data.end_date}
                                            onChange={(event) =>
                                                form.setData(
                                                    'end_date',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                inputStyle,
                                                !!form.errors.end_date,
                                            )}
                                        />
                                    </Field>
                                </div>

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
                                        placeholder="Opsional"
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
                        title="Riwayat Cuti"
                        subtitle={`${requests.length.toLocaleString('id-ID')} pengajuan`}
                        padded={false}
                    >
                        {requests.length === 0 ? (
                            <EmptyState
                                icon="palmtree"
                                message="Belum ada pengajuan cuti."
                            />
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 560,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={thCell}>Jenis</th>
                                            <th style={thCell}>Periode</th>
                                            <th style={thCell}>Hari</th>
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
                                                    {row.leave_type ?? '—'}
                                                </td>
                                                <td style={cell}>
                                                    {formatDate(row.start_date)}{' '}
                                                    – {formatDate(row.end_date)}
                                                </td>
                                                <td style={cell}>
                                                    {row.total_days.toLocaleString(
                                                        'id-ID',
                                                    )}
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
