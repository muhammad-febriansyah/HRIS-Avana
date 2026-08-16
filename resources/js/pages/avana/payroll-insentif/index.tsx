import { Head, Link, router, useForm } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import IncentiveController from '@/actions/App/Http/Controllers/Avana/IncentiveController';
import { SearchableSelect } from '@/components/searchable-select';
import { ActionBtn, AIcon, C, card, RupiahInput } from '@/lib/avana';
import {
    AMOUNT_TYPE_LABELS,
    BASIS_LABELS,
    BASIS_UNITS,
    rupiah,
    STATUS_LABELS,
} from './types';
import type {
    ComponentOption,
    EmployeeOption,
    IncentiveAssignmentRow,
    IncentiveBasis,
    IncentiveCalculationRow,
    IncentiveSchemeRow,
    PeriodOption,
} from './types';

type SchemeFormData = {
    code: string;
    name: string;
    basis: IncentiveBasis;
    payroll_component_id: string;
    effective_start_date: string;
    effective_end_date: string;
    rounding: string;
    rounding_unit: string;
    prorate_partial_period: boolean;
    status: string;
    notes: string;
};

interface Props {
    schemes: IncentiveSchemeRow[];
    periods: PeriodOption[];
    selected_period_id: number | null;
    calculations: IncentiveCalculationRow[];
    employees: EmployeeOption[];
    components: ComponentOption[];
    assignments: IncentiveAssignmentRow[];
    flash?: { success?: string; warning?: string };
}

const th: CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '11px 14px',
    borderBottom: `1px solid ${C.line}`,
    whiteSpace: 'nowrap',
};

const td: CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '11px 14px',
    borderBottom: `1px solid ${C.line}`,
    verticalAlign: 'top',
};

const label: CSSProperties = {
    display: 'block',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    marginBottom: 5,
};

const input: CSSProperties = {
    width: '100%',
    height: 38,
    padding: '0 12px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    background: '#fff',
    color: C.text,
};

const TABS = [
    { id: 'skema', label: 'Skema & Aturan' },
    { id: 'penetapan', label: 'Penetapan Karyawan' },
    { id: 'perhitungan', label: 'Perhitungan & Persetujuan' },
] as const;

/** Colour a calculation status the way the payroll screens do. */
function statusColor(status: string): string {
    if (status === 'approved' || status === 'locked') {
        return C.green;
    }

    if (status === 'rejected') {
        return C.red;
    }

    return C.amber;
}

export default function InsentifIndex({
    schemes,
    periods,
    selected_period_id,
    calculations,
    employees,
    components,
    assignments,
    flash,
}: Props) {
    const [tab, setTab] = useState<(typeof TABS)[number]['id']>('skema');
    const [selected, setSelected] = useState<number[]>([]);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const schemeForm = useForm<SchemeFormData>({
        code: '',
        name: '',
        basis: 'attendance',
        payroll_component_id: '',
        effective_start_date: new Date().toISOString().slice(0, 10),
        effective_end_date: '',
        rounding: 'none',
        rounding_unit: '1',
        prorate_partial_period: false,
        status: 'active',
        notes: '',
    });

    const submitScheme = () => {
        schemeForm.post(IncentiveController.storeScheme().url, {
            preserveScroll: true,
            onSuccess: () => schemeForm.reset(),
        });
    };

    const toggleRow = (id: number) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    const act = (url: string, extra: Record<string, unknown> = {}) => {
        if (selected.length === 0) {
            toast.error('Pilih dulu baris insentifnya');

            return;
        }

        router.post(
            url,
            { calculation_ids: selected, ...extra },
            { preserveScroll: true, onSuccess: () => setSelected([]) },
        );
    };

    return (
        <>
            <Head title="Insentif" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-start',
                        marginBottom: 18,
                    }}
                >
                    <div>
                        <div style={{ fontSize: 12.5, color: C.faint }}>
                            Payroll · Insentif
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: '4px 0 6px',
                            }}
                        >
                            Insentif
                        </h1>
                        <div style={{ fontSize: 13.5, color: C.muted }}>
                            Skema menentukan cara hitungnya, penetapan
                            menentukan siapa yang dapat, dan hanya insentif yang
                            disetujui yang masuk payroll.
                        </div>
                    </div>
                    <Link
                        href={IncentiveController.history().url}
                        style={{ textDecoration: 'none' }}
                    >
                        <ActionBtn
                            icon="history"
                            label="Riwayat Insentif"
                            variant="neutral"
                        />
                    </Link>
                </div>

                <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                    {TABS.map((row) => (
                        <button
                            key={row.id}
                            onClick={() => setTab(row.id)}
                            style={{
                                padding: '8px 14px',
                                borderRadius: 8,
                                border: `1px solid ${tab === row.id ? C.primary : C.line}`,
                                background: tab === row.id ? C.primary : '#fff',
                                color: tab === row.id ? '#fff' : C.text,
                                fontSize: 13,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            {row.label}
                        </button>
                    ))}
                </div>

                {tab === 'skema' && (
                    <SchemeTab
                        schemes={schemes}
                        components={components}
                        form={schemeForm}
                        onSubmit={submitScheme}
                    />
                )}

                {tab === 'penetapan' && (
                    <AssignmentTab
                        schemes={schemes}
                        employees={employees}
                        assignments={assignments}
                    />
                )}

                {tab === 'perhitungan' && (
                    <CalculationTab
                        schemes={schemes}
                        periods={periods}
                        selectedPeriodId={selected_period_id}
                        calculations={calculations}
                        selected={selected}
                        onToggle={toggleRow}
                        onAct={act}
                    />
                )}
            </div>
        </>
    );
}

/** Scheme list plus the create form and each scheme's rule bands. */
function SchemeTab({
    schemes,
    components,
    form,
    onSubmit,
}: {
    schemes: IncentiveSchemeRow[];
    components: ComponentOption[];
    form: ReturnType<typeof useForm<SchemeFormData>>;
    onSubmit: () => void;
}) {
    const [openRules, setOpenRules] = useState<number | null>(null);

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <div style={{ ...card, padding: 18 }}>
                <div
                    style={{
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.text,
                        marginBottom: 12,
                    }}
                >
                    Skema Baru
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(190px, 1fr))',
                        gap: 12,
                    }}
                >
                    <div>
                        <label style={label}>Kode</label>
                        <input
                            style={input}
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                            placeholder="INS-HADIR"
                        />
                    </div>
                    <div>
                        <label style={label}>Nama</label>
                        <input
                            style={input}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Insentif Kehadiran"
                        />
                    </div>
                    <div>
                        <label style={label}>Dasar Perhitungan</label>
                        <select
                            style={input}
                            value={form.data.basis}
                            onChange={(event) =>
                                form.setData(
                                    'basis',
                                    event.target.value as IncentiveBasis,
                                )
                            }
                        >
                            {Object.entries(BASIS_LABELS).map(([value, text]) => (
                                <option key={value} value={value}>
                                    {text}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label style={label}>Komponen Payslip</label>
                        <select
                            style={input}
                            value={form.data.payroll_component_id}
                            onChange={(event) =>
                                form.setData(
                                    'payroll_component_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">— pilih komponen —</option>
                            {components.map((component) => (
                                <option key={component.id} value={component.id}>
                                    {component.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label style={label}>Berlaku Mulai</label>
                        <input
                            type="date"
                            style={input}
                            value={form.data.effective_start_date}
                            onChange={(event) =>
                                form.setData(
                                    'effective_start_date',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div>
                        <label style={label}>Berlaku Sampai</label>
                        <input
                            type="date"
                            style={input}
                            value={form.data.effective_end_date}
                            onChange={(event) =>
                                form.setData(
                                    'effective_end_date',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div>
                        <label style={label}>Pembulatan</label>
                        <select
                            style={input}
                            value={form.data.rounding}
                            onChange={(event) =>
                                form.setData('rounding', event.target.value)
                            }
                        >
                            <option value="none">Tanpa pembulatan</option>
                            <option value="nearest">Terdekat</option>
                            <option value="up">Ke atas</option>
                            <option value="down">Ke bawah</option>
                        </select>
                    </div>
                    <div>
                        <label style={label}>Kelipatan</label>
                        <input
                            type="number"
                            min={1}
                            style={input}
                            value={form.data.rounding_unit}
                            onChange={(event) =>
                                form.setData('rounding_unit', event.target.value)
                            }
                        />
                    </div>
                </div>
                <label
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        margin: '12px 0',
                        fontSize: 13,
                        color: C.text,
                        cursor: 'pointer',
                    }}
                >
                    <input
                        type="checkbox"
                        checked={form.data.prorate_partial_period}
                        onChange={(event) =>
                            form.setData(
                                'prorate_partial_period',
                                event.target.checked,
                            )
                        }
                    />
                    Prorata untuk karyawan masuk/resign di tengah periode
                </label>
                <ActionBtn
                    icon="plus"
                    label="Simpan Skema"
                    variant="primary"
                    onClick={onSubmit}
                />
                {Object.values(form.errors).length > 0 && (
                    <div style={{ marginTop: 10, fontSize: 12.5, color: C.red }}>
                        {Object.values(form.errors)[0] as string}
                    </div>
                )}
            </div>

            <div style={{ ...card, overflow: 'hidden' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={th}>Kode</th>
                            <th style={th}>Nama</th>
                            <th style={th}>Dasar</th>
                            <th style={th}>Komponen</th>
                            <th style={th}>Berlaku</th>
                            <th style={th}>Karyawan</th>
                            <th style={th}>Status</th>
                            <th style={{ ...th, textAlign: 'right' }}>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {schemes.length === 0 && (
                            <tr>
                                <td style={{ ...td, color: C.faint }} colSpan={8}>
                                    Belum ada skema insentif.
                                </td>
                            </tr>
                        )}
                        {schemes.map((scheme) => (
                            <>
                                <tr key={scheme.id}>
                                    <td style={td}>{scheme.code}</td>
                                    <td style={td}>{scheme.name}</td>
                                    <td style={td}>
                                        {BASIS_LABELS[scheme.basis]}
                                    </td>
                                    <td style={td}>{scheme.component ?? '—'}</td>
                                    <td style={td}>
                                        {scheme.effective_start_date} –{' '}
                                        {scheme.effective_end_date ?? 'seterusnya'}
                                    </td>
                                    <td style={td}>{scheme.assignments_count}</td>
                                    <td style={td}>{scheme.status}</td>
                                    <td style={{ ...td, textAlign: 'right' }}>
                                        <ActionBtn
                                            icon="list"
                                            label={
                                                openRules === scheme.id
                                                    ? 'Tutup Aturan'
                                                    : `Aturan (${scheme.rules.length})`
                                            }
                                            variant="neutral"
                                            onClick={() =>
                                                setOpenRules(
                                                    openRules === scheme.id
                                                        ? null
                                                        : scheme.id,
                                                )
                                            }
                                        />
                                    </td>
                                </tr>
                                {openRules === scheme.id && (
                                    <tr key={`${scheme.id}-rules`}>
                                        <td style={td} colSpan={8}>
                                            <RuleEditor scheme={scheme} />
                                        </td>
                                    </tr>
                                )}
                            </>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** The bands of one scheme, with a row to add another. */
function RuleEditor({ scheme }: { scheme: IncentiveSchemeRow }) {
    const form = useForm({
        min_value: '',
        max_value: '',
        amount_type: 'fixed',
        amount: '',
        notes: '',
    });

    return (
        <div style={{ display: 'grid', gap: 10 }}>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                    <tr>
                        <th style={th}>Dari</th>
                        <th style={th}>Sampai</th>
                        <th style={th}>Jenis</th>
                        <th style={th}>Nilai</th>
                        <th style={{ ...th, textAlign: 'right' }}>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {scheme.rules.length === 0 && (
                        <tr>
                            <td style={{ ...td, color: C.faint }} colSpan={5}>
                                Belum ada aturan — insentif akan dihitung 0.
                            </td>
                        </tr>
                    )}
                    {scheme.rules.map((rule) => (
                        <tr key={rule.id}>
                            <td style={td}>{rule.min_value ?? '—'}</td>
                            <td style={td}>{rule.max_value ?? '—'}</td>
                            <td style={td}>
                                {AMOUNT_TYPE_LABELS[rule.amount_type]}
                            </td>
                            <td style={td}>
                                {rule.amount_type === 'percent_of_basic'
                                    ? `${rule.amount}%`
                                    : rupiah(rule.amount)}
                            </td>
                            <td style={{ ...td, textAlign: 'right' }}>
                                <ActionBtn
                                    icon="trash-2"
                                    label="Hapus"
                                    variant="danger"
                                    onClick={() =>
                                        router.delete(
                                            IncentiveController.destroyRule(
                                                rule.id,
                                            ).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
                    gap: 10,
                    alignItems: 'end',
                }}
            >
                <div>
                    <label style={label}>
                        Dari ({BASIS_UNITS[scheme.basis]})
                    </label>
                    <input
                        type="number"
                        style={input}
                        value={form.data.min_value}
                        onChange={(event) =>
                            form.setData('min_value', event.target.value)
                        }
                    />
                </div>
                <div>
                    <label style={label}>Sampai</label>
                    <input
                        type="number"
                        style={input}
                        value={form.data.max_value}
                        onChange={(event) =>
                            form.setData('max_value', event.target.value)
                        }
                    />
                </div>
                <div>
                    <label style={label}>Jenis Nilai</label>
                    <select
                        style={input}
                        value={form.data.amount_type}
                        onChange={(event) =>
                            form.setData('amount_type', event.target.value)
                        }
                    >
                        {Object.entries(AMOUNT_TYPE_LABELS).map(
                            ([value, text]) => (
                                <option key={value} value={value}>
                                    {text}
                                </option>
                            ),
                        )}
                    </select>
                </div>
                <div>
                    <label style={label}>Nilai</label>
                    {form.data.amount_type === 'percent_of_basic' ? (
                        <input
                            type="number"
                            style={input}
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                        />
                    ) : (
                        <RupiahInput
                            value={form.data.amount}
                            onChange={(value: string) =>
                                form.setData('amount', value)
                            }
                            style={input}
                        />
                    )}
                </div>
                <ActionBtn
                    icon="plus"
                    label="Tambah Aturan"
                    variant="primary"
                    onClick={() =>
                        form.post(
                            IncentiveController.storeRule(scheme.route_key).url,
                            {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            },
                        )
                    }
                />
            </div>
        </div>
    );
}

/** Assign a scheme to employees, and list who currently has one. */
function AssignmentTab({
    schemes,
    employees,
    assignments,
}: {
    schemes: IncentiveSchemeRow[];
    employees: EmployeeOption[];
    assignments: IncentiveAssignmentRow[];
}) {
    const [schemeKey, setSchemeKey] = useState<string>(
        schemes[0]?.route_key ?? '',
    );
    const form = useForm<{
        employee_ids: number[];
        effective_start_date: string;
        effective_end_date: string;
    }>({
        employee_ids: [],
        effective_start_date: new Date().toISOString().slice(0, 10),
        effective_end_date: '',
    });

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <div style={{ ...card, padding: 18, display: 'grid', gap: 12 }}>
                <div style={{ fontSize: 14, fontWeight: 600, color: C.text }}>
                    Tetapkan Skema ke Karyawan
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                        gap: 12,
                    }}
                >
                    <div>
                        <label style={label}>Skema</label>
                        <select
                            style={input}
                            value={schemeKey}
                            onChange={(event) => setSchemeKey(event.target.value)}
                        >
                            {schemes.map((scheme) => (
                                <option key={scheme.id} value={scheme.route_key}>
                                    {scheme.code} · {scheme.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label style={label}>Berlaku Mulai</label>
                        <input
                            type="date"
                            style={input}
                            value={form.data.effective_start_date}
                            onChange={(event) =>
                                form.setData(
                                    'effective_start_date',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div>
                        <label style={label}>Berlaku Sampai</label>
                        <input
                            type="date"
                            style={input}
                            value={form.data.effective_end_date}
                            onChange={(event) =>
                                form.setData(
                                    'effective_end_date',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>

                <div>
                    <label style={label}>
                        Karyawan ({form.data.employee_ids.length} dipilih)
                    </label>
                    <div
                        style={{
                            maxHeight: 220,
                            overflowY: 'auto',
                            border: `1px solid ${C.line}`,
                            borderRadius: 8,
                            padding: 10,
                            display: 'grid',
                            gap: 6,
                        }}
                    >
                        {employees.map((employee) => (
                            <label
                                key={employee.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    fontSize: 13,
                                    cursor: 'pointer',
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.employee_ids.includes(
                                        employee.id,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'employee_ids',
                                            event.target.checked
                                                ? [
                                                      ...form.data.employee_ids,
                                                      employee.id,
                                                  ]
                                                : form.data.employee_ids.filter(
                                                      (id) => id !== employee.id,
                                                  ),
                                        )
                                    }
                                />
                                {employee.name} · {employee.employee_number}
                            </label>
                        ))}
                    </div>
                </div>

                <div>
                    <ActionBtn
                        icon="user-plus"
                        label="Tetapkan"
                        variant="primary"
                        onClick={() =>
                            form.post(
                                IncentiveController.assign(schemeKey).url,
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        form.setData('employee_ids', []),
                                },
                            )
                        }
                    />
                </div>
            </div>

            <div style={{ ...card, overflow: 'hidden' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={th}>Skema</th>
                            <th style={th}>Karyawan</th>
                            <th style={th}>Mulai</th>
                            <th style={th}>Sampai</th>
                            <th style={th}>Status</th>
                            <th style={{ ...th, textAlign: 'right' }}>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {assignments.length === 0 && (
                            <tr>
                                <td style={{ ...td, color: C.faint }} colSpan={6}>
                                    Belum ada penetapan.
                                </td>
                            </tr>
                        )}
                        {assignments.map((row) => (
                            <tr key={row.id}>
                                <td style={td}>{row.scheme}</td>
                                <td style={td}>
                                    {row.employee}
                                    <div
                                        style={{ fontSize: 12, color: C.faint }}
                                    >
                                        {row.employee_number}
                                    </div>
                                </td>
                                <td style={td}>{row.effective_start_date}</td>
                                <td style={td}>
                                    {row.effective_end_date ?? '—'}
                                </td>
                                <td style={td}>{row.status}</td>
                                <td style={{ ...td, textAlign: 'right' }}>
                                    {row.status === 'active' && (
                                        <ActionBtn
                                            icon="x"
                                            label="Hentikan"
                                            variant="danger"
                                            onClick={() =>
                                                router.delete(
                                                    IncentiveController.unassign(
                                                        row.route_key,
                                                    ).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** Calculate a period, then review and approve what it produced. */
function CalculationTab({
    schemes,
    periods,
    selectedPeriodId,
    calculations,
    selected,
    onToggle,
    onAct,
}: {
    schemes: IncentiveSchemeRow[];
    periods: PeriodOption[];
    selectedPeriodId: number | null;
    calculations: IncentiveCalculationRow[];
    selected: number[];
    onToggle: (id: number) => void;
    onAct: (url: string, extra?: Record<string, unknown>) => void;
}) {
    const [schemeKey, setSchemeKey] = useState<string>(
        schemes[0]?.route_key ?? '',
    );
    const [periodId, setPeriodId] = useState<string>(
        selectedPeriodId ? String(selectedPeriodId) : '',
    );
    const [reason, setReason] = useState('');

    const total = calculations
        .filter((row) => row.status !== 'rejected')
        .reduce((sum, row) => sum + row.amount, 0);

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <div
                style={{
                    ...card,
                    padding: 18,
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                    gap: 12,
                    alignItems: 'end',
                }}
            >
                <div>
                    <label style={label}>Skema</label>
                    <select
                        style={input}
                        value={schemeKey}
                        onChange={(event) => setSchemeKey(event.target.value)}
                    >
                        {schemes.map((scheme) => (
                            <option key={scheme.id} value={scheme.route_key}>
                                {scheme.code} · {scheme.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label style={label}>Periode Payroll</label>
                    <select
                        style={input}
                        value={periodId}
                        onChange={(event) => {
                            setPeriodId(event.target.value);
                            router.get(
                                IncentiveController.index().url,
                                { period: event.target.value },
                                { preserveState: true, preserveScroll: true },
                            );
                        }}
                    >
                        {periods.map((period) => (
                            <option key={period.id} value={period.id}>
                                {period.name} ({period.status})
                            </option>
                        ))}
                    </select>
                </div>
                <ActionBtn
                    icon="calculator"
                    label="Hitung Insentif"
                    variant="primary"
                    onClick={() =>
                        router.post(
                            IncentiveController.calculate(schemeKey).url,
                            { payroll_period_id: Number(periodId) },
                            { preserveScroll: true },
                        )
                    }
                />
            </div>

            <div style={{ ...card, overflow: 'hidden' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        padding: '12px 16px',
                        borderBottom: `1px solid ${C.line}`,
                        gap: 10,
                        flexWrap: 'wrap',
                    }}
                >
                    <div style={{ fontSize: 13.5, color: C.text }}>
                        {calculations.length} baris · total{' '}
                        <strong>{rupiah(total)}</strong> · {selected.length}{' '}
                        dipilih
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <ActionBtn
                            icon="send"
                            label="Ajukan"
                            variant="neutral"
                            onClick={() =>
                                onAct(IncentiveController.submit().url)
                            }
                        />
                        <ActionBtn
                            icon="check"
                            label="Setujui"
                            variant="success"
                            onClick={() =>
                                onAct(IncentiveController.approve().url)
                            }
                        />
                        <input
                            style={{ ...input, width: 200, height: 34 }}
                            placeholder="Alasan penolakan…"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                        />
                        <ActionBtn
                            icon="x"
                            label="Tolak"
                            variant="danger"
                            onClick={() =>
                                onAct(IncentiveController.reject().url, {
                                    reason,
                                })
                            }
                        />
                    </div>
                </div>

                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={{ ...th, width: 36 }} />
                            <th style={th}>Karyawan</th>
                            <th style={th}>Skema</th>
                            <th style={th}>Terukur</th>
                            <th style={th}>Nominal</th>
                            <th style={th}>Status</th>
                            <th style={th}>Disetujui</th>
                        </tr>
                    </thead>
                    <tbody>
                        {calculations.length === 0 && (
                            <tr>
                                <td style={{ ...td, color: C.faint }} colSpan={7}>
                                    Belum ada perhitungan untuk periode ini.
                                </td>
                            </tr>
                        )}
                        {calculations.map((row) => (
                            <tr key={row.id}>
                                <td style={td}>
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(row.id)}
                                        disabled={row.status === 'locked'}
                                        onChange={() => onToggle(row.id)}
                                    />
                                </td>
                                <td style={td}>
                                    {row.employee}
                                    <div
                                        style={{ fontSize: 12, color: C.faint }}
                                    >
                                        {row.employee_number}
                                    </div>
                                </td>
                                <td style={td}>{row.scheme}</td>
                                <td style={td}>{row.measured_value}</td>
                                <td style={td}>
                                    {rupiah(row.amount)}
                                    {row.overridden && (
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.amber,
                                            }}
                                        >
                                            diubah manual dari{' '}
                                            {rupiah(row.computed_amount ?? 0)}
                                        </div>
                                    )}
                                </td>
                                <td style={td}>
                                    <span
                                        style={{
                                            padding: '3px 10px',
                                            borderRadius: 100,
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color: statusColor(row.status),
                                            background: `${statusColor(row.status)}1A`,
                                        }}
                                    >
                                        {STATUS_LABELS[row.status] ?? row.status}
                                    </span>
                                    {row.reason && (
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.faint,
                                            }}
                                        >
                                            {row.reason}
                                        </div>
                                    )}
                                </td>
                                <td style={td}>
                                    {row.approver ?? '—'}
                                    <div
                                        style={{ fontSize: 12, color: C.faint }}
                                    >
                                        {row.approved_at ?? ''}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div style={{ fontSize: 12.5, color: C.faint }}>
                <AIcon name="info" size={13} /> Insentif yang Anda hitung sendiri
                harus disetujui orang lain, dan hanya yang berstatus disetujui
                yang ikut dibayar saat payroll dijalankan.
            </div>
        </div>
    );
}
