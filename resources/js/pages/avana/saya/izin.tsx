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
    selectStyle,
    StatusPill,
    textareaStyle,
    withError,
} from './components';

interface PermissionRow {
    id: number;
    start_date: string | null;
    end_date: string | null;
    type: string;
    start_time: string | null;
    end_time: string | null;
    reason: string | null;
    status: string;
}

interface Props {
    requests: PermissionRow[];
    types: { value: string; label: string }[];
}

type FlashProps = { flash?: { success?: string } };

export default function SayaIzin({ requests, types }: Props) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        start_date: '',
        end_date: '',
        type: '',
        start_time: '',
        end_time: '',
        reason: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const isSingleDay =
        form.data.start_date !== '' &&
        form.data.start_date === form.data.end_date;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/avana/saya/izin', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const typeLabel = (value: string) =>
        types.find((type) => type.value === value)?.label ?? value;

    return (
        <>
            <Head title="Izin Saya" />
            <PageShell>
                <PageHeader
                    title="Izin Saya"
                    subtitle="Pengajuan izin sakit, keperluan pribadi, atau dinas luar."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1.6fr)',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    <form onSubmit={submit}>
                        <Panel title="Ajukan Izin">
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                }}
                            >
                                <Field
                                    label="Jenis Izin"
                                    required
                                    error={form.errors.type}
                                >
                                    <select
                                        value={form.data.type}
                                        onChange={(event) =>
                                            form.setData(
                                                'type',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!form.errors.type,
                                        )}
                                    >
                                        <option value="">— Pilih —</option>
                                        {types.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
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

                                {isSingleDay && (
                                    <div
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns: '1fr 1fr',
                                            gap: 12,
                                        }}
                                    >
                                        <Field
                                            label="Jam Mulai"
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
                                )}

                                <Field
                                    label="Alasan"
                                    error={form.errors.reason}
                                    hint="Jam hanya bisa diisi untuk izin satu hari."
                                >
                                    <textarea
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
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
                        title="Riwayat Izin"
                        subtitle={`${requests.length.toLocaleString('id-ID')} pengajuan`}
                        padded={false}
                    >
                        {requests.length === 0 ? (
                            <EmptyState
                                icon="file-clock"
                                message="Belum ada pengajuan izin."
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
                                            <th style={thCell}>Jam</th>
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
                                                    {typeLabel(row.type)}
                                                </td>
                                                <td style={cell}>
                                                    {formatDate(row.start_date)}{' '}
                                                    – {formatDate(row.end_date)}
                                                </td>
                                                <td style={cell}>
                                                    {row.start_time
                                                        ? `${row.start_time}–${row.end_time ?? '…'}`
                                                        : 'Sehari penuh'}
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
