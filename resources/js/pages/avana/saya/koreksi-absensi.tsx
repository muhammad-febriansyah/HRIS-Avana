import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { storeCorrection } from '@/actions/App/Http/Controllers/Avana/EssAttendanceController';
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
    StatusPill,
    textareaStyle,
    withError,
} from './components';

interface CorrectionRow {
    id: number;
    date: string | null;
    requested_clock_in: string | null;
    requested_clock_out: string | null;
    reason: string | null;
    status: string;
}

type FlashProps = { flash?: { success?: string } };

export default function SayaKoreksiAbsensi({
    corrections,
}: {
    corrections: CorrectionRow[];
}) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        date: '',
        requested_clock_in: '',
        requested_clock_out: '',
        reason: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeCorrection.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Koreksi Absensi" />
            <PageShell>
                <PageHeader
                    title="Koreksi Absensi"
                    subtitle="Ajukan perbaikan jam masuk/pulang yang tidak tercatat dengan benar."
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
                        <Panel title="Pengajuan Baru">
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
                                        label="Jam Masuk"
                                        error={form.errors.requested_clock_in}
                                    >
                                        <input
                                            type="time"
                                            value={form.data.requested_clock_in}
                                            onChange={(event) =>
                                                form.setData(
                                                    'requested_clock_in',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                inputStyle,
                                                !!form.errors
                                                    .requested_clock_in,
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        label="Jam Pulang"
                                        error={form.errors.requested_clock_out}
                                    >
                                        <input
                                            type="time"
                                            value={
                                                form.data.requested_clock_out
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'requested_clock_out',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                inputStyle,
                                                !!form.errors
                                                    .requested_clock_out,
                                            )}
                                        />
                                    </Field>
                                </div>

                                <Field
                                    label="Alasan"
                                    required
                                    error={form.errors.reason}
                                    hint="Isi minimal salah satu jam, lalu jelaskan kenapa perlu dikoreksi."
                                >
                                    <textarea
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="mis. lupa absen pulang karena meeting di klien"
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
                        title="Riwayat Pengajuan"
                        subtitle={`${corrections.length.toLocaleString('id-ID')} pengajuan`}
                        padded={false}
                    >
                        {corrections.length === 0 ? (
                            <EmptyState
                                icon="clock-alert"
                                message="Belum ada pengajuan koreksi."
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
                                            <th style={thCell}>Masuk</th>
                                            <th style={thCell}>Pulang</th>
                                            <th style={thCell}>Alasan</th>
                                            <th style={thCell}>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {corrections.map((row) => (
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
                                                    {row.requested_clock_in ??
                                                        '—'}
                                                </td>
                                                <td style={cell}>
                                                    {row.requested_clock_out ??
                                                        '—'}
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
