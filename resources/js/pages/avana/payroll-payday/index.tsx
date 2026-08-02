import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import PaydayController from '@/actions/App/Http/Controllers/Avana/PaydayController';
import { ActionBtn, AIcon, C, card } from '@/lib/avana';

interface Payday {
    id: number;
    code: string;
    name: string;
    pay_mode: string;
    pay_day: number | null;
    pay_label: string;
    next_pay_date: string;
    cut_off_start_day: number | null;
    cut_off_end_day: number | null;
    cut_off_label: string | null;
    description: string | null;
    is_active: boolean;
    employees_count: number;
}

interface EmployeeRow {
    id: number;
    name: string;
    number: string | null;
    payday_id: number | null;
}

interface Props {
    paydays: Payday[];
    employees: EmployeeRow[];
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
const label: React.CSSProperties = {
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    marginBottom: 6,
    display: 'block',
};

export default function PayrollPayday({ paydays, employees }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [target, setTarget] = useState<string>('');

    const form = useForm({
        code: '',
        name: '',
        pay_mode: 'date',
        pay_day: '25',
        cut_off_start_day: '',
        cut_off_end_day: '',
        description: '',
        is_active: true as boolean,
    });

    const submit = () =>
        form.post(PaydayController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Kelompok payday dibuat');
                form.reset('code', 'name', 'description');
            },
            onError: () => toast.error('Periksa isian kelompok payday'),
        });

    const del = (id: number) =>
        router.delete(PaydayController.destroy(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Kelompok payday dihapus'),
        });

    const assign = () => {
        if (selected.length === 0) {
            toast.error('Pilih karyawan dulu');

            return;
        }

        router.post(
            PaydayController.assign().url,
            { payday_id: target === '' ? null : Number(target), employee_ids: selected },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Mapping karyawan disimpan');
                    setSelected([]);
                },
                onError: () => toast.error('Mapping gagal disimpan'),
            },
        );
    };

    const toggle = (id: number) =>
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const groupName = (id: number | null) =>
        id === null ? '—' : (paydays.find((p) => p.id === id)?.name ?? '—');

    return (
        <>
            <Head title="Mapping Payday" />
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
                    <span style={{ color: C.muted }}>Mapping Payday</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 4px' }}>
                    Mapping Payday
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Kapan gaji dibayar dan rentang kehadiran mana yang ikut dihitung. Cut-off kelompok
                    menggantikan periode absensi Master Gaji saat Payroll Run.
                </div>

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div style={{ fontSize: 14, fontWeight: 600, color: C.navy, marginBottom: 14 }}>
                        Tambah Kelompok
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '0.8fr 1.4fr 1fr 0.7fr 0.7fr 0.7fr auto',
                            gap: 12,
                            alignItems: 'end',
                        }}
                    >
                        <div>
                            <span style={label}>Kode</span>
                            <input
                                style={input}
                                placeholder="PD-PUSAT"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Nama kelompok</span>
                            <input
                                style={input}
                                placeholder="Kantor Pusat & Staff"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Payday</span>
                            <select
                                style={input}
                                value={form.data.pay_mode}
                                onChange={(e) => form.setData('pay_mode', e.target.value)}
                            >
                                <option value="date">Tanggal tetap</option>
                                <option value="end_of_month">Akhir bulan</option>
                            </select>
                        </div>
                        <div>
                            <span style={label}>Tanggal</span>
                            <input
                                style={input}
                                type="number"
                                min={1}
                                max={31}
                                disabled={form.data.pay_mode === 'end_of_month'}
                                value={form.data.pay_mode === 'end_of_month' ? '' : form.data.pay_day}
                                onChange={(e) => form.setData('pay_day', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Cut-off dari</span>
                            <input
                                style={input}
                                type="number"
                                min={1}
                                max={31}
                                value={form.data.cut_off_start_day}
                                onChange={(e) => form.setData('cut_off_start_day', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>s.d.</span>
                            <input
                                style={input}
                                type="number"
                                min={1}
                                max={31}
                                value={form.data.cut_off_end_day}
                                onChange={(e) => form.setData('cut_off_end_day', e.target.value)}
                            />
                        </div>
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

                <div style={{ ...card, padding: 0, overflow: 'hidden', marginBottom: 18 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr>
                                    {[
                                        'Kelompok',
                                        'Payday',
                                        'Pembayaran berikutnya',
                                        'Cut-off Kehadiran',
                                        'Karyawan',
                                        '',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {paydays.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} style={{ ...td, textAlign: 'center', color: C.faint }}>
                                            Belum ada kelompok payday.
                                        </td>
                                    </tr>
                                ) : (
                                    paydays.map((p) => (
                                        <tr key={p.id}>
                                            <td style={{ ...td, fontWeight: 600, color: C.navy }}>
                                                {p.name}
                                                <div style={{ fontSize: 12, fontWeight: 400, color: C.muted }}>
                                                    {p.code}
                                                </div>
                                            </td>
                                            <td style={td}>{p.pay_label}</td>
                                            <td style={{ ...td, color: C.muted }}>{p.next_pay_date}</td>
                                            <td style={td}>
                                                {p.cut_off_label ?? (
                                                    <span style={{ color: C.faint }}>Ikut Master Gaji</span>
                                                )}
                                            </td>
                                            <td style={td}>{p.employees_count}</td>
                                            <td style={{ ...td, textAlign: 'right' }}>
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() => del(p.id)}
                                                />
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style={{ ...card, padding: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: 12,
                            marginBottom: 14,
                        }}
                    >
                        <div style={{ fontSize: 14, fontWeight: 600, color: C.navy }}>
                            Assign Karyawan ({selected.length} dipilih)
                        </div>
                        <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                            <select
                                style={{ ...input, width: 220 }}
                                value={target}
                                onChange={(e) => setTarget(e.target.value)}
                            >
                                <option value="">— Lepas dari kelompok —</option>
                                {paydays.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <button style={primaryBtn} onClick={assign}>
                                <AIcon name="check" size={15} color="#fff" />
                                Terapkan
                            </button>
                        </div>
                    </div>
                    <div style={{ maxHeight: 340, overflowY: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr>
                                    {['', 'Karyawan', 'NIK', 'Kelompok saat ini'].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {employees.map((e) => (
                                    <tr key={e.id}>
                                        <td style={{ ...td, width: 36 }}>
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(e.id)}
                                                onChange={() => toggle(e.id)}
                                            />
                                        </td>
                                        <td style={{ ...td, fontWeight: 600, color: C.navy }}>{e.name}</td>
                                        <td style={{ ...td, color: C.muted }}>{e.number ?? '—'}</td>
                                        <td style={td}>{groupName(e.payday_id)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
