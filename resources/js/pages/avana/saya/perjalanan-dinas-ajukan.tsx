import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, btnProcess, C, RupiahInput } from '@/lib/avana';
import {
    Field,
    inputStyle,
    PageShell,
    Panel,
    selectStyle,
    textareaStyle,
    withError,
} from './components';

/** Transport options offered by the form; the field stays free text. */
const TRANSPORTS = [
    'Pesawat',
    'Kereta Api',
    'Bus',
    'Mobil Dinas',
    'Kapal',
    'Lainnya',
];

export default function SayaPerjalananDinasAjukan() {
    const form = useForm({
        destination: '',
        purpose: '',
        start_date: '',
        end_date: '',
        transport: '',
        estimated_cost: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/avana/saya/perjalanan-dinas');
    };

    return (
        <>
            <Head title="Ajukan Perjalanan Dinas" />
            <PageShell>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <span>Layanan Saya</span>
                    <AIcon name="chevron-right" size={13} />
                    <Link
                        href="/avana/saya/perjalanan-dinas"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Perjalanan Dinas Saya
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Ajukan</span>
                </div>

                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: 0,
                        letterSpacing: '-.01em',
                    }}
                >
                    Ajukan Perjalanan Dinas
                </h1>
                <div
                    style={{
                        fontSize: 14,
                        color: C.muted,
                        marginTop: 4,
                        marginBottom: 22,
                    }}
                >
                    Pengajuan masuk berstatus Menunggu, lalu ditinjau HR atau
                    atasanmu.
                </div>

                <form onSubmit={submit}>
                    <Panel
                        title="Rincian Perjalanan"
                        subtitle="Uang harian ditetapkan oleh penyetuju, bukan diisi di sini."
                    >
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fit, minmax(240px, 1fr))',
                                gap: 16,
                            }}
                        >
                            <Field
                                label="Tujuan"
                                required
                                error={form.errors.destination}
                            >
                                <input
                                    value={form.data.destination}
                                    onChange={(event) =>
                                        form.setData(
                                            'destination',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="mis. Kantor Cabang Surabaya"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.destination,
                                    )}
                                />
                            </Field>

                            <Field
                                label="Transportasi"
                                error={form.errors.transport}
                            >
                                <select
                                    value={form.data.transport}
                                    onChange={(event) =>
                                        form.setData(
                                            'transport',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.transport,
                                    )}
                                >
                                    <option value="">— Pilih —</option>
                                    {TRANSPORTS.map((transport) => (
                                        <option
                                            key={transport}
                                            value={transport}
                                        >
                                            {transport}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                label="Berangkat"
                                required
                                error={form.errors.start_date}
                            >
                                <DatePicker
                                    value={form.data.start_date}
                                    onChange={(value) =>
                                        form.setData('start_date', value)
                                    }
                                    placeholder="Pilih tanggal berangkat"
                                    hasError={!!form.errors.start_date}
                                    width="100%"
                                />
                            </Field>

                            <Field
                                label="Kembali"
                                required
                                error={form.errors.end_date}
                            >
                                <DatePicker
                                    value={form.data.end_date}
                                    onChange={(value) =>
                                        form.setData('end_date', value)
                                    }
                                    placeholder="Pilih tanggal kembali"
                                    hasError={!!form.errors.end_date}
                                    width="100%"
                                />
                            </Field>

                            <Field
                                label="Estimasi Biaya"
                                error={form.errors.estimated_cost}
                                hint="Perkiraan tiket, penginapan, dan lain-lain."
                            >
                                <RupiahInput
                                    value={form.data.estimated_cost}
                                    onChange={(raw) =>
                                        form.setData('estimated_cost', raw)
                                    }
                                    invalid={!!form.errors.estimated_cost}
                                />
                            </Field>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <Field
                                label="Keperluan"
                                error={form.errors.purpose}
                            >
                                <textarea
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            'purpose',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="mis. audit cabang & pelatihan tim baru"
                                    style={withError(
                                        textareaStyle,
                                        !!form.errors.purpose,
                                    )}
                                />
                            </Field>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                                marginTop: 20,
                            }}
                        >
                            <Link
                                href="/avana/saya/perjalanan-dinas"
                                style={{
                                    ...btnOut,
                                    height: 44,
                                    justifyContent: 'center',
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon name="x" size={16} color={C.text} />
                                Batal
                            </Link>
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
            </PageShell>
        </>
    );
}
