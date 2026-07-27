import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import PayrollUmrController from '@/actions/App/Http/Controllers/Avana/PayrollUmrController';
import { ActionBtn, AIcon, C, card, RupiahInput } from '@/lib/avana';

interface Rate {
    id: number;
    branch_id: number | null;
    branch: string | null;
    region: string | null;
    year: number;
    amount: number;
    note: string | null;
}

interface Props {
    rates: Rate[];
    branchOptions: { id: number; name: string }[];
}

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

const rupiah = (n: number) => 'Rp ' + n.toLocaleString('id-ID');

export default function PayrollUmr({ rates, branchOptions }: Props) {
    const form = useForm({
        branch_id: '',
        region: '',
        year: String(new Date().getFullYear()),
        amount: '',
        note: '',
    });

    const submit = () => {
        form.post(PayrollUmrController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('UMR disimpan');
                form.reset('amount', 'region', 'note');
            },
            onError: () => toast.error('Periksa isian UMR'),
        });
    };

    const del = (id: number) =>
        router.delete(PayrollUmrController.destroy(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('UMR dihapus'),
        });

    return (
        <>
            <Head title="UMR" />
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
                    <span style={{ color: C.muted }}>UMR</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    UMR
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Upah minimum per cabang/area &amp; tahun. Dipakai formula
                    komponen bertipe UMR &amp; basis "sesuai UMR".
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
                        Tambah UMR
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.4fr 1.4fr 90px 1.2fr auto',
                            gap: 12,
                            alignItems: 'center',
                        }}
                    >
                        <select
                            style={input}
                            value={form.data.branch_id}
                            onChange={(e) =>
                                form.setData('branch_id', e.target.value)
                            }
                        >
                            <option value="">Cabang: Default (semua)</option>
                            {branchOptions.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                        <input
                            style={input}
                            placeholder="Region/area (opsional)"
                            value={form.data.region}
                            onChange={(e) =>
                                form.setData('region', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            type="number"
                            placeholder="Tahun"
                            value={form.data.year}
                            onChange={(e) =>
                                form.setData('year', e.target.value)
                            }
                        />
                        <RupiahInput
                            value={form.data.amount}
                            onChange={(raw) => form.setData('amount', raw)}
                            placeholder="Nilai UMR"
                            style={input}
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
                </div>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
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
                                        'Cabang',
                                        'Region',
                                        'Tahun',
                                        'Nilai UMR',
                                        'Keterangan',
                                        '',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rates.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada UMR.
                                        </td>
                                    </tr>
                                ) : (
                                    rates.map((r) => (
                                        <tr key={r.id}>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {r.branch ?? 'Default (semua)'}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {r.region ?? '—'}
                                            </td>
                                            <td style={td}>{r.year}</td>
                                            <td style={td}>
                                                {rupiah(r.amount)}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {r.note ?? '—'}
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
                                                    onClick={() => del(r.id)}
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
