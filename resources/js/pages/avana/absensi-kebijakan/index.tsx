import { Head, router, useForm } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import AttendancePolicyController from '@/actions/App/Http/Controllers/Avana/AttendancePolicyController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnSave, C, card } from '@/lib/avana';

/** Mirrors AttendancePolicy::SCOPES, loosest last. */
const SCOPE_OPTIONS = [
    {
        value: 'assigned',
        label: 'Lokasi ditugaskan',
        hint: 'Hanya di kantor / cabang karyawan sendiri.',
    },
    {
        value: 'any_branch',
        label: 'Semua cabang',
        hint: 'Boleh absen di kantor cabang mana pun. Radius tetap dicek.',
    },
    {
        value: 'anywhere',
        label: 'WFA — di mana saja',
        hint: 'Radius tidak dicek. GPS tetap direkam dan ditandai WFA.',
    },
] as const;

/** Mirrors AttendancePolicy::FACE_MODES, strictest first. */
const FACE_MODE_OPTIONS = [
    {
        value: 'recognition',
        label: 'Face Recognition',
        hint: 'Cocokkan wajah dengan data terdaftar (verifikasi identitas 1:1).',
    },
    {
        value: 'detection',
        label: 'Face Detection',
        hint: 'Cukup deteksi wajah asli (selfie), tanpa mencocokkan identitas.',
    },
    {
        value: 'off',
        label: 'Tanpa Wajah',
        hint: 'Absen tanpa verifikasi wajah sama sekali.',
    },
] as const;

interface Policy {
    attendance_scope: string;
    device_binding_enabled: boolean;
    face_mode: string;
    require_face_enrollment: boolean;
    require_liveness_challenge: boolean;
    face_enforcement: string;
    integrity_enforcement: string;
    block_mock_location: boolean;
    block_rooted: boolean;
    block_emulator: boolean;
}

/** One selectable scope, mirroring `AttendancePolicy::scopeOptions()`. */
interface ScopeOption {
    value: string;
    label: string;
}

/** An employee whose scope departs from the tenant default. */
interface ScopeOverride {
    id: number;
    route_key: string;
    name: string;
    employee_number: string | null;
    attendance_scope: string;
    scope_label: string;
}

/** An employee still on the tenant default, offered by the picker. */
interface AssignableEmployee {
    id: number;
    name: string;
    employee_number: string | null;
}

interface PageProps {
    policy: Policy;
    attestationEnabled: boolean;
    scopeOptions: ScopeOption[];
    overrides: ScopeOverride[];
    assignableEmployees: AssignableEmployee[];
}

const POLICY_TABS = [
    {
        key: 'area',
        label: 'Area Absensi',
        hint: 'Default area untuk semua karyawan.',
        icon: 'map-pin',
    },
    {
        key: 'login',
        label: 'Login',
        hint: 'Pengikatan akun ke perangkat.',
        icon: 'smartphone',
    },
    {
        key: 'face',
        label: 'Wajah',
        hint: 'Mode verifikasi wajah saat absen.',
        icon: 'scan-face',
    },
    {
        key: 'device',
        label: 'Perangkat',
        hint: 'Integritas device dan anti-manipulasi.',
        icon: 'shield',
    },
    {
        key: 'override',
        label: 'Pengecualian',
        hint: 'Atur karyawan yang beda area absensi.',
        icon: 'users',
    },
] as const;

type PolicyTabKey = (typeof POLICY_TABS)[number]['key'];

const sectionTitle: CSSProperties = {
    fontSize: 15,
    fontWeight: 700,
    color: C.text,
    marginBottom: 4,
};

const sectionHint: CSSProperties = {
    fontSize: 13,
    color: C.muted,
    marginBottom: 16,
};

function Toggle({
    on,
    onChange,
    label,
    hint,
}: {
    on: boolean;
    onChange: (v: boolean) => void;
    label: string;
    hint?: string;
}) {
    return (
        <label
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 12,
                cursor: 'pointer',
                padding: '10px 0',
            }}
        >
            <button
                type="button"
                onClick={() => onChange(!on)}
                aria-pressed={on}
                style={{
                    flexShrink: 0,
                    width: 40,
                    height: 24,
                    borderRadius: 999,
                    border: 'none',
                    background: on ? C.primary : '#CBD5E1',
                    position: 'relative',
                    cursor: 'pointer',
                    transition: 'background 120ms',
                }}
            >
                <span
                    style={{
                        position: 'absolute',
                        top: 2,
                        left: on ? 18 : 2,
                        width: 20,
                        height: 20,
                        borderRadius: '50%',
                        background: '#fff',
                        transition: 'left 120ms',
                    }}
                />
            </button>
            <span>
                <span
                    style={{
                        display: 'block',
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.text,
                    }}
                >
                    {label}
                </span>
                {hint ? (
                    <span
                        style={{
                            display: 'block',
                            fontSize: 12.5,
                            color: C.muted,
                            marginTop: 2,
                        }}
                    >
                        {hint}
                    </span>
                ) : null}
            </span>
        </label>
    );
}

function EnforcementChoice({
    value,
    onChange,
}: {
    value: string;
    onChange: (v: string) => void;
}) {
    const options: { key: string; label: string; hint: string }[] = [
        {
            key: 'block',
            label: 'Blokir',
            hint: 'Absen ditolak saat cek gagal.',
        },
        {
            key: 'flag',
            label: 'Tandai',
            hint: 'Absen tetap masuk, ditandai untuk HR.',
        },
    ];

    return (
        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
            {options.map((opt) => {
                const active = value === opt.key;

                return (
                    <button
                        key={opt.key}
                        type="button"
                        onClick={() => onChange(opt.key)}
                        style={{
                            flex: 1,
                            textAlign: 'left',
                            padding: '12px 14px',
                            borderRadius: 10,
                            border: `1.5px solid ${active ? C.primary : C.border}`,
                            background: active ? '#EEF2FF' : '#fff',
                            cursor: 'pointer',
                        }}
                    >
                        <span
                            style={{
                                display: 'block',
                                fontSize: 14,
                                fontWeight: 700,
                                color: active ? C.primary : C.text,
                            }}
                        >
                            {opt.label}
                        </span>
                        <span
                            style={{
                                display: 'block',
                                fontSize: 12,
                                color: C.muted,
                                marginTop: 2,
                            }}
                        >
                            {opt.hint}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

function Section({ children }: { children: ReactNode }) {
    return (
        <div style={{ ...card, padding: 20, marginBottom: 16 }}>{children}</div>
    );
}

const controlStyle: CSSProperties = {
    width: '100%',
    height: 40,
    padding: '0 10px',
    borderRadius: 9,
    border: `1px solid ${C.border}`,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
};

/**
 * Exceptions to the tenant-wide area rule: one employee — a field sales, a
 * driver — put on WFA without loosening the geofence for everyone else.
 */
function OverridePanel({
    overrides,
    assignableEmployees,
    scopeOptions,
    tenantScopeLabel,
    embedded = false,
}: {
    overrides: ScopeOverride[];
    assignableEmployees: AssignableEmployee[];
    scopeOptions: ScopeOption[];
    tenantScopeLabel: string;
    embedded?: boolean;
}) {
    type OverrideDraft = {
        employee_id: string;
        attendance_scope: string;
    };

    const form = useForm({
        overrides: [
            {
                employee_id: '',
                attendance_scope: 'anywhere',
            },
        ] as OverrideDraft[],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(AttendancePolicyController.storeOverride.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('overrides', [
                    { employee_id: '', attendance_scope: 'anywhere' },
                ]);
                toast.success('Pengecualian ditambahkan');
            },
        });
    };

    const updateRow = (index: number, changes: Partial<OverrideDraft>) => {
        form.setData(
            'overrides',
            form.data.overrides.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...changes } : row,
            ),
        );
    };

    const removeRow = (index: number) => {
        form.setData(
            'overrides',
            form.data.overrides.length === 1
                ? [{ employee_id: '', attendance_scope: 'anywhere' }]
                : form.data.overrides.filter(
                      (_, rowIndex) => rowIndex !== index,
                  ),
        );
    };

    const addRow = () => {
        form.setData('overrides', [
            ...form.data.overrides,
            { employee_id: '', attendance_scope: 'anywhere' },
        ]);
    };

    const remove = (employee: ScopeOverride) => {
        router.delete(
            AttendancePolicyController.destroyOverride.url(employee.route_key),
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        `${employee.name} kembali ikut kebijakan perusahaan`,
                    ),
            },
        );
    };

    const content = (
        <>
            <div style={sectionTitle}>Pengecualian per Karyawan</div>
            <div style={sectionHint}>
                Karyawan di daftar ini memakai area absensi sendiri. Sisanya
                mengikuti kebijakan perusahaan di atas ({tenantScopeLabel}).
            </div>

            <form onSubmit={submit} style={{ marginBottom: 16 }}>
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 8 }}
                >
                    {form.data.overrides.map((row, index) => {
                        const usedByOtherRow = form.data.overrides
                            .filter((_, rowIndex) => rowIndex !== index)
                            .map((item) => item.employee_id);
                        const employeeOptions = assignableEmployees
                            .filter(
                                (employee) =>
                                    !usedByOtherRow.includes(
                                        String(employee.id),
                                    ) ||
                                    String(employee.id) === row.employee_id,
                            )
                            .map((employee) => ({
                                value: String(employee.id),
                                label: employee.employee_number
                                    ? `${employee.name} (${employee.employee_number})`
                                    : employee.name,
                            }));

                        return (
                            <div
                                key={index}
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'minmax(220px, 1fr) minmax(220px, 1fr) auto',
                                    gap: 10,
                                    alignItems: 'end',
                                }}
                            >
                                <label>
                                    <span
                                        style={{
                                            display: 'block',
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginBottom: 5,
                                        }}
                                    >
                                        Karyawan {index + 1}
                                    </span>
                                    <SearchableSelect
                                        value={row.employee_id}
                                        onChange={(value: string) =>
                                            updateRow(index, {
                                                employee_id: value,
                                            })
                                        }
                                        options={employeeOptions}
                                        placeholder="Pilih karyawan…"
                                        searchPlaceholder="Cari nama atau NIK karyawan…"
                                        allowClear
                                        style={controlStyle}
                                    />
                                </label>

                                <label>
                                    <span
                                        style={{
                                            display: 'block',
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginBottom: 5,
                                        }}
                                    >
                                        Area Absensi
                                    </span>
                                    <select
                                        value={row.attendance_scope}
                                        onChange={(event) =>
                                            updateRow(index, {
                                                attendance_scope:
                                                    event.target.value,
                                            })
                                        }
                                        style={controlStyle}
                                    >
                                        {scopeOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <button
                                    type="button"
                                    onClick={() => removeRow(index)}
                                    aria-label={`Hapus baris karyawan ${index + 1}`}
                                    style={{
                                        ...btnSave,
                                        padding: '0 12px',
                                        height: 40,
                                        background: '#fff',
                                        color: C.red,
                                        border: `1px solid ${C.border}`,
                                    }}
                                >
                                    <AIcon name="trash-2" size={15} />
                                </button>
                            </div>
                        );
                    })}
                </div>

                <div style={{ display: 'flex', gap: 10, marginTop: 10 }}>
                    <button
                        type="button"
                        onClick={addRow}
                        style={{
                            ...btnSave,
                            background: '#fff',
                            color: C.primary,
                            border: `1px solid ${C.primary}`,
                        }}
                    >
                        <AIcon name="plus" size={15} /> Tambah baris
                    </button>
                    <button
                        type="submit"
                        style={{
                            ...btnSave,
                            opacity: form.processing ? 0.6 : 1,
                        }}
                        disabled={
                            form.processing ||
                            form.data.overrides.some(
                                (row) => row.employee_id === '',
                            )
                        }
                    >
                        <AIcon name="save" size={16} /> Simpan pengecualian
                    </button>
                </div>
            </form>

            {form.errors.overrides || form.errors.employee_ids ? (
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.red,
                        marginBottom: 12,
                    }}
                >
                    {form.errors.overrides || form.errors.employee_ids}
                </div>
            ) : null}

            {overrides.length === 0 ? (
                <div style={{ fontSize: 13, color: C.faint }}>
                    Belum ada pengecualian — semua karyawan mengikuti kebijakan
                    perusahaan.
                </div>
            ) : (
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 8,
                    }}
                >
                    {overrides.map((employee) => (
                        <div
                            key={employee.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 12,
                                padding: '10px 12px',
                                borderRadius: 10,
                                border: `1px solid ${C.border}`,
                            }}
                        >
                            <span>
                                <span
                                    style={{
                                        display: 'block',
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        color: C.text,
                                    }}
                                >
                                    {employee.name}
                                    {employee.employee_number
                                        ? ` · ${employee.employee_number}`
                                        : ''}
                                </span>
                                <span
                                    style={{
                                        display: 'block',
                                        fontSize: 12,
                                        color: C.muted,
                                        marginTop: 2,
                                    }}
                                >
                                    {employee.scope_label}
                                </span>
                            </span>
                            <button
                                type="button"
                                onClick={() => remove(employee)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    padding: '7px 12px',
                                    borderRadius: 8,
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    color: C.red,
                                    fontSize: 12.5,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="trash-2" size={14} />
                                Hapus
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </>
    );

    if (embedded) {
        return content;
    }

    return <Section>{content}</Section>;
}

export default function AbsensiKebijakan({
    policy,
    attestationEnabled,
    scopeOptions,
    overrides,
    assignableEmployees,
}: PageProps) {
    const form = useForm<Policy>({ ...policy });
    const [activeTab, setActiveTab] = useState<PolicyTabKey>('area');

    // The scope an employee falls back to when they have no exception.
    const tenantScopeLabel =
        scopeOptions.find((option) => option.value === policy.attendance_scope)
            ?.label ?? policy.attendance_scope;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(AttendancePolicyController.update.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success('Kebijakan absensi tersimpan'),
        });
    };

    return (
        <>
            <Head title="Kebijakan Absensi" />

            <div style={{ padding: '28px 32px' }}>
                <div style={{ marginBottom: 22 }}>
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
                        <span>Beranda</span>
                        <AIcon name="chevron-right" size={13} />
                        <span>Kehadiran</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>
                            Kebijakan Absensi
                        </span>
                    </div>
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Kebijakan Absensi
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Atur verifikasi wajah dan keamanan perangkat saat
                        karyawan clock-in di aplikasi.
                    </div>
                </div>

                <form onSubmit={submit}>
                    <Section>
                        <div
                            role="tablist"
                            aria-label="Pengaturan kebijakan absensi"
                            style={{
                                display: 'flex',
                                gap: 10,
                                overflowX: 'auto',
                                paddingBottom: 4,
                                marginBottom: 18,
                            }}
                        >
                            {POLICY_TABS.map((tab) => {
                                const active = activeTab === tab.key;

                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => setActiveTab(tab.key)}
                                        style={{
                                            flex: '0 0 auto',
                                            minWidth: 170,
                                            padding: '12px 14px',
                                            borderRadius: 12,
                                            border: `1px solid ${
                                                active ? C.primary : C.border
                                            }`,
                                            background: active
                                                ? 'rgba(37,71,249,.06)'
                                                : '#fff',
                                            color: active ? C.primary : C.text,
                                            textAlign: 'left',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                                marginBottom: 4,
                                                fontSize: 13.5,
                                                fontWeight: 700,
                                            }}
                                        >
                                            <AIcon
                                                name={tab.icon}
                                                size={15}
                                                color={
                                                    active ? C.primary : C.muted
                                                }
                                            />
                                            {tab.label}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: active
                                                    ? C.primary
                                                    : C.muted,
                                                lineHeight: 1.45,
                                            }}
                                        >
                                            {tab.hint}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        {activeTab === 'area' && (
                            <>
                                <div style={sectionTitle}>Area Absensi</div>
                                <div style={sectionHint}>
                                    Default untuk semua karyawan. Pengecualian
                                    per orang diatur di tab Pengecualian.
                                </div>

                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 8,
                                        marginTop: 10,
                                    }}
                                >
                                    {SCOPE_OPTIONS.map((option) => (
                                        <label
                                            key={option.value}
                                            style={{
                                                display: 'flex',
                                                gap: 10,
                                                alignItems: 'flex-start',
                                                cursor: 'pointer',
                                                padding: '10px 12px',
                                                borderRadius: 10,
                                                border: `1px solid ${
                                                    form.data
                                                        .attendance_scope ===
                                                    option.value
                                                        ? C.primary
                                                        : C.border
                                                }`,
                                                background:
                                                    form.data
                                                        .attendance_scope ===
                                                    option.value
                                                        ? 'rgba(37,71,249,.04)'
                                                        : 'transparent',
                                            }}
                                        >
                                            <input
                                                type="radio"
                                                name="attendance_scope"
                                                checked={
                                                    form.data
                                                        .attendance_scope ===
                                                    option.value
                                                }
                                                onChange={() =>
                                                    form.setData(
                                                        'attendance_scope',
                                                        option.value,
                                                    )
                                                }
                                                style={{ marginTop: 3 }}
                                            />
                                            <span>
                                                <span
                                                    style={{
                                                        display: 'block',
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {option.label}
                                                </span>
                                                <span
                                                    style={{
                                                        display: 'block',
                                                        fontSize: 12,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {option.hint}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </>
                        )}

                        {activeTab === 'login' && (
                            <>
                                <div style={sectionTitle}>Keamanan Login</div>
                                <div style={sectionHint}>
                                    Atur pengikatan akun ke perangkat mobile.
                                </div>

                                <Toggle
                                    on={form.data.device_binding_enabled}
                                    onChange={(v) =>
                                        form.setData(
                                            'device_binding_enabled',
                                            v,
                                        )
                                    }
                                    label="1 Perangkat 1 Akun"
                                    hint="Akun terkunci ke HP pertama yang login. Ganti HP butuh reset perangkat oleh admin. Matikan agar bisa login dari HP mana pun."
                                />
                            </>
                        )}

                        {activeTab === 'face' && (
                            <>
                                <div style={sectionTitle}>Verifikasi Wajah</div>
                                <div style={sectionHint}>
                                    Pilih tingkat verifikasi wajah saat karyawan
                                    absen.
                                </div>

                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 8,
                                        marginTop: 4,
                                    }}
                                >
                                    {FACE_MODE_OPTIONS.map((option) => (
                                        <label
                                            key={option.value}
                                            style={{
                                                display: 'flex',
                                                gap: 10,
                                                alignItems: 'flex-start',
                                                cursor: 'pointer',
                                                padding: '10px 12px',
                                                borderRadius: 10,
                                                border: `1px solid ${
                                                    form.data.face_mode ===
                                                    option.value
                                                        ? C.primary
                                                        : C.border
                                                }`,
                                                background:
                                                    form.data.face_mode ===
                                                    option.value
                                                        ? 'rgba(37,71,249,.04)'
                                                        : 'transparent',
                                            }}
                                        >
                                            <input
                                                type="radio"
                                                name="face_mode"
                                                checked={
                                                    form.data.face_mode ===
                                                    option.value
                                                }
                                                onChange={() =>
                                                    form.setData(
                                                        'face_mode',
                                                        option.value,
                                                    )
                                                }
                                                style={{ marginTop: 3 }}
                                            />
                                            <span>
                                                <span
                                                    style={{
                                                        display: 'block',
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {option.label}
                                                </span>
                                                <span
                                                    style={{
                                                        display: 'block',
                                                        fontSize: 12,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {option.hint}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>

                                {form.data.face_mode === 'recognition' && (
                                    <div
                                        style={{
                                            marginTop: 16,
                                            paddingTop: 16,
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <Toggle
                                            on={
                                                form.data
                                                    .require_face_enrollment
                                            }
                                            onChange={(v) =>
                                                form.setData(
                                                    'require_face_enrollment',
                                                    v,
                                                )
                                            }
                                            label="Wajib daftar wajah"
                                            hint="Karyawan tidak bisa absen sebelum mendaftarkan wajah."
                                        />

                                        <div style={{ marginTop: 8 }}>
                                            <span
                                                style={{
                                                    fontSize: 13,
                                                    fontWeight: 600,
                                                    color: C.text,
                                                }}
                                            >
                                                Saat wajah tidak cocok
                                            </span>
                                            <EnforcementChoice
                                                value={
                                                    form.data.face_enforcement
                                                }
                                                onChange={(v) =>
                                                    form.setData(
                                                        'face_enforcement',
                                                        v,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {activeTab === 'device' && (
                            <>
                                <div style={sectionTitle}>
                                    Integritas Perangkat
                                </div>
                                <div style={sectionHint}>
                                    Tutup celah Fake GPS, root/jailbreak, dan
                                    emulator.
                                </div>

                                <span
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.text,
                                    }}
                                >
                                    Saat perangkat tidak tepercaya
                                </span>
                                <EnforcementChoice
                                    value={form.data.integrity_enforcement}
                                    onChange={(v) =>
                                        form.setData('integrity_enforcement', v)
                                    }
                                />

                                <div
                                    style={{
                                        marginTop: 12,
                                        borderTop: `1px solid ${C.border}`,
                                        paddingTop: 4,
                                    }}
                                >
                                    <Toggle
                                        on={form.data.block_mock_location}
                                        onChange={(v) =>
                                            form.setData(
                                                'block_mock_location',
                                                v,
                                            )
                                        }
                                        label="Deteksi lokasi palsu (Fake GPS)"
                                    />
                                    <Toggle
                                        on={form.data.block_rooted}
                                        onChange={(v) =>
                                            form.setData('block_rooted', v)
                                        }
                                        label="Deteksi root / jailbreak"
                                    />
                                    <Toggle
                                        on={form.data.block_emulator}
                                        onChange={(v) =>
                                            form.setData('block_emulator', v)
                                        }
                                        label="Deteksi emulator"
                                    />
                                </div>

                                <div
                                    style={{
                                        marginTop: 12,
                                        padding: '10px 12px',
                                        borderRadius: 8,
                                        background: attestationEnabled
                                            ? '#ECFDF5'
                                            : '#F8FAFC',
                                        fontSize: 12.5,
                                        color: C.muted,
                                        display: 'flex',
                                        gap: 8,
                                    }}
                                >
                                    <AIcon
                                        name={
                                            attestationEnabled
                                                ? 'shield-check'
                                                : 'info'
                                        }
                                        size={16}
                                    />
                                    <span>
                                        {attestationEnabled
                                            ? 'Play Integrity / App Attest aktif — token perangkat diverifikasi ke server penyedia.'
                                            : 'Verifikasi kriptografis (Play Integrity / App Attest) belum diaktifkan. Deteksi di atas memakai sinyal dari aplikasi. Aktifkan kredensial penyedia untuk verifikasi penuh.'}
                                    </span>
                                </div>
                            </>
                        )}

                        {activeTab === 'override' && (
                            <div style={{ marginTop: 2 }}>
                                <OverridePanel
                                    overrides={overrides}
                                    assignableEmployees={assignableEmployees}
                                    scopeOptions={scopeOptions}
                                    tenantScopeLabel={tenantScopeLabel}
                                    embedded
                                />
                            </div>
                        )}
                    </Section>

                    {/* Anti-Replay (nonce sekali pakai) is hidden for now: it
                        rules out offline attendance, since a nonce expires two
                        minutes after it is issued. The switch is still carried
                        in the form so whatever a tenant already has stays put
                        rather than being cleared by a save. */}

                    <button
                        type="submit"
                        style={{
                            ...btnSave,
                            opacity: form.processing ? 0.6 : 1,
                        }}
                        disabled={form.processing}
                    >
                        <AIcon name="save" size={16} />
                        Simpan Kebijakan
                    </button>
                </form>
            </div>
        </>
    );
}
