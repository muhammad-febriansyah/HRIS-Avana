import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import EmployeeSalaryController from '@/actions/App/Http/Controllers/Avana/EmployeeSalaryController';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, card, RupiahInput } from '@/lib/avana';

interface Row {
    id: number;
    name: string;
    group: string;
    calc_basis: string | null;
    is_fixed: boolean;
    master_amount: number;
    employee_amount: number | null;
    effective_from: string | null;
}

interface Employee {
    id: number;
    name: string;
    nik: string | null;
    salary_master_id: number | null;
    position: string | null;
    branch: string | null;
}

interface Compliance {
    basic: number;
    allowances: number;
    total: number;
    umr_status: string;
    umr_label: string;
    grade_status: string;
    grade_label: string;
}

interface Props {
    employee: Employee | null;
    rows: Row[];
    compliance: Compliance | null;
    employeeOptions: { id: number; name: string; nik: string | null }[];
    masterOptions: { id: number; label: string }[];
    salaryFloor: string | null;
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
    padding: '11px 14px',
    borderBottom: `1px solid ${C.line}`,
    whiteSpace: 'nowrap',
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '10px 14px',
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
const ghostBtn: React.CSSProperties = {
    ...primaryBtn,
    background: '#fff',
    color: C.primary,
    border: `1px solid ${C.line}`,
};

const rupiah = (n: number) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

/** The figure in force for a row: the employee's own, else the template's. */
const inForce = (row: Row) =>
    row.employee_amount === null ? row.master_amount : row.employee_amount;

export default function GajiKaryawan({
    employee,
    rows,
    compliance,
    employeeOptions,
    masterOptions,
    salaryFloor,
}: Props) {
    const [amounts, setAmounts] = useState<Record<number, string>>({});
    const [seededFor, setSeededFor] = useState<number | null>(null);

    // A different employee brings a different set of components, so the typed
    // figures start again rather than leaking onto the next person. Adjusting
    // the state during render (rather than in an effect) keeps the inputs from
    // flashing the previous employee's figures for a frame.
    if (seededFor !== (employee?.id ?? null)) {
        setSeededFor(employee?.id ?? null);
        setAmounts(
            Object.fromEntries(
                rows.map((r) => [r.id, String(Math.round(inForce(r)))]),
            ),
        );
    }

    const form = useForm({
        employee_id: employee?.id ?? 0,
        salary_master_id: employee?.salary_master_id ?? '',
        effective_start_date: '',
        reason: '',
        components: [] as { payroll_component_id: number; amount: number }[],
    });

    const pickEmployee = (value: string) =>
        router.get(
            EmployeeSalaryController.index().url,
            value === '' ? {} : { employee_id: Number(value) },
            { preserveScroll: true, replace: true },
        );

    /** Put every template nominal back into the form, dropping the overrides. */
    const copyFromMaster = () =>
        setAmounts(
            Object.fromEntries(
                rows.map((r) => [r.id, String(Math.round(r.master_amount))]),
            ),
        );

    const fixedRows = rows.filter((r) => r.is_fixed);

    const total = fixedRows.reduce((sum, r) => {
        const typed = Number(amounts[r.id] ?? '');
        const value = Number.isFinite(typed) ? typed : inForce(r);

        return r.group === 'potongan' ? sum - value : sum + value;
    }, 0);

    const submit = () => {
        if (employee === null) {
            return;
        }

        router.post(
            EmployeeSalaryController.store().url,
            {
                employee_id: employee.id,
                salary_master_id:
                    form.data.salary_master_id === ''
                        ? null
                        : Number(form.data.salary_master_id),
                effective_start_date: form.data.effective_start_date || null,
                reason: form.data.reason || null,
                components: fixedRows.map((r) => ({
                    payroll_component_id: r.id,
                    amount: Number(amounts[r.id] ?? inForce(r)) || 0,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => form.setData('reason', ''),
                onError: (errors) =>
                    toast.error(
                        errors.effective_start_date ??
                            errors.components ??
                            'Gaji gagal disimpan',
                    ),
            },
        );
    };

    return (
        <>
            <Head title="Gaji Karyawan" />
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
                    <span style={{ color: C.muted }}>Gaji Karyawan</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Gaji Karyawan
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Pilih karyawan, pilih Master Gaji yang sudah ada, lalu ubah
                    nominal yang berbeda untuk orang ini. Master Gaji tetap jadi
                    template bersama — tidak perlu membuat master baru hanya
                    karena nominalnya beda.
                </div>

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.4fr 1.4fr',
                            gap: 12,
                        }}
                    >
                        <div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginBottom: 6,
                                }}
                            >
                                Karyawan
                            </div>
                            <SearchableSelect
                                value={
                                    employee === null ? '' : String(employee.id)
                                }
                                onChange={pickEmployee}
                                placeholder="Pilih karyawan (NIK / nama)…"
                                searchPlaceholder="Cari NIK / nama…"
                                allowClear
                                options={employeeOptions.map((e) => ({
                                    value: String(e.id),
                                    label: (e.nik ? e.nik + ' · ' : '') + e.name,
                                }))}
                            />
                        </div>
                        <div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginBottom: 6,
                                }}
                            >
                                Master Gaji
                            </div>
                            <SearchableSelect
                                value={String(form.data.salary_master_id ?? '')}
                                onChange={(v) =>
                                    form.setData('salary_master_id', v)
                                }
                                placeholder="Pilih Master Gaji…"
                                searchPlaceholder="Cari master…"
                                allowClear
                                options={masterOptions.map((m) => ({
                                    value: String(m.id),
                                    label: m.label,
                                }))}
                            />
                        </div>
                    </div>
                    {employee !== null && (
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginTop: 10,
                            }}
                        >
                            {[employee.position, employee.branch]
                                .filter(Boolean)
                                .join(' · ') || 'Tanpa jabatan/cabang'}
                        </div>
                    )}
                </div>

                {employee === null ? (
                    <div
                        style={{
                            ...card,
                            padding: 40,
                            textAlign: 'center',
                            color: C.faint,
                            fontSize: 13.5,
                        }}
                    >
                        Pilih karyawan untuk mengatur gajinya.
                    </div>
                ) : (
                    <div style={{ ...card, padding: 18 }}>
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
                                Komponen Gaji
                            </div>
                            <button
                                type="button"
                                style={ghostBtn}
                                onClick={copyFromMaster}
                            >
                                <AIcon name="copy" size={14} color={C.primary} />
                                Salin dari Master
                            </button>
                        </div>

                        {rows.length === 0 ? (
                            <div
                                style={{
                                    padding: 28,
                                    textAlign: 'center',
                                    color: C.faint,
                                    fontSize: 13.5,
                                }}
                            >
                                Master Gaji ini belum punya komponen yang
                                dicentang. Centang komponennya dulu di Master
                                Gaji.
                            </div>
                        ) : (
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
                                                'Komponen',
                                                'Jenis',
                                                'Nominal Master',
                                                'Nominal Karyawan',
                                                'Berlaku sejak',
                                            ].map((h) => (
                                                <th key={h} style={th}>
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((r) => (
                                            <tr key={r.id}>
                                                <td style={td}>{r.name}</td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                        fontSize: 12.5,
                                                    }}
                                                >
                                                    {r.group === 'potongan'
                                                        ? 'Potongan'
                                                        : 'Penerimaan'}
                                                    {!r.is_fixed && (
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            variabel · dari
                                                            absensi
                                                        </div>
                                                    )}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {rupiah(r.master_amount)}
                                                </td>
                                                <td style={td}>
                                                    {r.is_fixed ? (
                                                        <RupiahInput
                                                            style={{
                                                                ...input,
                                                                width: 160,
                                                            }}
                                                            value={
                                                                amounts[r.id] ??
                                                                ''
                                                            }
                                                            onChange={(raw) =>
                                                                setAmounts(
                                                                    (a) => ({
                                                                        ...a,
                                                                        [r.id]: raw,
                                                                    }),
                                                                )
                                                            }
                                                        />
                                                    ) : (
                                                        <span
                                                            style={{
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            dihitung payroll
                                                        </span>
                                                    )}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.faint,
                                                        fontSize: 12.5,
                                                    }}
                                                >
                                                    {r.effective_from ??
                                                        'ikut master'}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                }}
                                                colSpan={3}
                                            >
                                                Total Gaji Tetap
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                }}
                                                colSpan={2}
                                            >
                                                {rupiah(total)}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr auto',
                                gap: 12,
                                alignItems: 'end',
                                marginTop: 16,
                            }}
                        >
                            <div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        marginBottom: 6,
                                    }}
                                >
                                    Berlaku mulai
                                </div>
                                <DatePicker
                                    value={form.data.effective_start_date}
                                    onChange={(v) =>
                                        form.setData('effective_start_date', v)
                                    }
                                    placeholder="Hari ini"
                                    width={200}
                                />
                            </div>
                            <div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        marginBottom: 6,
                                    }}
                                >
                                    Alasan perubahan
                                </div>
                                <input
                                    style={input}
                                    type="text"
                                    placeholder="Promosi, penyesuaian UMR, hasil review…"
                                    value={form.data.reason}
                                    onChange={(e) =>
                                        form.setData('reason', e.target.value)
                                    }
                                />
                            </div>
                            <button
                                type="button"
                                style={primaryBtn}
                                onClick={submit}
                                disabled={rows.length === 0}
                            >
                                <AIcon name="save" size={15} color="#fff" />
                                Simpan Gaji
                            </button>
                        </div>

                        {salaryFloor !== null && (
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.muted,
                                    marginTop: 10,
                                }}
                            >
                                Payroll sudah final sampai periode sebelumnya —
                                tanggal berlaku paling awal {salaryFloor}.
                                Selisih periode yang sudah final dibayar lewat
                                Rapel.
                            </div>
                        )}

                        {compliance !== null && (
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 18,
                                    flexWrap: 'wrap',
                                    marginTop: 14,
                                    paddingTop: 14,
                                    borderTop: `1px solid ${C.line}`,
                                    fontSize: 12.5,
                                    color: C.muted,
                                }}
                            >
                                <span>
                                    Gaji pokok {rupiah(compliance.basic)}
                                </span>
                                <span>
                                    Tunjangan tetap{' '}
                                    {rupiah(compliance.allowances)}
                                </span>
                                <span>UMR: {compliance.umr_label}</span>
                                <span>
                                    Skala upah: {compliance.grade_label}
                                </span>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
