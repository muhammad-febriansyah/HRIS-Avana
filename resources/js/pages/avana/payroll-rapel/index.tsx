import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import SalaryRapelController from '@/actions/App/Http/Controllers/Avana/SalaryRapelController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, card } from '@/lib/avana';

interface Rapel {
    id: number;
    employee: string | null;
    employee_number: string | null;
    component: string | null;
    old_amount: number;
    new_amount: number;
    effective_from: string | null;
    posting_date: string | null;
    months: number;
    total: number;
    reason: string;
    status: string;
    approver: string | null;
}

interface Props {
    rapels: Rapel[];
    employeeOptions: { id: number; name: string; nik: string | null }[];
    componentOptions: { id: number; name: string }[];
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
    padding: '9px 16px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};

const rupiah = (n: number) =>
    (n < 0 ? '− Rp ' : 'Rp ') + Math.abs(n).toLocaleString('id-ID');

export default function PayrollRapel({
    rapels,
    employeeOptions,
    componentOptions,
}: Props) {
    const today = new Date().toISOString().slice(0, 10);

    const form = useForm({
        employee_id: '',
        payroll_component_id: '',
        label: '',
        old_amount: '',
        new_amount: '',
        effective_from: '',
        posting_date: today,
        reason: '',
    });

    const submit = () => {
        form.post(SalaryRapelController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Rapel gaji disimpan');
                form.reset('old_amount', 'new_amount', 'reason', 'label');
            },
            onError: () => toast.error('Periksa isian rapel'),
        });
    };

    const approve = (id: number) =>
        router.post(
            SalaryRapelController.approve(id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Rapel disetujui'),
            },
        );

    const del = (id: number) =>
        router.delete(SalaryRapelController.destroy(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Rapel dihapus'),
        });

    return (
        <>
            <Head title="Rapel Gaji" />
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
                    <span>Proses</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Rapel Gaji</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Rapel Gaji
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Selisih gaji masa lalu (kenaikan berlaku surut). Sistem
                    hitung (baru − lama) × jumlah bulan, dibayar sekali pada
                    periode tanggal posting. Taxable sebagai penghasilan.
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
                        Tambah Rapel
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.6fr 1.2fr 130px 130px',
                            gap: 12,
                            marginBottom: 12,
                        }}
                    >
                        <SearchableSelect
                            value={form.data.employee_id}
                            onChange={(v) => form.setData('employee_id', v)}
                            placeholder="Pilih pegawai (NIK / nama)…"
                            searchPlaceholder="Cari NIK / nama…"
                            options={employeeOptions.map((e) => ({
                                value: String(e.id),
                                label: (e.nik ? e.nik + ' · ' : '') + e.name,
                            }))}
                        />
                        <SearchableSelect
                            value={form.data.payroll_component_id}
                            onChange={(v) =>
                                form.setData('payroll_component_id', v)
                            }
                            placeholder="Komponen (opsional)…"
                            searchPlaceholder="Cari komponen…"
                            allowClear
                            options={componentOptions.map((c) => ({
                                value: String(c.id),
                                label: c.name,
                            }))}
                        />
                        <input
                            style={input}
                            type="number"
                            placeholder="Nominal lama"
                            value={form.data.old_amount}
                            onChange={(e) =>
                                form.setData('old_amount', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            type="number"
                            placeholder="Nominal baru"
                            value={form.data.new_amount}
                            onChange={(e) =>
                                form.setData('new_amount', e.target.value)
                            }
                        />
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '150px 150px 1.4fr auto',
                            gap: 12,
                            alignItems: 'center',
                        }}
                    >
                        <div>
                            <div
                                style={{
                                    fontSize: 11,
                                    color: C.faint,
                                    marginBottom: 3,
                                }}
                            >
                                Berlaku sejak
                            </div>
                            <input
                                style={input}
                                type="date"
                                value={form.data.effective_from}
                                onChange={(e) =>
                                    form.setData(
                                        'effective_from',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <div
                                style={{
                                    fontSize: 11,
                                    color: C.faint,
                                    marginBottom: 3,
                                }}
                            >
                                Dibayar (posting)
                            </div>
                            <input
                                style={input}
                                type="date"
                                value={form.data.posting_date}
                                onChange={(e) =>
                                    form.setData('posting_date', e.target.value)
                                }
                            />
                        </div>
                        <input
                            style={input}
                            placeholder="Alasan / keterangan (mis. SK kenaikan)"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
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
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                            }}
                        >
                            <thead>
                                <tr>
                                    {[
                                        'Posting',
                                        'Pegawai',
                                        'Komponen',
                                        'Selisih/bln',
                                        'Bulan',
                                        'Total Rapel',
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
                                {rapels.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada rapel gaji.
                                        </td>
                                    </tr>
                                ) : (
                                    rapels.map((r) => (
                                        <tr key={r.id}>
                                            <td style={td}>{r.posting_date}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {r.employee ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {r.component ?? 'Gaji'}
                                            </td>
                                            <td style={td}>
                                                {rupiah(
                                                    r.new_amount - r.old_amount,
                                                )}
                                            </td>
                                            <td style={td}>{r.months}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color:
                                                        r.total < 0
                                                            ? C.red
                                                            : '#15803D',
                                                    fontWeight: 700,
                                                }}
                                            >
                                                {rupiah(r.total)}
                                            </td>
                                            <td style={td}>
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        fontWeight: 700,
                                                        padding: '3px 9px',
                                                        borderRadius: 6,
                                                        color:
                                                            r.status ===
                                                            'approved'
                                                                ? '#15803D'
                                                                : '#B45309',
                                                        background:
                                                            r.status ===
                                                            'approved'
                                                                ? '#DCFCE7'
                                                                : '#FEF3C7',
                                                    }}
                                                >
                                                    {r.status === 'approved'
                                                        ? 'Disetujui'
                                                        : 'Menunggu'}
                                                </span>
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {r.status !== 'approved' && (
                                                    <button
                                                        onClick={() =>
                                                            approve(r.id)
                                                        }
                                                        style={{
                                                            ...primaryBtn,
                                                            padding: '5px 10px',
                                                            fontSize: 12,
                                                            marginRight: 8,
                                                        }}
                                                    >
                                                        Setujui
                                                    </button>
                                                )}
                                                <button
                                                    onClick={() => del(r.id)}
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
