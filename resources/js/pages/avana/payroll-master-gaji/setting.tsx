import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import SalaryMasterController from '@/actions/App/Http/Controllers/Avana/SalaryMasterController';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, C, card } from '@/lib/avana';

type FlagKey = 'included' | 'is_prorate' | 'is_kompensasi';

interface Component {
    id: number;
    name: string;
    group: string;
    included: boolean;
    amount: number;
    is_prorate: boolean;
    is_kompensasi: boolean;
}

const rupiah = (n: number) =>
    n ? new Intl.NumberFormat('id-ID').format(n) : '';

/** One membership row: the included checkbox plus its monthly nominal input. */
function MembershipRow({
    c,
    masterId,
    onToggle,
}: {
    c: Component;
    masterId: number;
    onToggle: (checked: boolean) => void;
}) {
    const [amount, setAmount] = useState<string>(rupiah(c.amount));

    const saveAmount = () => {
        const value = Number(amount.replace(/[^\d]/g, '')) || 0;

        if (value === c.amount) {
            return;
        }

        router.post(
            SalaryMasterController.setComponentAmount(masterId).url,
            { payroll_component_id: c.id, amount: value },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 9,
                fontSize: 13,
                color: C.text,
                padding: '8px 6px',
                borderBottom: `1px solid ${C.line}`,
            }}
        >
            <input
                type="checkbox"
                checked={c.included}
                onChange={(e) => onToggle(e.target.checked)}
                style={{ cursor: 'pointer' }}
            />
            <span style={{ flex: 1 }}>{c.name}</span>
            {c.included && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 4,
                    }}
                >
                    <span style={{ fontSize: 11.5, color: C.faint }}>Rp</span>
                    <input
                        style={{ ...input, width: 120, textAlign: 'right' }}
                        inputMode="numeric"
                        value={amount}
                        placeholder="0"
                        onChange={(e) =>
                            setAmount(
                                rupiah(
                                    Number(
                                        e.target.value.replace(/[^\d]/g, ''),
                                    ) || 0,
                                ),
                            )
                        }
                        onBlur={saveAmount}
                    />
                </div>
            )}
        </div>
    );
}

interface Master {
    id: number;
    code: string;
    category: string;
    note: string | null;
    is_active: boolean;
    process_type: string | null;
    period_start_day: number | null;
    period_end_day: number | null;
    cut_off_day: number | null;
    day_divisor: number | null;
    day_calc_method: string | null;
    day_calc_method_id: number | null;
    overtime_calc_method: string | null;
    overtime_start_day: number | null;
    overtime_end_day: number | null;
    overtime_period: string | null;
    attendance_start_day: number | null;
    attendance_end_day: number | null;
    attendance_period: string | null;
    compensation_method: string | null;
    probation_months: number | null;
    employees_count: number;
}

interface EmployeeOption {
    id: number;
    name: string;
    salary_master_id: number | null;
}

interface DayCalcMethodOption {
    id: number;
    name: string;
    basis: string;
    divisor: number | null;
}

interface GradeOption {
    id: number;
    label: string;
    min: number;
    mid: number;
    max: number;
}

interface EmployeeSalary {
    id: number;
    name: string;
    number: string | null;
    branch: string | null;
    salary_grade_id: number | null;
    /** When the employee's own Gaji Pokok row took effect; null = template. */
    effective_from: string | null;
    basic: number;
    allowances: number;
    total: number;
    umr_status: string;
    umr_label: string;
    umr_amount: number | null;
    umr_region: string | null;
    grade_status: string;
    grade_label: string;
    grade_min: number | null;
    grade_max: number | null;
}

interface Props {
    master: Master;
    components: Component[];
    dayCalcMethods: DayCalcMethodOption[];
    employeeOptions: EmployeeOption[];
    gradeOptions: GradeOption[];
    salaries: EmployeeSalary[];
}

const input: React.CSSProperties = {
    padding: '8px 10px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    background: '#fff',
};
const label: React.CSSProperties = {
    fontSize: 13,
    color: C.text,
    background: C.surface,
    padding: '11px 14px',
    borderRadius: 8,
    fontWeight: 500,
};
const sectionTitle: React.CSSProperties = {
    fontSize: 12.5,
    fontWeight: 700,
    letterSpacing: 0.4,
    color: C.muted,
    background: C.surface,
    padding: '10px 14px',
    borderRadius: 8,
    marginBottom: 14,
};

function Radio({
    name,
    value,
    current,
    onChange,
    text,
}: {
    name: string;
    value: string;
    current: string;
    onChange: (v: string) => void;
    text: string;
}) {
    return (
        <label
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 6,
                fontSize: 13,
                color: C.text,
                cursor: 'pointer',
            }}
        >
            <input
                type="radio"
                name={name}
                checked={current === value}
                onChange={() => onChange(value)}
            />
            {text}
        </label>
    );
}

/** A two-column Penerimaan | Potongan checklist bound to one flag key. */
function ChecklistSection({
    title,
    components,
    flag,
    masterId,
    withAmount = false,
}: {
    title: string;
    components: Component[];
    flag: FlagKey;
    masterId: number;
    withAmount?: boolean;
}) {
    const toggle = (c: Component, checked: boolean) =>
        router.post(
            SalaryMasterController.setComponent(masterId).url,
            { payroll_component_id: c.id, flag, checked },
            { preserveScroll: true, preserveState: false },
        );

    const col = (group: 'penerimaan' | 'potongan') => (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <div
                style={{
                    fontSize: 11.5,
                    fontWeight: 700,
                    color: C.muted,
                    padding: '8px 6px',
                    borderBottom: `1px solid ${C.line}`,
                    textTransform: 'uppercase',
                }}
            >
                {group}
            </div>
            {components
                .filter((c) => (c.group ?? 'penerimaan') === group)
                .map((c) =>
                    withAmount ? (
                        <MembershipRow
                            key={c.id}
                            c={c}
                            masterId={masterId}
                            onToggle={(checked) => toggle(c, checked)}
                        />
                    ) : (
                        <label
                            key={c.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                                fontSize: 13,
                                color: C.text,
                                padding: '8px 6px',
                                borderBottom: `1px solid ${C.line}`,
                                cursor: 'pointer',
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={c[flag]}
                                onChange={(e) => toggle(c, e.target.checked)}
                            />
                            {c.name}
                        </label>
                    ),
                )}
        </div>
    );

    return (
        <div style={{ ...card, padding: 18, marginBottom: 16 }}>
            <div style={sectionTitle}>{title}</div>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 24,
                }}
            >
                {col('penerimaan')}
                {col('potongan')}
            </div>
        </div>
    );
}

export default function MasterGajiSetting({
    master,
    components,
    dayCalcMethods,
    employeeOptions,
    gradeOptions,
    salaries,
}: Props) {
    const [assignOpen, setAssignOpen] = useState(false);

    const form = useForm({
        code: master.code,
        category: master.category,
        note: master.note ?? '',
        is_active: master.is_active,
        process_type: master.process_type ?? 'normal',
        period_start_day: master.period_start_day ?? '',
        period_end_day: master.period_end_day ?? '',
        cut_off_day: master.cut_off_day ?? '',
        day_divisor: master.day_divisor ?? '',
        day_calc_method: master.day_calc_method ?? 'hari_kerja',
        day_calc_method_id: master.day_calc_method_id ?? '',
        overtime_calc_method: master.overtime_calc_method ?? 'reguler',
        overtime_start_day: master.overtime_start_day ?? '',
        overtime_end_day: master.overtime_end_day ?? '',
        overtime_period: master.overtime_period ?? 'berjalan',
        attendance_start_day: master.attendance_start_day ?? '',
        attendance_end_day: master.attendance_end_day ?? '',
        attendance_period: master.attendance_period ?? 'berjalan',
        compensation_method: master.compensation_method ?? '',
        probation_months: master.probation_months ?? '',
    });

    const save = () => {
        form.put(SalaryMasterController.update(master.id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Master Gaji disimpan'),
            onError: () => toast.error('Periksa isian setting'),
        });
    };

    const rangeRow = (
        title: string,
        startKey:
            'period_start_day' | 'overtime_start_day' | 'attendance_start_day',
        endKey: 'period_end_day' | 'overtime_end_day' | 'attendance_end_day',
        periodKey?: 'overtime_period' | 'attendance_period',
    ) => (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '200px 1fr',
                gap: 16,
                alignItems: 'center',
            }}
        >
            <div style={label}>{title}</div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <input
                    style={{ ...input, width: 72 }}
                    type="number"
                    placeholder="tgl 1"
                    value={form.data[startKey] as number | string}
                    onChange={(e) => form.setData(startKey, e.target.value)}
                />
                <span style={{ color: C.muted, fontSize: 13 }}>s.d</span>
                <input
                    style={{ ...input, width: 72 }}
                    type="number"
                    placeholder="tgl 31"
                    value={form.data[endKey] as number | string}
                    onChange={(e) => form.setData(endKey, e.target.value)}
                />
                {periodKey && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 16,
                            marginLeft: 12,
                        }}
                    >
                        <Radio
                            name={periodKey}
                            value="berjalan"
                            current={form.data[periodKey]}
                            onChange={(v) => form.setData(periodKey, v)}
                            text="Berjalan"
                        />
                        <Radio
                            name={periodKey}
                            value="bulan_lalu"
                            current={form.data[periodKey]}
                            onChange={(v) => form.setData(periodKey, v)}
                            text="Bulan Lalu"
                        />
                    </div>
                )}
            </div>
        </div>
    );

    return (
        <>
            <Head title={`Master Gaji · ${master.code}`} />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: 20,
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 6,
                            }}
                        >
                            <span>Payroll</span>
                            <AIcon name="chevron-right" size={13} />
                            <span>Master Gaji</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                {master.code}
                            </span>
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
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button
                            onClick={() =>
                                router.visit(SalaryMasterController.index().url)
                            }
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                padding: '9px 16px',
                                borderRadius: 8,
                                border: `1px solid ${C.line}`,
                                background: '#fff',
                                color: C.muted,
                                fontSize: 13,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            <AIcon
                                name="arrow-left"
                                size={15}
                                color={C.muted}
                            />
                            Kembali
                        </button>
                        <button
                            onClick={save}
                            disabled={form.processing}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                padding: '9px 20px',
                                borderRadius: 8,
                                border: 'none',
                                background: C.green,
                                color: '#fff',
                                fontSize: 13,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            <AIcon name="save" size={15} color="#fff" />
                            Simpan
                        </button>
                    </div>
                </div>

                {/* KATEGORI GAJI */}
                <div style={{ ...card, padding: 18, marginBottom: 16 }}>
                    <div style={sectionTitle}>KATEGORI GAJI</div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '200px 1fr',
                            gap: 16,
                            rowGap: 12,
                            alignItems: 'center',
                        }}
                    >
                        <div style={label}>Kode</div>
                        <input
                            style={input}
                            placeholder="cth. GP01"
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                        />
                        <div style={label}>Kategori Gaji</div>
                        <input
                            style={input}
                            placeholder="cth. Gaji Pokok Bulanan"
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                        />
                        <div style={label}>Keterangan</div>
                        <input
                            style={input}
                            placeholder="cth. Dipakai untuk karyawan tetap"
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                    </div>
                </div>

                {/* PENERIMAAN | POTONGAN membership */}
                <ChecklistSection
                    title="PENERIMAAN | POTONGAN — centang & isi nominal"
                    components={components}
                    flag="included"
                    masterId={master.id}
                    withAmount
                />

                {/* SETTING */}
                <div style={{ ...card, padding: 18, marginBottom: 16 }}>
                    <div style={sectionTitle}>SETTING</div>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr',
                                gap: 16,
                                alignItems: 'center',
                            }}
                        >
                            <div style={label}>Tipe Proses</div>
                            <div style={{ display: 'flex', gap: 20 }}>
                                <Radio
                                    name="process_type"
                                    value="normal"
                                    current={form.data.process_type}
                                    onChange={(v) =>
                                        form.setData('process_type', v)
                                    }
                                    text="Normal"
                                />
                                <Radio
                                    name="process_type"
                                    value="bulan_lalu"
                                    current={form.data.process_type}
                                    onChange={(v) =>
                                        form.setData('process_type', v)
                                    }
                                    text="Bulan Lalu"
                                />
                            </div>
                        </div>

                        {rangeRow(
                            'Periode Gaji',
                            'period_start_day',
                            'period_end_day',
                        )}

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr',
                                gap: 16,
                                alignItems: 'center',
                            }}
                        >
                            <div style={label}>Tanggal Cut Off</div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                }}
                            >
                                <input
                                    style={{ ...input, width: 100 }}
                                    type="number"
                                    placeholder="cth. 25"
                                    value={form.data.cut_off_day}
                                    onChange={(e) =>
                                        form.setData(
                                            'cut_off_day',
                                            e.target.value,
                                        )
                                    }
                                />
                                <span
                                    style={{ fontSize: 12.5, color: C.faint }}
                                >
                                    Batas perhitungan prorate/rapel gaji
                                </span>
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr',
                                gap: 16,
                                alignItems: 'center',
                            }}
                        >
                            <div style={label}>Jumlah Hari</div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                }}
                            >
                                <input
                                    style={{ ...input, width: 100 }}
                                    type="number"
                                    placeholder="cth. 21"
                                    value={form.data.day_divisor}
                                    onChange={(e) =>
                                        form.setData(
                                            'day_divisor',
                                            e.target.value,
                                        )
                                    }
                                />
                                <span
                                    style={{ fontSize: 12.5, color: C.faint }}
                                >
                                    Faktor pembagi hari kerja
                                </span>
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr',
                                gap: 16,
                                alignItems: 'center',
                            }}
                        >
                            <div style={label}>Metode Perhitungan Hari</div>
                            <div>
                                <select
                                    style={{ ...input, minWidth: 280 }}
                                    value={form.data.day_calc_method_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'day_calc_method_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        — Manual (pakai basis di bawah) —
                                    </option>
                                    {dayCalcMethods.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                            {m.divisor
                                                ? ` (÷${m.divisor})`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: C.faint,
                                        marginLeft: 12,
                                    }}
                                >
                                    Pilih metode dari Setting Komponen, atau
                                    biarkan manual.
                                </span>
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr',
                                gap: 16,
                                alignItems: 'center',
                            }}
                        >
                            <div style={label}>Perhitungan Hari (Manual)</div>
                            <div style={{ display: 'flex', gap: 20 }}>
                                {[
                                    ['absen', 'Absen'],
                                    ['hari_kerja', 'Hari Kerja'],
                                    ['hari_kalender', 'Hari Kalender'],
                                    ['formula', 'Formula'],
                                ].map(([v, t]) => (
                                    <Radio
                                        key={v}
                                        name="day_calc_method"
                                        value={v}
                                        current={form.data.day_calc_method}
                                        onChange={(val) =>
                                            form.setData('day_calc_method', val)
                                        }
                                        text={t}
                                    />
                                ))}
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr',
                                gap: 16,
                                alignItems: 'center',
                            }}
                        >
                            <div style={label}>Perhitungan Overtime</div>
                            <div style={{ display: 'flex', gap: 20 }}>
                                <Radio
                                    name="overtime_calc_method"
                                    value="reguler"
                                    current={form.data.overtime_calc_method}
                                    onChange={(v) =>
                                        form.setData('overtime_calc_method', v)
                                    }
                                    text="Reguler"
                                />
                                <Radio
                                    name="overtime_calc_method"
                                    value="flat"
                                    current={form.data.overtime_calc_method}
                                    onChange={(v) =>
                                        form.setData('overtime_calc_method', v)
                                    }
                                    text="Flat"
                                />
                            </div>
                        </div>

                        {rangeRow(
                            'Periode Overtime',
                            'overtime_start_day',
                            'overtime_end_day',
                            'overtime_period',
                        )}
                        {rangeRow(
                            'Periode Absensi',
                            'attendance_start_day',
                            'attendance_end_day',
                            'attendance_period',
                        )}

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '200px 1fr',
                                gap: 16,
                                alignItems: 'center',
                            }}
                        >
                            <div style={label}>Probation</div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                }}
                            >
                                <input
                                    style={{ ...input, width: 100 }}
                                    type="number"
                                    placeholder="cth. 3"
                                    value={form.data.probation_months}
                                    onChange={(e) =>
                                        form.setData(
                                            'probation_months',
                                            e.target.value,
                                        )
                                    }
                                />
                                <span style={{ fontSize: 13, color: C.muted }}>
                                    Bulan
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* KOMPONEN PRORATE / OVERTIME / KOMPENSASI */}
                <ChecklistSection
                    title="KOMPONEN PRORATE"
                    components={components}
                    flag="is_prorate"
                    masterId={master.id}
                />
                {/* Komponen basis lembur ditandai "Tetap" di Setup Lembur —
                    satu daftar untuk seluruh tenant, sesuai desain setup. */}
                <ChecklistSection
                    title="KOMPONEN KOMPENSASI"
                    components={components}
                    flag="is_kompensasi"
                    masterId={master.id}
                />

                {/* Tempel ke Pegawai */}
                <div style={{ ...card, padding: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                        }}
                    >
                        <div style={sectionTitle}>
                            TEMPEL KE PEGAWAI ({master.employees_count})
                        </div>
                        <button
                            onClick={() => setAssignOpen((v) => !v)}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
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
                            <AIcon
                                name={assignOpen ? 'x' : 'user-plus'}
                                size={14}
                                color={C.primary}
                            />
                            {assignOpen ? 'Tutup' : 'Pilih Pegawai'}
                        </button>
                    </div>
                    {assignOpen && (
                        <AssignPanel
                            master={master}
                            employeeOptions={employeeOptions}
                        />
                    )}
                </div>

                <SalaryValidationPanel
                    master={master}
                    salaries={salaries}
                    gradeOptions={gradeOptions}
                />
            </div>
        </>
    );
}

/**
 * Gaji per pegawai beserta putusan UMR & rentang grade — dihitung, bukan
 * diketik, sesuai bagian 3 dokumentasi setup payroll.
 */
function SalaryValidationPanel({
    master,
    salaries,
    gradeOptions,
}: {
    master: Master;
    salaries: EmployeeSalary[];
    gradeOptions: GradeOption[];
}) {
    const [draft, setDraft] = useState<Record<number, string>>({});
    const [from, setFrom] = useState<Record<number, string>>({});

    const saveSalary = (employeeId: number) => {
        const raw = draft[employeeId];

        if (raw === undefined || raw === '') {
            return;
        }

        router.post(
            SalaryMasterController.setEmployeeSalary(master.id).url,
            {
                employee_id: employeeId,
                amount: Number(raw),
                // Blank means "from today"; a date opens a new version of the
                // salary and closes the one it replaces.
                effective_start_date: from[employeeId] || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setDraft((d) => ({ ...d, [employeeId]: '' })),
                onError: () => toast.error('Gaji pokok gagal disimpan'),
            },
        );
    };

    const saveGrade = (employeeId: number, gradeId: string) =>
        router.post(
            SalaryMasterController.setEmployeeGrade(master.id).url,
            { employee_id: employeeId, salary_grade_id: gradeId === '' ? null : Number(gradeId) },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Grade karyawan disimpan'),
                onError: () => toast.error('Grade gagal disimpan'),
            },
        );

    const isBreach = (status: string) =>
        status === 'below' || status === 'below_min' || status === 'above_max';

    const badge = (status: string): React.CSSProperties => ({
        display: 'inline-block',
        padding: '3px 9px',
        borderRadius: 999,
        fontSize: 11.5,
        fontWeight: 600,
        color: isBreach(status) ? C.red : status === 'unknown' ? C.faint : C.green,
        background: isBreach(status) ? '#FEF2F2' : status === 'unknown' ? C.surface : '#F0FDF4',
    });

    return (
        <div style={{ ...card, padding: 18 }}>
            <div style={sectionTitle}>VALIDASI UMR &amp; SKALA UPAH</div>
            <div style={{ fontSize: 12.5, color: C.faint, margin: '-6px 0 14px' }}>
                Gaji pokok + tunjangan tetap dicek otomatis terhadap UMR cabang dan rentang grade.
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr>
                            {[
                                'Karyawan',
                                'Grade',
                                'Gaji Pokok',
                                'Tunjangan Tetap',
                                'Total',
                                'Validasi UMR',
                                'Skala Upah',
                            ].map((h, i) => (
                                <th
                                    key={i}
                                    style={{
                                        textAlign: 'left',
                                        fontSize: 12,
                                        fontWeight: 600,
                                        color: C.muted,
                                        padding: '10px 12px',
                                        borderBottom: `1px solid ${C.line}`,
                                    }}
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {salaries.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={7}
                                    style={{
                                        fontSize: 13,
                                        color: C.faint,
                                        padding: '14px 12px',
                                        textAlign: 'center',
                                    }}
                                >
                                    Belum ada pegawai yang ditempel ke Master Gaji ini.
                                </td>
                            </tr>
                        ) : (
                            salaries.map((s) => {
                                const cell: React.CSSProperties = {
                                    fontSize: 13,
                                    color: C.text,
                                    padding: '10px 12px',
                                    borderBottom: `1px solid ${C.line}`,
                                };

                                return (
                                    <tr key={s.id}>
                                        <td style={{ ...cell, fontWeight: 600, color: C.navy }}>
                                            {s.name}
                                            <div style={{ fontSize: 11.5, fontWeight: 400, color: C.muted }}>
                                                {s.branch ?? '—'}
                                            </div>
                                        </td>
                                        <td style={cell}>
                                            <select
                                                style={{ ...input, width: 150 }}
                                                value={s.salary_grade_id ?? ''}
                                                onChange={(e) => saveGrade(s.id, e.target.value)}
                                            >
                                                <option value="">— Belum diatur —</option>
                                                {gradeOptions.map((g) => (
                                                    <option key={g.id} value={g.id}>
                                                        {g.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td style={cell}>
                                            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                                <input
                                                    style={{ ...input, width: 130 }}
                                                    type="number"
                                                    placeholder={String(Math.round(s.basic))}
                                                    value={draft[s.id] ?? ''}
                                                    onChange={(e) =>
                                                        setDraft((d) => ({ ...d, [s.id]: e.target.value }))
                                                    }
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => saveSalary(s.id)}
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        padding: '7px 9px',
                                                        borderRadius: 8,
                                                        border: `1px solid ${C.line}`,
                                                        background: '#fff',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    <AIcon name="save" size={14} color={C.primary} />
                                                </button>
                                            </div>
                                            <div style={{ marginTop: 5 }}>
                                                <DatePicker
                                                    value={from[s.id] ?? ''}
                                                    onChange={(v) =>
                                                        setFrom((d) => ({ ...d, [s.id]: v }))
                                                    }
                                                    placeholder="Berlaku mulai"
                                                    width={168}
                                                />
                                            </div>
                                            <div style={{ fontSize: 11.5, color: C.muted, marginTop: 3 }}>
                                                Rp {Math.round(s.basic).toLocaleString('id-ID')}
                                                {s.effective_from !== null && (
                                                    <> · berlaku {s.effective_from}</>
                                                )}
                                            </div>
                                        </td>
                                        <td style={cell}>
                                            Rp {Math.round(s.allowances).toLocaleString('id-ID')}
                                        </td>
                                        <td style={{ ...cell, fontWeight: 600 }}>
                                            Rp {Math.round(s.total).toLocaleString('id-ID')}
                                        </td>
                                        <td style={cell}>
                                            <span style={badge(s.umr_status)}>
                                                {s.umr_label}
                                            </span>
                                            {s.umr_amount !== null && (
                                                <div style={{ fontSize: 11.5, color: C.muted, marginTop: 3 }}>
                                                    UMR Rp {Math.round(s.umr_amount).toLocaleString('id-ID')}
                                                </div>
                                            )}
                                        </td>
                                        <td style={cell}>
                                            <span style={badge(s.grade_status)}>
                                                {s.grade_label}
                                            </span>
                                            {s.grade_min !== null && s.grade_max !== null && (
                                                <div style={{ fontSize: 11.5, color: C.muted, marginTop: 3 }}>
                                                    Rp {Math.round(s.grade_min).toLocaleString('id-ID')} – Rp{' '}
                                                    {Math.round(s.grade_max).toLocaleString('id-ID')}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
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
        <div style={{ marginTop: 14 }}>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns:
                        'repeat(auto-fill, minmax(220px, 1fr))',
                    gap: 6,
                    maxHeight: 240,
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
            <button
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 7,
                    padding: '9px 16px',
                    borderRadius: 8,
                    border: 'none',
                    background: C.violet,
                    color: '#fff',
                    fontSize: 13,
                    fontWeight: 600,
                    cursor: 'pointer',
                }}
                disabled={form.processing}
                onClick={apply}
            >
                <AIcon name="check" size={15} color="#fff" />
                Terapkan
            </button>
        </div>
    );
}
