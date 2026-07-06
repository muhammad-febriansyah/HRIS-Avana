import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import SalaryMasterController from '@/actions/App/Http/Controllers/Avana/SalaryMasterController';
import { AIcon, C, card } from '@/lib/avana';

interface Master {
    id: number;
    code: string;
    category: string;
    note: string | null;
    is_active: boolean;
    employees_count: number;
    included_count: number;
}

interface Props {
    masters: Master[];
}

const input: React.CSSProperties = {
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    width: '100%',
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

export default function PayrollMasterGaji({ masters }: Props) {
    const [adding, setAdding] = useState(false);
    const [q, setQ] = useState('');
    const form = useForm({ code: '', category: '', note: '' });

    const submit = () => {
        form.post(SalaryMasterController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Master Gaji disimpan');
                form.reset();
                setAdding(false);
            },
            onError: () => toast.error('Periksa isian master gaji'),
        });
    };

    const del = (m: Master) => {
        if (!confirm(`Hapus master gaji ${m.code}?`)) {
            return;
        }
        router.delete(SalaryMasterController.destroy(m.id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Master Gaji dihapus'),
        });
    };

    const rows = masters.filter(
        (m) =>
            m.code.toLowerCase().includes(q.toLowerCase()) ||
            m.category.toLowerCase().includes(q.toLowerCase()),
    );

    return (
        <>
            <Head title="Master Gaji" />
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
                    <span>Komponen</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Master Gaji</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 18px',
                    }}
                >
                    Master Gaji
                </h1>

                {adding && (
                    <div style={{ ...card, padding: 18, marginBottom: 16 }}>
                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 14,
                            }}
                        >
                            Tambah Master Gaji
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1.4fr 1.4fr auto',
                                gap: 12,
                                alignItems: 'center',
                            }}
                        >
                            <input
                                style={input}
                                placeholder="Kode"
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData('code', e.target.value)
                                }
                            />
                            <input
                                style={input}
                                placeholder="Kategori Gaji"
                                value={form.data.category}
                                onChange={(e) =>
                                    form.setData('category', e.target.value)
                                }
                            />
                            <input
                                style={input}
                                placeholder="Keterangan (opsional)"
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
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
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginTop: 10,
                            }}
                        >
                            Setelah disimpan, klik ikon Setting untuk atur
                            komponen & perhitungan.
                        </div>
                    </div>
                )}

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            padding: 16,
                            gap: 12,
                        }}
                    >
                        <input
                            style={{ ...input, maxWidth: 320 }}
                            placeholder="Cari.."
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <button
                            style={primaryBtn}
                            onClick={() => setAdding((v) => !v)}
                        >
                            + Tambah Data
                        </button>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                            }}
                        >
                            <thead>
                                <tr>
                                    {[
                                        'No',
                                        'Kode',
                                        'Kategori Gaji',
                                        'Keterangan',
                                        'Komponen',
                                        'Pegawai',
                                        'Status',
                                        'Setting',
                                        'Kontrol',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada Master Gaji.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((m, i) => (
                                        <tr key={m.id}>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.faint,
                                                }}
                                            >
                                                {i + 1}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {m.code}
                                            </td>
                                            <td style={td}>{m.category}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {m.note ?? '—'}
                                            </td>
                                            <td style={td}>
                                                {m.included_count}
                                            </td>
                                            <td style={td}>
                                                {m.employees_count}
                                            </td>
                                            <td style={td}>
                                                <span
                                                    style={{
                                                        display: 'inline-block',
                                                        width: 10,
                                                        height: 10,
                                                        borderRadius: '50%',
                                                        background: m.is_active
                                                            ? '#22C55E'
                                                            : C.faint,
                                                    }}
                                                    title={
                                                        m.is_active
                                                            ? 'Aktif'
                                                            : 'Nonaktif'
                                                    }
                                                />
                                            </td>
                                            <td style={td}>
                                                <button
                                                    title="Setting"
                                                    onClick={() =>
                                                        router.visit(
                                                            SalaryMasterController.setting(
                                                                m.id,
                                                            ).url,
                                                        )
                                                    }
                                                    style={{
                                                        border: `1px solid ${C.line}`,
                                                        background: C.surface,
                                                        borderRadius: 7,
                                                        padding: '6px 8px',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="settings"
                                                        size={15}
                                                        color={C.muted}
                                                    />
                                                </button>
                                            </td>
                                            <td style={td}>
                                                <button
                                                    title="Hapus"
                                                    onClick={() => del(m)}
                                                    style={{
                                                        border: 'none',
                                                        background:
                                                            'transparent',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="trash-2"
                                                        size={15}
                                                        color={C.red}
                                                    />
                                                </button>
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
