import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import SalaryAssignmentController from '@/actions/App/Http/Controllers/Avana/SalaryAssignmentController';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import SalaryMasterController from '@/actions/App/Http/Controllers/Avana/SalaryMasterController';
import { AIcon, C, card } from '@/lib/avana';

interface Option {
    id: number;
    label: string;
}

interface PreviewRow {
    id: number;
    name: string;
    nik: string | null;
    position: string | null;
    branch: string | null;
    current_master: string | null;
    current_total: number;
    template_total: number;
    has_own_figures: boolean;
    override_count: number;
}

interface Filters {
    salary_master_id: number | null;
    branch_id: number | null;
    department_id: number | null;
    position_id: number | null;
    salary_grade_id: number | null;
    employment_status: string | null;
    assignment: string;
    /** Field Kustom filters, keyed by field key (Client, Placement, NPO, …). */
    custom: Record<string, string>;
    /** all | active | none — contract in force on the effective date. */
    contract: string;
    effective_start_date: string;
    existing: 'skip' | 'overwrite';
}

/** One Field Kustom the tenant defined for employees. */
interface CustomFieldOption {
    key: string;
    label: string;
    type: string;
    options: string[];
}

interface Props {
    filters: Filters;
    /** Set when the requested Master Gaji exists but is switched off. */
    masterNotice: string | null;
    preview: PreviewRow[];
    previewEmployeeIds: number[];
    previewToken: string | null;
    template: {
        id: number;
        code: string;
        category: string;
        component_count: number;
        variable_count: number;
        total: number;
    } | null;
    masterOptions: Option[];
    branchOptions: Option[];
    departmentOptions: Option[];
    positionOptions: Option[];
    gradeOptions: Option[];
    customFieldOptions: CustomFieldOption[];
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
    padding: '10px 18px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};
const fieldLabel: React.CSSProperties = {
    fontSize: 12.5,
    color: C.muted,
    marginBottom: 6,
};

const rupiah = (n: number) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

const EMPLOYMENT_STATUSES = ['permanent', 'contract', 'probation', 'intern'];

const ASSIGNMENT_SCOPES: { value: string; label: string }[] = [
    { value: 'all', label: 'Semua karyawan' },
    { value: 'unassigned', label: 'Belum punya Master Gaji' },
    { value: 'other', label: 'Master Gaji lain' },
    { value: 'assigned', label: 'Sudah di master ini' },
];

export default function MassAssignment({
    filters,
    masterNotice,
    preview = [],
    previewEmployeeIds = [],
    previewToken,
    template,
    masterOptions = [],
    branchOptions = [],
    departmentOptions = [],
    positionOptions = [],
    gradeOptions = [],
    customFieldOptions = [],
    salaryFloor,
}: Props) {
    const [picked, setPicked] = useState<number[]>([]);
    const [reason, setReason] = useState('');
    // The refusal that came back from the last attempt, shown next to the field
    // it belongs to: a mass assignment that silently does nothing reads as a
    // broken button, not as "this date sits in a closed payroll".
    const [failures, setFailures] = useState<Record<string, string>>({});

    // A new preview is a new set of people; carrying ticks across it would
    // apply the template to someone the filter no longer shows. The ticks are
    // reset during render so the Apply button never counts a stale selection.
    const previewKey = `${previewToken ?? 'none'}:${preview.map((p) => p.id).join(',')}`;
    const [seededFor, setSeededFor] = useState<string | null>(null);

    if (seededFor !== previewKey) {
        setSeededFor(previewKey);
        setPicked(preview.map((p) => p.id));
    }

    const setFilter = (key: keyof Filters, value: string) =>
        router.get(
            SalaryAssignmentController.index().url,
            {
                ...Object.fromEntries(
                    Object.entries(filters).filter(
                        ([k, v]) => v !== null && k !== key,
                    ),
                ),
                ...(value === '' ? {} : { [key]: value }),
            },
            { preserveScroll: true, replace: true },
        );

    /** Filter on one Field Kustom without dropping the others. */
    const setCustomFilter = (key: string, value: string) => {
        const custom = { ...(filters.custom ?? {}) };

        if (value === '') {
            delete custom[key];
        } else {
            custom[key] = value;
        }

        router.get(
            SalaryAssignmentController.index().url,
            {
                ...Object.fromEntries(
                    Object.entries(filters).filter(
                        ([k, v]) => v !== null && k !== 'custom',
                    ),
                ),
                custom,
            },
            { preserveScroll: true, replace: true },
        );
    };

    const toggle = (id: number) =>
        setPicked((p) =>
            p.includes(id) ? p.filter((x) => x !== id) : [...p, id],
        );

    const apply = () => {
        if (template === null || previewToken === null || picked.length === 0) {
            return;
        }

        router.post(
            SalaryAssignmentController.apply().url,
            {
                salary_master_id: template.id,
                employee_ids: picked,
                preview_employee_ids: previewEmployeeIds,
                preview_token: previewToken,
                effective_start_date: filters.effective_start_date,
                reason: reason || null,
                existing: filters.existing,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReason('');
                    setFailures({});
                },
                onError: (errors) => {
                    setFailures(errors as Record<string, string>);
                    // Every key is reported, not a hand-picked few: the reason
                    // and preview-employee refusals used to fall through to a
                    // bare "Penetapan gagal" that named nothing.
                    toast.error(
                        errors.effective_start_date ??
                            errors.reason ??
                            errors.preview_token ??
                            errors.salary_master_id ??
                            errors.employee_ids ??
                            Object.values(errors)[0] ??
                            'Penetapan gagal',
                    );
                },
            },
        );
    };

    const withOwnFigures = preview.filter(
        (p) => p.has_own_figures && picked.includes(p.id),
    ).length;

    const select = (
        key: keyof Filters,
        label: string,
        options: Option[],
        placeholder: string,
    ) => (
        <div>
            <div style={fieldLabel}>{label}</div>
            <SearchableSelect
                value={filters[key] === null ? '' : String(filters[key])}
                onChange={(v) => setFilter(key, v)}
                placeholder={placeholder}
                searchPlaceholder="Cari…"
                allowClear
                options={options.map((o) => ({
                    value: String(o.id),
                    label: o.label,
                }))}
            />
        </div>
    );

    return (
        <>
            <div>

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(200px, 1fr))',
                            gap: 12,
                        }}
                    >
                        <div>
                            <div style={fieldLabel}>Master Gaji</div>
                            <SearchableSelect
                                value={
                                    filters.salary_master_id === null
                                        ? ''
                                        : String(filters.salary_master_id)
                                }
                                onChange={(v) =>
                                    setFilter('salary_master_id', v)
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
                        {select(
                            'branch_id',
                            'Cabang',
                            branchOptions,
                            'Semua cabang',
                        )}
                        {select(
                            'department_id',
                            'Departemen',
                            departmentOptions,
                            'Semua departemen',
                        )}
                        {select(
                            'position_id',
                            'Jabatan',
                            positionOptions,
                            'Semua jabatan',
                        )}
                        {select(
                            'salary_grade_id',
                            'Grade',
                            gradeOptions,
                            'Semua grade',
                        )}
                        <div>
                            <div style={fieldLabel}>Status kerja</div>
                            <select
                                style={input}
                                value={filters.employment_status ?? ''}
                                onChange={(e) =>
                                    setFilter(
                                        'employment_status',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">Semua status</option>
                                {EMPLOYMENT_STATUSES.map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <div style={fieldLabel}>Cakupan</div>
                            <select
                                style={input}
                                value={filters.assignment}
                                onChange={(e) =>
                                    setFilter('assignment', e.target.value)
                                }
                            >
                                {ASSIGNMENT_SCOPES.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <div style={fieldLabel}>Kontrak</div>
                            <select
                                style={input}
                                value={filters.contract ?? 'all'}
                                onChange={(e) =>
                                    setFilter('contract', e.target.value)
                                }
                            >
                                <option value="all">Semua karyawan</option>
                                <option value="active">
                                    Kontrak aktif di tanggal berlaku
                                </option>
                                <option value="none">Tanpa kontrak aktif</option>
                            </select>
                        </div>
                        {customFieldOptions.map((field) => (
                            <div key={field.key}>
                                <div style={fieldLabel}>{field.label}</div>
                                {field.options.length > 0 ? (
                                    <select
                                        style={input}
                                        value={filters.custom?.[field.key] ?? ''}
                                        onChange={(e) =>
                                            setCustomFilter(
                                                field.key,
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Semua {field.label.toLowerCase()}
                                        </option>
                                        {field.options.map((option) => (
                                            <option key={option} value={option}>
                                                {option}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        style={input}
                                        defaultValue={
                                            filters.custom?.[field.key] ?? ''
                                        }
                                        placeholder={`Semua ${field.label.toLowerCase()}`}
                                        onBlur={(e) =>
                                            setCustomFilter(
                                                field.key,
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {template === null ? (
                    <PreviewPlaceholder notice={masterNotice} />
                ) : (
                    <>
                        <div
                            style={{
                                ...card,
                                padding: 16,
                                marginBottom: 18,
                                display: 'flex',
                                gap: 24,
                                flexWrap: 'wrap',
                                fontSize: 13,
                                color: C.muted,
                            }}
                        >
                            <span>
                                Template{' '}
                                <strong style={{ color: C.navy }}>
                                    {template.code}
                                </strong>{' '}
                                · {template.category}
                            </span>
                            <span>
                                {template.component_count -
                                    template.variable_count}{' '}
                                komponen tetap
                            </span>
                            {template.variable_count > 0 && (
                                <span>
                                    {template.variable_count} tarif variabel
                                </span>
                            )}
                            <span>
                                Total template{' '}
                                <strong style={{ color: C.navy }}>
                                    {rupiah(template.total)}
                                </strong>
                            </span>
                            <span>
                                {preview.length} karyawan cocok ·{' '}
                                {picked.length} dipilih
                            </span>
                        </div>

                        <div style={{ ...card, marginBottom: 18 }}>
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                    }}
                                >
                                    <thead>
                                        <tr>
                                            <th style={{ ...th, width: 44 }}>
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        preview.length > 0 &&
                                                        picked.length ===
                                                            preview.length
                                                    }
                                                    onChange={(e) =>
                                                        setPicked(
                                                            e.target.checked
                                                                ? preview.map(
                                                                      (p) =>
                                                                          p.id,
                                                                  )
                                                                : [],
                                                        )
                                                    }
                                                />
                                            </th>
                                            {[
                                                'Karyawan',
                                                'Jabatan / Cabang',
                                                'Master sekarang',
                                                'Gaji tetap sekarang',
                                                'Setelah template',
                                                'Catatan',
                                            ].map((h) => (
                                                <th key={h} style={th}>
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.length === 0 ? (
                                            <tr>
                                                <td
                                                    style={{
                                                        ...td,
                                                        textAlign: 'center',
                                                        color: C.faint,
                                                        padding: 28,
                                                    }}
                                                    colSpan={7}
                                                >
                                                    Tidak ada karyawan yang
                                                    cocok dengan filter ini.
                                                </td>
                                            </tr>
                                        ) : (
                                            preview.map((p) => (
                                                <tr key={p.id}>
                                                    <td style={td}>
                                                        <input
                                                            type="checkbox"
                                                            checked={picked.includes(
                                                                p.id,
                                                            )}
                                                            onChange={() =>
                                                                toggle(p.id)
                                                            }
                                                        />
                                                    </td>
                                                    <td style={td}>
                                                        {p.name}
                                                        {p.nik !== null && (
                                                            <div
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    color: C.faint,
                                                                }}
                                                            >
                                                                {p.nik}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            color: C.muted,
                                                            fontSize: 12.5,
                                                        }}
                                                    >
                                                        {[p.position, p.branch]
                                                            .filter(Boolean)
                                                            .join(' · ') || '—'}
                                                    </td>
                                                    <td style={td}>
                                                        {p.current_master ??
                                                            '—'}
                                                    </td>
                                                    <td style={td}>
                                                        {rupiah(
                                                            p.current_total,
                                                        )}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {rupiah(
                                                            p.template_total,
                                                        )}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            fontSize: 12,
                                                            color: p.has_own_figures
                                                                ? '#B45309'
                                                                : C.faint,
                                                        }}
                                                    >
                                                        {p.has_own_figures
                                                            ? 'Punya nominal khusus'
                                                            : '—'}
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
                                    display: 'grid',
                                    gridTemplateColumns: '200px 1fr 220px auto',
                                    gap: 12,
                                    alignItems: 'end',
                                }}
                            >
                                <div>
                                    <div style={fieldLabel}>Berlaku mulai</div>
                                    <DatePicker
                                        value={filters.effective_start_date}
                                        onChange={(value) =>
                                            setFilter(
                                                'effective_start_date',
                                                value,
                                            )
                                        }
                                        placeholder="Pilih tanggal"
                                        width={200}
                                    />
                                </div>
                                <div>
                                    <div style={fieldLabel}>
                                        Alasan penetapan
                                        <span style={{ color: C.faint }}>
                                            {' '}
                                            · wajib bila gaji sudah berjalan
                                        </span>
                                    </div>
                                    <input
                                        style={{
                                            ...input,
                                            ...(failures.reason
                                                ? {
                                                      border: '1px solid #DC2626',
                                                  }
                                                : {}),
                                        }}
                                        type="text"
                                        placeholder="Penyesuaian UMR 2027, restrukturisasi…"
                                        value={reason}
                                        onChange={(e) =>
                                            setReason(e.target.value)
                                        }
                                    />
                                </div>
                                <div>
                                    <div style={fieldLabel}>
                                        Karyawan bernominal khusus
                                    </div>
                                    <select
                                        style={input}
                                        value={filters.existing}
                                        onChange={(e) =>
                                            setFilter(
                                                'existing',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="skip">
                                            Pertahankan nominalnya
                                        </option>
                                        <option value="overwrite">
                                            Timpa dengan template
                                        </option>
                                    </select>
                                </div>
                                <button
                                    type="button"
                                    style={primaryBtn}
                                    onClick={apply}
                                    disabled={picked.length === 0}
                                >
                                    <AIcon
                                        name="check"
                                        size={15}
                                        color="#fff"
                                    />
                                    Terapkan ke {picked.length} karyawan
                                </button>
                            </div>

                            {Object.values(failures).length > 0 && (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: '#DC2626',
                                        marginTop: 12,
                                    }}
                                >
                                    {Object.values(failures)[0]}
                                </div>
                            )}

                            {withOwnFigures > 0 && (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color:
                                            filters.existing === 'overwrite'
                                                ? '#B45309'
                                                : C.muted,
                                        marginTop: 12,
                                    }}
                                >
                                    {withOwnFigures} karyawan terpilih punya
                                    nominal khusus.{' '}
                                    {filters.existing === 'overwrite'
                                        ? 'Nominal itu akan diganti nominal template.'
                                        : 'Nominal itu dipertahankan; komponen lain tetap diisi template.'}
                                </div>
                            )}

                            {salaryFloor !== null && (
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: C.muted,
                                        marginTop: 8,
                                    }}
                                >
                                    Payroll sudah final sampai periode
                                    sebelumnya — tanggal berlaku paling awal{' '}
                                    {salaryFloor}.
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}


/**
 * What fills the preview area before there is a preview: either an invitation to
 * pick a template, or the reason the chosen one cannot be used — with the way
 * out of it, so the screen is never a dead end.
 */
function PreviewPlaceholder({ notice }: { notice: string | null }) {
    const blocked = notice !== null;
    const tone = blocked ? C.amber : C.primary;

    return (
        <div
            style={{
                ...card,
                padding: '44px 28px',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                textAlign: 'center',
                gap: 12,
            }}
        >
            <div
                style={{
                    width: 52,
                    height: 52,
                    borderRadius: 999,
                    background: `${tone}1A`,
                    display: 'grid',
                    placeItems: 'center',
                }}
            >
                <AIcon
                    name={blocked ? 'triangle-alert' : 'users'}
                    size={22}
                    color={tone}
                />
            </div>

            <div style={{ fontSize: 15.5, fontWeight: 600, color: C.navy }}>
                {blocked
                    ? 'Master Gaji ini tidak bisa dipakai'
                    : 'Pilih Master Gaji dulu'}
            </div>

            <div
                style={{
                    fontSize: 13.5,
                    color: C.muted,
                    maxWidth: 520,
                    lineHeight: 1.6,
                }}
            >
                {notice ??
                    'Setelah Master Gaji dipilih, preview menampilkan gaji tetap setiap karyawan sekarang dan setelah template diterapkan — sebelum apa pun disimpan.'}
            </div>

            {blocked && (
                <a
                    href={SalaryMasterController.index().url}
                    style={{
                        marginTop: 4,
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 7,
                        padding: '9px 16px',
                        borderRadius: 9,
                        background: C.primary,
                        color: '#fff',
                        fontSize: 13,
                        fontWeight: 600,
                        textDecoration: 'none',
                    }}
                >
                    <AIcon name="file-cog" size={15} color="#fff" />
                    Buka Master Gaji
                </a>
            )}
        </div>
    );
}
