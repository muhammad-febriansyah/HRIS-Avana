import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import PaydayController from '@/actions/App/Http/Controllers/Avana/PaydayController';
import { AIcon, ActionBtn, C, card } from '@/lib/avana';

interface Payday {
    id: number;
    code: string;
    name: string;
    pay_day: number;
    cut_off_day: number | null;
    description: string | null;
    is_active: boolean;
    employees_count: number;
}

interface EmployeeOption {
    id: number;
    name: string;
    payday_id: number | null;
}

interface Props {
    paydays: Payday[];
    employees: EmployeeOption[];
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

export default function MappingPayday({ paydays, employees }: Props) {
    const form = useForm({
        code: '',
        name: '',
        pay_day: '25',
        cut_off_day: '',
        description: '',
        is_active: true,
    });

    const [assignTo, setAssignTo] = useState<Payday | null>(null);
    const [checked, setChecked] = useState<Set<number>>(new Set());

    const submit = () => {
        form.post(PaydayController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Payday disimpan');
                form.reset('code', 'name', 'cut_off_day', 'description');
            },
            onError: () => toast.error('Periksa isian'),
        });
    };

    const del = (id: number) =>
        router.delete(PaydayController.destroy(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Payday dihapus'),
        });

    const openAssign = (p: Payday) => {
        setAssignTo(p);
        setChecked(
            new Set(
                employees.filter((e) => e.payday_id === p.id).map((e) => e.id),
            ),
        );
    };

    const toggle = (id: number) => {
        setChecked((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const saveAssign = () => {
        if (!assignTo) {
            return;
        }

        router.post(
            PaydayController.assign(assignTo.id).url,
            { employee_ids: Array.from(checked) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Pegawai dipetakan ke payday');
                    setAssignTo(null);
                },
                onError: () => toast.error('Gagal memetakan'),
            },
        );
    };

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
                    <span>Setting Komponen</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Mapping Payday</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Mapping Payday
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Jadwal tanggal gajian (payday) dan pemetaan karyawan ke tiap
                    jadwal — mis. staf digaji tanggal 25, harian tanggal 1.
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
                        Tambah Payday
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                '1fr 1.4fr 130px 130px 1.6fr auto',
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
                            placeholder="Nama jadwal"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            type="number"
                            min={1}
                            max={31}
                            placeholder="Tgl gajian"
                            value={form.data.pay_day}
                            onChange={(e) =>
                                form.setData('pay_day', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            type="number"
                            min={1}
                            max={31}
                            placeholder="Cut-off"
                            value={form.data.cut_off_day}
                            onChange={(e) =>
                                form.setData('cut_off_day', e.target.value)
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
                                        'Kode',
                                        'Nama',
                                        'Tgl Gajian',
                                        'Cut-off',
                                        'Keterangan',
                                        'Pegawai',
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
                                {paydays.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada payday.
                                        </td>
                                    </tr>
                                ) : (
                                    paydays.map((p) => (
                                        <tr key={p.id}>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {p.code}
                                            </td>
                                            <td style={td}>{p.name}</td>
                                            <td style={td}>Tgl {p.pay_day}</td>
                                            <td style={td}>
                                                {p.cut_off_day
                                                    ? `Tgl ${p.cut_off_day}`
                                                    : '—'}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {p.description ?? '—'}
                                            </td>
                                            <td style={td}>
                                                {p.employees_count} org
                                            </td>
                                            <td style={td}>
                                                {p.is_active ? (
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
                                                    <ActionBtn
                                                        icon="link"
                                                        label="Petakan"
                                                        variant="primary"
                                                        onClick={() =>
                                                            openAssign(p)
                                                        }
                                                    />
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() =>
                                                            del(p.id)
                                                        }
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

                {assignTo && (
                    <div style={{ ...card, padding: 18, marginTop: 18 }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                marginBottom: 14,
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 14,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Petakan Pegawai ke {assignTo.code} —{' '}
                                {assignTo.name}
                            </div>
                            <button
                                onClick={() => setAssignTo(null)}
                                aria-label="Tutup"
                                style={{
                                    width: 30,
                                    height: 30,
                                    border: `1px solid ${C.border}`,
                                    background: C.surface,
                                    borderRadius: 8,
                                    cursor: 'pointer',
                                    color: C.muted,
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name="x" size={16} />
                            </button>
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(240px, 1fr))',
                                gap: 8,
                                marginBottom: 16,
                            }}
                        >
                            {employees.map((e) => {
                                const mapped =
                                    e.payday_id !== null &&
                                    e.payday_id !== assignTo.id;

                                return (
                                    <label
                                        key={e.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            fontSize: 13,
                                            color: C.text,
                                            cursor: 'pointer',
                                            padding: '6px 8px',
                                            borderRadius: 6,
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked.has(e.id)}
                                            onChange={() => toggle(e.id)}
                                        />
                                        <span>{e.name}</span>
                                        {mapped && (
                                            <span
                                                style={{
                                                    fontSize: 11,
                                                    color: C.faint,
                                                }}
                                            >
                                                (payday lain)
                                            </span>
                                        )}
                                    </label>
                                );
                            })}
                        </div>
                        <button
                            style={{ ...primaryBtn, background: C.violet }}
                            onClick={saveAssign}
                        >
                            <AIcon name="save" size={15} color="#fff" />
                            Simpan Pemetaan
                        </button>
                    </div>
                )}
            </div>
        </>
    );
}
