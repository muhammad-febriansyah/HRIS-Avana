import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import SalaryMasterController from '@/actions/App/Http/Controllers/Avana/SalaryMasterController';
import { AIcon, C, card } from '@/lib/avana';

interface MasterComponent {
    id: number;
    payroll_component_id: number;
    name: string | null;
    group: string | null;
    is_prorate: boolean;
    is_overtime_base: boolean;
}

interface Master {
    id: number;
    code: string;
    category: string;
    note: string | null;
    is_active: boolean;
    period_day: number | null;
    cut_off_day: number | null;
    day_divisor: number | null;
    overtime_period: string | null;
    attendance_period: string | null;
    employees_count: number;
    components: MasterComponent[];
}

interface ComponentOption {
    id: number;
    name: string;
    group: string | null;
}

interface EmployeeOption {
    id: number;
    name: string;
    salary_master_id: number | null;
}

interface Props {
    masters: Master[];
    componentOptions: ComponentOption[];
    employeeOptions: EmployeeOption[];
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

export default function PayrollMasterGaji({
    masters,
    componentOptions,
    employeeOptions,
}: Props) {
    const form = useForm({
        code: '',
        category: '',
        note: '',
        period_day: '',
        cut_off_day: '',
        day_divisor: '',
        overtime_period: '',
        attendance_period: '',
        is_active: true,
    });

    const submit = () => {
        form.post(SalaryMasterController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Master Gaji disimpan');
                form.reset();
            },
            onError: () => toast.error('Periksa isian master gaji'),
        });
    };

    return (
        <>
            <Head title="Master Gaji" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ marginBottom: 20 }}>
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
                        <span style={{ color: C.muted }}>Master Gaji</span>
                    </div>
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                        }}
                    >
                        Master Gaji
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Template gaji: checklist komponen + setting periode/cut
                        off/jumlah hari yang ditempel ke pegawai.
                    </div>
                </div>

                {/* Create form */}
                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div
                        style={{
                            fontSize: 14,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 14,
                        }}
                    >
                        Buat Master Gaji
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr 1.4fr',
                            gap: 12,
                            marginBottom: 12,
                        }}
                    >
                        <input
                            style={input}
                            placeholder="Kode (mis. MG-ORG)"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
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
                            onChange={(e) => form.setData('note', e.target.value)}
                        />
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(5, 1fr)',
                            gap: 12,
                            alignItems: 'end',
                        }}
                    >
                        <input
                            style={input}
                            type="number"
                            placeholder="Tgl periode"
                            value={form.data.period_day}
                            onChange={(e) =>
                                form.setData('period_day', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            type="number"
                            placeholder="Tgl cut off"
                            value={form.data.cut_off_day}
                            onChange={(e) =>
                                form.setData('cut_off_day', e.target.value)
                            }
                        />
                        <input
                            style={input}
                            type="number"
                            placeholder="Jumlah hari"
                            value={form.data.day_divisor}
                            onChange={(e) =>
                                form.setData('day_divisor', e.target.value)
                            }
                        />
                        <select
                            style={input}
                            value={form.data.overtime_period}
                            onChange={(e) =>
                                form.setData('overtime_period', e.target.value)
                            }
                        >
                            <option value="">Periode OT —</option>
                            <option value="berjalan">Berjalan</option>
                            <option value="bulan_lalu">Bulan Lalu</option>
                        </select>
                        <select
                            style={input}
                            value={form.data.attendance_period}
                            onChange={(e) =>
                                form.setData('attendance_period', e.target.value)
                            }
                        >
                            <option value="">Periode Absen —</option>
                            <option value="berjalan">Berjalan</option>
                            <option value="bulan_lalu">Bulan Lalu</option>
                        </select>
                    </div>
                    <div style={{ marginTop: 14, textAlign: 'right' }}>
                        <button
                            style={primaryBtn}
                            disabled={form.processing}
                            onClick={submit}
                        >
                            Simpan Master Gaji
                        </button>
                    </div>
                </div>

                {masters.length === 0 ? (
                    <div
                        style={{
                            ...card,
                            padding: 40,
                            textAlign: 'center',
                            color: C.faint,
                            fontSize: 14,
                        }}
                    >
                        Belum ada Master Gaji.
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                        }}
                    >
                        {masters.map((m) => (
                            <MasterCard
                                key={m.id}
                                master={m}
                                componentOptions={componentOptions}
                                employeeOptions={employeeOptions}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function MasterCard({
    master,
    componentOptions,
    employeeOptions,
}: {
    master: Master;
    componentOptions: ComponentOption[];
    employeeOptions: EmployeeOption[];
}) {
    const [assignOpen, setAssignOpen] = useState(false);
    const checkedIds = new Set(
        master.components.map((c) => c.payroll_component_id),
    );

    const flagsFor = (id: number) =>
        master.components.find((c) => c.payroll_component_id === id);

    const setComponent = (
        id: number,
        checked: boolean,
        isProrate: boolean,
        isOvertime: boolean,
    ) =>
        router.post(
            SalaryMasterController.setComponent(master.id).url,
            {
                payroll_component_id: id,
                checked,
                is_prorate: isProrate,
                is_overtime_base: isOvertime,
            },
            { preserveScroll: true },
        );

    const del = () =>
        router.delete(SalaryMasterController.destroy(master.id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Master Gaji dihapus'),
        });

    const settings = [
        master.period_day && `Periode tgl ${master.period_day}`,
        master.cut_off_day && `Cut off tgl ${master.cut_off_day}`,
        master.day_divisor && `${master.day_divisor} hari`,
        master.overtime_period && `OT ${master.overtime_period}`,
        master.attendance_period && `Absen ${master.attendance_period}`,
    ].filter(Boolean);

    return (
        <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '14px 18px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div>
                    <div
                        style={{
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        {master.code} · {master.category}
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.faint,
                            marginTop: 2,
                        }}
                    >
                        {master.employees_count} pegawai
                        {settings.length > 0 && ' · ' + settings.join(' · ')}
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    <button
                        onClick={() => setAssignOpen((v) => !v)}
                        style={{
                            padding: '7px 12px',
                            borderRadius: 8,
                            border: `1px solid ${C.line}`,
                            background: '#fff',
                            color: C.primary,
                            fontSize: 12.5,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        Tempel ke Pegawai
                    </button>
                    <button
                        onClick={del}
                        style={{
                            border: 'none',
                            background: 'transparent',
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="trash-2" size={16} color={C.red} />
                    </button>
                </div>
            </div>

            {assignOpen && (
                <AssignPanel
                    master={master}
                    employeeOptions={employeeOptions}
                />
            )}

            {/* Component checklist */}
            <div style={{ padding: '10px 18px 16px' }}>
                <div
                    style={{
                        fontSize: 12.5,
                        fontWeight: 600,
                        color: C.muted,
                        margin: '6px 0 10px',
                    }}
                >
                    Checklist Komponen
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(280px, 1fr))',
                        gap: 8,
                    }}
                >
                    {componentOptions.map((c) => {
                        const on = checkedIds.has(c.id);
                        const flags = flagsFor(c.id);
                        return (
                            <div
                                key={c.id}
                                style={{
                                    border: `1px solid ${on ? C.primary + '55' : C.line}`,
                                    background: on ? C.primary + '08' : '#fff',
                                    borderRadius: 8,
                                    padding: '9px 11px',
                                }}
                            >
                                <label
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        fontSize: 13,
                                        color: C.text,
                                        cursor: 'pointer',
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={on}
                                        onChange={(e) =>
                                            setComponent(
                                                c.id,
                                                e.target.checked,
                                                flags?.is_prorate ?? false,
                                                flags?.is_overtime_base ?? false,
                                            )
                                        }
                                    />
                                    <span style={{ fontWeight: 600 }}>
                                        {c.name}
                                    </span>
                                    <span
                                        style={{
                                            marginLeft: 'auto',
                                            fontSize: 11,
                                            color: C.faint,
                                        }}
                                    >
                                        {c.group}
                                    </span>
                                </label>
                                {on && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 14,
                                            marginTop: 8,
                                            paddingLeft: 24,
                                        }}
                                    >
                                        <label
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 5,
                                                fontSize: 12,
                                                color: C.muted,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={
                                                    flags?.is_prorate ?? false
                                                }
                                                onChange={(e) =>
                                                    setComponent(
                                                        c.id,
                                                        true,
                                                        e.target.checked,
                                                        flags?.is_overtime_base ??
                                                            false,
                                                    )
                                                }
                                            />
                                            prorate
                                        </label>
                                        <label
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 5,
                                                fontSize: 12,
                                                color: C.muted,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={
                                                    flags?.is_overtime_base ??
                                                    false
                                                }
                                                onChange={(e) =>
                                                    setComponent(
                                                        c.id,
                                                        true,
                                                        flags?.is_prorate ??
                                                            false,
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            overtime
                                        </label>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function AssignPanel({
    master,
    employeeOptions,
}: {
    master: Master;
    employeeOptions: EmployeeOption[];
}) {
    const form = useForm<{ employee_ids: number[] }>({
        employee_ids: employeeOptions
            .filter((e) => e.salary_master_id === master.id)
            .map((e) => e.id),
    });

    const toggle = (id: number) =>
        form.setData(
            'employee_ids',
            form.data.employee_ids.includes(id)
                ? form.data.employee_ids.filter((x) => x !== id)
                : [...form.data.employee_ids, id],
        );

    const apply = () =>
        form.post(SalaryMasterController.assign(master.id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Master Gaji ditempel ke pegawai'),
        });

    return (
        <div
            style={{
                padding: 16,
                background: C.surface,
                borderBottom: `1px solid ${C.line}`,
            }}
        >
            <div
                style={{
                    fontSize: 12.5,
                    fontWeight: 600,
                    color: C.muted,
                    marginBottom: 10,
                }}
            >
                Pilih pegawai untuk template ini
            </div>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns:
                        'repeat(auto-fill, minmax(220px, 1fr))',
                    gap: 6,
                    maxHeight: 220,
                    overflowY: 'auto',
                    marginBottom: 12,
                }}
            >
                {employeeOptions.map((e) => (
                    <label
                        key={e.id}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            color: C.text,
                            cursor: 'pointer',
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={form.data.employee_ids.includes(e.id)}
                            onChange={() => toggle(e.id)}
                        />
                        {e.name}
                        {e.salary_master_id !== null &&
                            e.salary_master_id !== master.id && (
                                <span
                                    style={{ fontSize: 10.5, color: C.faint }}
                                >
                                    (lain)
                                </span>
                            )}
                    </label>
                ))}
            </div>
            <button style={primaryBtn} disabled={form.processing} onClick={apply}>
                Terapkan
            </button>
        </div>
    );
}
