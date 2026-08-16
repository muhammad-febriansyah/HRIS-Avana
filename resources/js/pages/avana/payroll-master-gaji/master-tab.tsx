import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import SalaryMasterController from '@/actions/App/Http/Controllers/Avana/SalaryMasterController';
import { AIcon, ActionBtn, C, card } from '@/lib/avana';

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
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    padding: '9px 16px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};

/** The Master Gaji list, rendered as the first tab of the Master Gaji page. */
export default function MasterGajiTab({ masters = [] }: Props) {
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
            <div>

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
                                style={{ ...primaryBtn, background: C.green }}
                                disabled={form.processing}
                                onClick={submit}
                            >
                                <AIcon name="save" size={15} color="#fff" />
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
                            <AIcon
                                name={adding ? 'x' : 'plus'}
                                size={15}
                                color="#fff"
                            />
                            {adding ? 'Tutup Form' : 'Tambah Data'}
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
                                                <ActionBtn
                                                    icon="settings"
                                                    label="Atur"
                                                    variant="primary"
                                                    onClick={() =>
                                                        router.visit(
                                                            SalaryMasterController.setting(
                                                                m.id,
                                                            ).url,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td style={td}>
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() => del(m)}
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
