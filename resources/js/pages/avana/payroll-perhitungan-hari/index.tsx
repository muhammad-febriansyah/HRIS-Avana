import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import DayCalcMethodController from '@/actions/App/Http/Controllers/Avana/DayCalcMethodController';
import { AIcon, ActionBtn, C, card } from '@/lib/avana';

interface Method {
    id: number;
    code: string;
    name: string;
    basis: string;
    divisor: number | null;
    description: string | null;
    is_active: boolean;
}

interface Props {
    methods: Method[];
    basisOptions: string[];
}

const BASIS_LABEL: Record<string, string> = {
    absen: 'Absen',
    hari_kerja: 'Hari Kerja',
    hari_kalender: 'Hari Kalender',
    formula: 'Formula',
};

const input: React.CSSProperties = {
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    width: '100%',
    background: '#fff',
};
const th: React.CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const primaryBtn: React.CSSProperties = {
    padding: '9px 16px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};

export default function PerhitunganHari({ methods, basisOptions }: Props) {
    const form = useForm({
        code: '',
        name: '',
        basis: 'hari_kerja',
        divisor: '',
        description: '',
        is_active: true,
    });

    const submit = () => {
        form.post(DayCalcMethodController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Perhitungan Hari disimpan');
                form.reset('code', 'name', 'divisor', 'description');
            },
            onError: () => toast.error('Periksa isian'),
        });
    };

    const del = (id: number) =>
        router.delete(DayCalcMethodController.destroy(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Perhitungan Hari dihapus'),
        });

    return (
        <>
            <Head title="Perhitungan Hari" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 7,
                    }}
                >
                    <span>Payroll</span>
                    <AIcon name="chevron-right" size={13} />
                    <span>Setting Komponen</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Perhitungan Hari</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Perhitungan Hari
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Metode penghitungan hari untuk prorata gaji: pasangan basis
                    (Absen / Hari Kerja / Hari Kalender / Formula) dengan pembagi.
                    Master Gaji memilih salah satu metode ini.
                </div>

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div
                        style={{
                            fontSize: 14,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 14,
                        }}
                    >
                        Tambah Metode
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                '1fr 1.4fr 1.2fr 90px 1.6fr auto',
                            gap: 12,
                            alignItems: 'center',
                        }}
                    >
                        <input
                            style={input}
                            placeholder="Kode"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                        />
                        <input
                            style={input}
                            placeholder="Nama metode"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        <select
                            style={input}
                            value={form.data.basis}
                            onChange={(e) =>
                                form.setData('basis', e.target.value)
                            }
                        >
                            {basisOptions.map((b) => (
                                <option key={b} value={b}>
                                    {BASIS_LABEL[b] ?? b}
                                </option>
                            ))}
                        </select>
                        <input
                            style={input}
                            type="number"
                            placeholder="Pembagi"
                            value={form.data.divisor}
                            onChange={(e) =>
                                form.setData('divisor', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            placeholder="Keterangan (opsional)"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <button
                            style={primaryBtn}
                            disabled={form.processing}
                            onClick={submit}
                        >
                            Simpan
                        </button>
                    </div>
                </div>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{ width: '100%', borderCollapse: 'collapse' }}
                        >
                            <thead>
                                <tr>
                                    {[
                                        'Kode',
                                        'Nama',
                                        'Basis',
                                        'Pembagi',
                                        'Keterangan',
                                        'Status',
                                        '',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {methods.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada metode.
                                        </td>
                                    </tr>
                                ) : (
                                    methods.map((m) => (
                                        <tr key={m.id}>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {m.code}
                                            </td>
                                            <td style={td}>{m.name}</td>
                                            <td style={td}>
                                                {BASIS_LABEL[m.basis] ?? m.basis}
                                            </td>
                                            <td style={td}>
                                                {m.divisor ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {m.description ?? '—'}
                                            </td>
                                            <td style={td}>
                                                {m.is_active ? (
                                                    <span
                                                        style={{
                                                            color: '#16A34A',
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        Aktif
                                                    </span>
                                                ) : (
                                                    <span
                                                        style={{
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() => del(m.id)}
                                                />
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
