import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import PayrollCorrectionController from '@/actions/App/Http/Controllers/Avana/PayrollCorrectionController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, ActionBtn, C, card } from '@/lib/avana';

interface Correction {
    id: number;
    employee: string | null;
    employee_number: string | null;
    correction_date: string | null;
    type: string;
    amount: number;
    points: string | null;
    reason: string;
    status: string;
    approver: string | null;
}

interface Props {
    corrections: Correction[];
    employeeOptions: { id: number; name: string; nik: string | null }[];
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

const rupiah = (n: number) => 'Rp ' + n.toLocaleString('id-ID');

export default function PayrollKoreksi({
    corrections,
    employeeOptions,
}: Props) {
    const form = useForm({
        employee_id: '',
        correction_date: new Date().toISOString().slice(0, 10),
        type: 'earning',
        amount: '',
        points: '',
        reason: '',
        file: null as File | null,
    });

    const submit = () => {
        form.post(PayrollCorrectionController.store().url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Koreksi gaji disimpan');
                form.reset('amount', 'points', 'reason', 'file');
            },
            onError: () => toast.error('Periksa isian koreksi'),
        });
    };

    const approve = (id: number) =>
        router.post(
            PayrollCorrectionController.approve(id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Koreksi disetujui'),
            },
        );

    const del = (id: number) =>
        router.delete(PayrollCorrectionController.destroy(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Koreksi dihapus'),
        });

    return (
        <>
            <Head title="Koreksi Gaji" />
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
                    <span style={{ color: C.muted }}>Koreksi Gaji</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Koreksi Gaji
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Penyesuaian gaji manual per pegawai. Setelah disetujui,
                    otomatis masuk ke proses gaji periode terkait.
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
                        Tambah Koreksi
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.6fr 130px 130px 1fr',
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
                        <input
                            style={input}
                            type="date"
                            value={form.data.correction_date}
                            onChange={(e) =>
                                form.setData('correction_date', e.target.value)
                            }
                        />
                        <select
                            style={input}
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            <option value="earning">Pendapatan (+)</option>
                            <option value="deduction">Potongan (−)</option>
                        </select>
                        <input
                            style={input}
                            type="number"
                            placeholder="Nilai"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                        />
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1.6fr auto auto',
                            gap: 12,
                            alignItems: 'center',
                        }}
                    >
                        <input
                            style={input}
                            placeholder="Poin perubahan (opsional)"
                            value={form.data.points}
                            onChange={(e) =>
                                form.setData('points', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            placeholder="Alasan perubahan"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        <input
                            type="file"
                            style={{ fontSize: 12.5, color: C.muted }}
                            onChange={(e) =>
                                form.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
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
                                        'Tanggal',
                                        'Pegawai',
                                        'Jenis',
                                        'Nilai',
                                        'Alasan',
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
                                {corrections.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada koreksi gaji.
                                        </td>
                                    </tr>
                                ) : (
                                    corrections.map((c) => (
                                        <tr key={c.id}>
                                            <td style={td}>
                                                {c.correction_date}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {c.employee ?? '—'}
                                            </td>
                                            <td style={td}>
                                                {c.type === 'deduction'
                                                    ? 'Potongan'
                                                    : 'Pendapatan'}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color:
                                                        c.type === 'deduction'
                                                            ? C.red
                                                            : '#15803D',
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {c.type === 'deduction'
                                                    ? '− '
                                                    : '+ '}
                                                {rupiah(c.amount)}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {c.reason}
                                            </td>
                                            <td style={td}>
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        fontWeight: 700,
                                                        padding: '3px 9px',
                                                        borderRadius: 6,
                                                        color:
                                                            c.status ===
                                                            'approved'
                                                                ? '#15803D'
                                                                : '#B45309',
                                                        background:
                                                            c.status ===
                                                            'approved'
                                                                ? '#DCFCE7'
                                                                : '#FEF3C7',
                                                    }}
                                                >
                                                    {c.status === 'approved'
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
                                                <div
                                                    style={{
                                                        display: 'inline-flex',
                                                        gap: 6,
                                                        justifyContent:
                                                            'flex-end',
                                                        flexWrap: 'wrap',
                                                    }}
                                                >
                                                    {c.status !== 'approved' && (
                                                        <button
                                                            onClick={() =>
                                                                approve(c.id)
                                                            }
                                                            style={{
                                                                ...primaryBtn,
                                                                padding:
                                                                    '5px 10px',
                                                                fontSize: 12,
                                                            }}
                                                        >
                                                            Setujui
                                                        </button>
                                                    )}
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() => del(c.id)}
                                                    />
                                                </div>
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
