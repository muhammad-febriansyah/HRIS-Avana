import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, card } from '@/lib/avana';
import {
    DEFAULT_PASSWORD,
    EMPLOYEE_STEPS,
    MARITAL_STATUSES,
    NO_MANAGER,
    RELIGIONS,
    STEP_FIELDS,
    UNASSIGNED_MANAGER,
} from './types';
import type {
    CustomFieldDef,
    EmployeeFormData,
    EmployeeFormOptions,
} from './types';

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    color: C.text,
    marginBottom: 7,
};

const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
    transition: '.15s',
};

const selectStyle: CSSProperties = { ...inputStyle, cursor: 'pointer' };

/** PTKP codes PPh 21 recognises, mirroring App\Support\Pph21Ter. */
const PTKP_OPTIONS = [
    { value: 'TK/0', label: 'TK/0 — Tidak kawin, tanpa tanggungan' },
    { value: 'TK/1', label: 'TK/1 — Tidak kawin, 1 tanggungan' },
    { value: 'TK/2', label: 'TK/2 — Tidak kawin, 2 tanggungan' },
    { value: 'TK/3', label: 'TK/3 — Tidak kawin, 3 tanggungan' },
    { value: 'K/0', label: 'K/0 — Kawin, tanpa tanggungan' },
    { value: 'K/1', label: 'K/1 — Kawin, 1 tanggungan' },
    { value: 'K/2', label: 'K/2 — Kawin, 2 tanggungan' },
    { value: 'K/3', label: 'K/3 — Kawin, 3 tanggungan' },
];

const errorBorder: CSSProperties = {
    border: `1px solid ${C.red}`,
    boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

const sectionGrid: CSSProperties = {
    padding: '20px 22px',
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '16px 20px',
};

const req = <span style={{ color: C.red }}>*</span>;

interface EmployeeFormProps {
    form: InertiaFormProps<EmployeeFormData>;
    options: EmployeeFormOptions;
    submitLabel: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    customFields?: CustomFieldDef[];
    hasLogin?: boolean;
    /**
     * A new hire must leave this form with a way in — either a password that
     * creates a login, or an existing account linked to them. Editing someone
     * who never got an account does not force one on them.
     */
    isCreate?: boolean;
}

function SectionHeader({
    icon,
    title,
    desc,
}: {
    icon: string;
    title: string;
    desc?: string;
}) {
    return (
        <div
            style={{
                padding: '18px 22px',
                borderBottom: `1px solid ${C.line}`,
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                <AIcon name={icon} size={18} color={C.primary} />
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
            </div>
            {desc ? (
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        marginTop: 3,
                        marginLeft: 27,
                    }}
                >
                    {desc}
                </div>
            ) : null}
        </div>
    );
}

function Field({
    htmlFor,
    label,
    required = false,
    fullWidth = false,
    error,
    hint,
    children,
}: {
    htmlFor: string;
    label: string;
    required?: boolean;
    fullWidth?: boolean;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div style={fullWidth ? { gridColumn: '1/-1' } : undefined}>
            <label htmlFor={htmlFor} style={labelStyle}>
                {label} {required ? req : null}
            </label>
            {children}
            {hint && !error ? (
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                    {hint}
                </div>
            ) : null}
            {error ? (
                <div style={errorTextStyle}>
                    <AIcon name="circle-alert" size={13} color={C.red} />
                    {error}
                </div>
            ) : null}
        </div>
    );
}

/**
 * Shared employee create/edit form rendered with the AvanaHR prototype markup.
 * The parent owns the Inertia form instance and submits to the right route.
 */
export function EmployeeForm({
    form,
    options,
    submitLabel,
    cancelHref,
    onSubmit,
    customFields = [],
    hasLogin = false,
    isCreate = false,
}: EmployeeFormProps) {
    const { data, setData, errors, processing } = form;
    const [step, setStep] = useState(0);
    const [showPassword, setShowPassword] = useState(false);

    /**
     * When the wizard last moved. Lanjut and Simpan are the same button in the
     * same spot — the last Lanjut turns into Simpan under the cursor — so the
     * second half of a double-click, or one impatient extra click, used to save
     * a form the admin had not finished reading. A submit that arrives right
     * after a step change is that stray click, not an intent to save.
     */
    const movedAt = useRef(0);

    const goToStep = (next: number) => {
        movedAt.current = Date.now();
        setStep(next);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        // Roughly the platform double-click window.
        if (Date.now() - movedAt.current < 500) {
            event.preventDefault();

            return;
        }

        onSubmit(event);
    };

    // Borrowing an existing account and minting a new one are alternatives, so
    // picking one closes the other's field.
    const isLinking = data.link_user_id !== '';

    // The custom-field step only exists when the tenant defined any.
    const steps = EMPLOYEE_STEPS.filter(
        (_, index) => index !== 3 || customFields.length > 0,
    );
    const lastStep = steps.length - 1;

    const stepHasErrors = (index: number): boolean =>
        STEP_FIELDS[index].some((field) =>
            Object.keys(errors).some(
                (key) => key === field || key.startsWith(`${field}.`),
            ),
        );

    // Server-side validation sends the wizard back to the step that owns the
    // first broken field, otherwise the message would sit on an unseen page.
    // Adjusted during render rather than in an effect, so the correct step
    // paints straight away instead of flashing the wrong one first.
    const errorKey = Object.keys(errors).sort().join(',');
    const [seenErrorKey, setSeenErrorKey] = useState(errorKey);

    if (errorKey !== seenErrorKey) {
        setSeenErrorKey(errorKey);

        const broken = steps.findIndex((_, index) => stepHasErrors(index));

        if (broken !== -1) {
            setStep(broken);
        }
    }

    /**
     * Required fields a step is still missing, by their on-screen label.
     *
     * Named rather than merely counted: a greyed-out Lanjut with nothing to
     * explain it leaves the person scanning the form for whichever box is
     * empty — the Direktur case hit exactly that, since Status Kepegawaian sits
     * below the fold on a short window.
     */
    const missingFor = (index: number): string[] => {
        const gaps = (pairs: [string, string][]): string[] =>
            pairs
                .filter(([value]) => String(value ?? '').trim().length === 0)
                .map(([, label]) => label);

        if (index === 0) {
            return gaps([
                [data.full_name, 'Nama Lengkap'],
                [data.nik, 'NIK (KTP)'],
                [data.email, 'Email'],
                [data.phone, 'No. Telepon'],
                [data.birth_place, 'Tempat Lahir'],
                [data.birth_date, 'Tanggal Lahir'],
                [data.gender, 'Jenis Kelamin'],
                [data.religion, 'Agama'],
                [data.marital_status, 'Status Pernikahan'],
            ]);
        }

        if (index === 1) {
            return gaps([
                [data.manager_id, 'Atasan Langsung'],
                [data.employment_status, 'Status Kepegawaian'],
                [data.department_id, 'Departemen'],
                [data.position_id, 'Jabatan'],
                [data.job_level_id, 'Jenjang Jabatan'],
                [data.salary_master_id, 'Master Gaji'],
                [data.contract_number, 'Nomor Kontrak'],
                [data.contract_type, 'Jenis Kontrak'],
                [data.contract_start_date, 'Kontrak Mulai'],
                // A PKWTT runs until the employee leaves, so it has no end date.
                ...(data.contract_type === 'pkwtt'
                    ? []
                    : ([[data.contract_end_date, 'Kontrak Berakhir']] as [
                          string,
                          string,
                      ][])),
                [data.ptkp_status, 'Status PTKP'],
                [data.join_date, 'Tanggal Masuk'],
                [data.branch_id, 'Cabang'],
                [data.work_location_id, 'Lokasi Kerja'],
                [data.status, 'Status Karyawan'],
            ]);
        }

        return [];
    };

    /** Whether a step has everything it needs to move on. */
    const stepComplete = (index: number): boolean =>
        missingFor(index).length === 0;

    // The last step's Simpan is gated by step 2, so it reports step 2's gaps.
    const missing = missingFor(step < lastStep ? step : 1);

    const styleFor = (hasError: boolean, base: CSSProperties): CSSProperties =>
        hasError ? { ...base, ...errorBorder } : base;

    // Only offer work locations that belong to the chosen branch (all of them
    // until a branch is picked).
    const availableWorkLocations = options.workLocations.filter(
        (location) =>
            !data.branch_id ||
            String(location.branch_id ?? '') === data.branch_id,
    );

    const setCustom = (key: string, value: string) =>
        setData('custom_data', { ...(data.custom_data ?? {}), [key]: value });

    return (
        <form
            onSubmit={handleSubmit}
            style={{ display: 'flex', flexDirection: 'column', gap: 18 }}
        >
            <Stepper
                steps={steps}
                current={step}
                onJump={goToStep}
                complete={stepComplete}
            />

            {step === 0 && (
                <div style={card}>
                    <SectionHeader
                        icon="user"
                        title="Data Personal"
                        desc="Identitas dasar karyawan sesuai KTP."
                    />
                    <div className="avn-2col" style={sectionGrid}>
                        <Field
                            htmlFor="full_name"
                            label="Nama Lengkap"
                            required
                            error={errors.full_name}
                        >
                            <input
                                id="full_name"
                                value={data.full_name}
                                onChange={(event) =>
                                    setData('full_name', event.target.value)
                                }
                                placeholder="Masukkan nama sesuai KTP"
                                style={styleFor(!!errors.full_name, inputStyle)}
                            />
                        </Field>

                        <Field
                            htmlFor="nik"
                            label="NIK (KTP)"
                            required
                            error={errors.nik}
                        >
                            <input
                                id="nik"
                                inputMode="numeric"
                                maxLength={16}
                                value={data.nik}
                                onChange={(event) =>
                                    setData('nik', event.target.value)
                                }
                                placeholder="16 digit NIK"
                                style={styleFor(!!errors.nik, inputStyle)}
                            />
                        </Field>

                        <Field
                            htmlFor="email"
                            label="Email"
                            required
                            error={errors.email}
                        >
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData('email', event.target.value)
                                }
                                placeholder="nama@perusahaan.co.id"
                                style={styleFor(!!errors.email, inputStyle)}
                            />
                        </Field>

                        <Field
                            htmlFor="password"
                            label={
                                hasLogin ? 'Reset Password' : 'Password Login'
                            }
                            // On an employee who has no login yet, a password is
                            // what creates one — unless an existing account is
                            // linked below instead.
                            required={isCreate && !data.link_user_id}
                            error={errors.password}
                        >
                            <div style={{ display: 'flex', gap: 8 }}>
                                <div
                                    style={{
                                        position: 'relative',
                                        flex: 1,
                                        minWidth: 0,
                                    }}
                                >
                                    <input
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        autoComplete="new-password"
                                        value={data.password}
                                        // A password here makes a new account,
                                        // which would contradict the account
                                        // being borrowed on the Akun step.
                                        disabled={isLinking}
                                        onChange={(event) =>
                                            setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Min. 8 karakter"
                                        style={{
                                            ...styleFor(
                                                !!errors.password,
                                                inputStyle,
                                            ),
                                            paddingRight: 40,
                                            background: isLinking
                                                ? C.surface
                                                : '#fff',
                                        }}
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword(!showPassword)
                                        }
                                        // The field is a control, not a target:
                                        // tabbing should land on the next input.
                                        tabIndex={-1}
                                        title={
                                            showPassword
                                                ? 'Sembunyikan password'
                                                : 'Tampilkan password'
                                        }
                                        aria-label={
                                            showPassword
                                                ? 'Sembunyikan password'
                                                : 'Tampilkan password'
                                        }
                                        style={{
                                            position: 'absolute',
                                            top: 0,
                                            right: 0,
                                            height: 42,
                                            width: 38,
                                            display: 'grid',
                                            placeItems: 'center',
                                            border: 'none',
                                            background: 'transparent',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <AIcon
                                            name={
                                                showPassword ? 'eye-off' : 'eye'
                                            }
                                            size={16}
                                            color={C.faint}
                                        />
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    disabled={isLinking}
                                    onClick={() =>
                                        setData('password', DEFAULT_PASSWORD)
                                    }
                                    title={`Isi dengan "${DEFAULT_PASSWORD}"`}
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 6,
                                        flex: 'none',
                                        height: 42,
                                        padding: '0 13px',
                                        borderRadius: 8,
                                        border: '1px solid rgba(47,84,201,.35)',
                                        background: 'rgba(47,84,201,.07)',
                                        color: C.primary,
                                        fontSize: 12.5,
                                        fontWeight: 600,
                                        cursor: isLinking
                                            ? 'not-allowed'
                                            : 'pointer',
                                        opacity: isLinking ? 0.5 : 1,
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    <AIcon
                                        name="wand-sparkles"
                                        size={14}
                                        color={C.primary}
                                    />
                                    Password Default
                                </button>
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.muted,
                                    marginTop: 6,
                                }}
                            >
                                {isLinking
                                    ? 'Akun yang ditautkan memakai passwordnya sendiri. Hapus tautan di langkah Akun & Akses bila ingin membuat akun baru.'
                                    : hasLogin
                                      ? 'Kosongkan bila password tidak diganti.'
                                      : `Kosongkan bila karyawan belum perlu akun mobile. Rekomendasi: ${DEFAULT_PASSWORD} — minta karyawan menggantinya setelah login pertama.`}
                            </div>
                        </Field>

                        <Field
                            htmlFor="phone"
                            label="No. Telepon"
                            required
                            error={errors.phone}
                        >
                            <input
                                id="phone"
                                value={data.phone}
                                onChange={(event) =>
                                    setData('phone', event.target.value)
                                }
                                placeholder="08xx-xxxx-xxxx"
                                style={styleFor(!!errors.phone, inputStyle)}
                            />
                        </Field>

                        <Field
                            htmlFor="birth_place"
                            label="Tempat Lahir"
                            required
                            error={errors.birth_place}
                        >
                            <input
                                id="birth_place"
                                value={data.birth_place}
                                onChange={(event) =>
                                    setData('birth_place', event.target.value)
                                }
                                placeholder="cth. Jakarta"
                                style={styleFor(
                                    !!errors.birth_place,
                                    inputStyle,
                                )}
                            />
                        </Field>

                        <Field
                            htmlFor="birth_date"
                            label="Tanggal Lahir"
                            required
                            error={errors.birth_date}
                        >
                            <DatePicker
                                value={data.birth_date}
                                onChange={(nextValue) =>
                                    setData('birth_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                        </Field>

                        <Field
                            htmlFor="gender"
                            label="Jenis Kelamin"
                            required
                            error={errors.gender}
                        >
                            <select
                                id="gender"
                                value={data.gender}
                                onChange={(event) =>
                                    setData('gender', event.target.value)
                                }
                                style={styleFor(!!errors.gender, selectStyle)}
                            >
                                <option value="">Pilih jenis kelamin</option>
                                {options.genders.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="religion"
                            label="Agama"
                            required
                            error={errors.religion}
                        >
                            <select
                                id="religion"
                                value={data.religion}
                                onChange={(event) =>
                                    setData('religion', event.target.value)
                                }
                                style={styleFor(!!errors.religion, selectStyle)}
                            >
                                <option value="">Pilih agama</option>
                                {RELIGIONS.map((religion) => (
                                    <option key={religion} value={religion}>
                                        {religion}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="marital_status"
                            label="Status Pernikahan"
                            required
                            error={errors.marital_status}
                        >
                            <select
                                id="marital_status"
                                value={data.marital_status}
                                onChange={(event) =>
                                    setData(
                                        'marital_status',
                                        event.target.value,
                                    )
                                }
                                style={styleFor(
                                    !!errors.marital_status,
                                    selectStyle,
                                )}
                            >
                                <option value="">
                                    Pilih status pernikahan
                                </option>
                                {MARITAL_STATUSES.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="address"
                            label="Alamat Domisili"
                            fullWidth
                            error={errors.address}
                        >
                            <textarea
                                id="address"
                                rows={2}
                                value={data.address}
                                onChange={(event) =>
                                    setData('address', event.target.value)
                                }
                                placeholder="Alamat lengkap tempat tinggal"
                                style={styleFor(!!errors.address, {
                                    ...inputStyle,
                                    height: undefined,
                                    padding: '11px 13px',
                                    resize: 'vertical',
                                })}
                            />
                        </Field>
                    </div>
                </div>
            )}

            {step === 2 && (
                <div style={card}>
                    <SectionHeader
                        icon="smartphone"
                        title="Akun Aplikasi Mobile & Hak Akses"
                        desc={
                            hasLogin
                                ? 'Karyawan sudah punya akun login. Isi password untuk reset, atau ganti role untuk mengubah hak aksesnya.'
                                : 'Isi password untuk membuatkan akun login (pakai email di atas). Role menentukan hak akses menu — pilih agar karyawan langsung mewarisi akses role tersebut.'
                        }
                    />
                    <div className="avn-2col" style={sectionGrid}>
                        <Field
                            htmlFor="role_id"
                            // Creating a login means picking a role: the company
                            // names its own roles, so there is no default one to
                            // fall back on.
                            label={
                                hasLogin
                                    ? 'Role / Hak Akses'
                                    : 'Role / Hak Akses *'
                            }
                            error={errors.role_id}
                        >
                            <select
                                id="role_id"
                                value={data.role_id}
                                onChange={(event) =>
                                    setData('role_id', event.target.value)
                                }
                                style={styleFor(!!errors.role_id, selectStyle)}
                            >
                                <option value="">
                                    {hasLogin
                                        ? '— Biarkan role saat ini —'
                                        : '— pilih peran —'}
                                </option>
                                {options.roles.map((role) => (
                                    <option
                                        key={role.id}
                                        value={String(role.id)}
                                    >
                                        {role.name}
                                        {role.can_access_mobile === false
                                            ? ' — tanpa aplikasi mobile'
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {/* An account made outside this form — an HR or finance
                            login — has no employee behind it, so the mobile app
                            answers 403 on its own profile and absensi. Attaching
                            it here is what turns it into this person's account,
                            instead of running a second login for the same human. */}
                        {!hasLogin && options.linkableUsers.length > 0 ? (
                            <Field
                                htmlFor="link_user_id"
                                label="Tautkan Akun yang Sudah Ada"
                                fullWidth
                                error={errors.link_user_id}
                                hint="Untuk akun yang dibuat di luar form ini (mis. admin HR). Tanpa tautan, akun itu bisa login tapi ditolak di menu absensi, cuti dan profil aplikasi mobile."
                            >
                                <SearchableSelect
                                    value={data.link_user_id}
                                    onChange={(next) =>
                                        setData('link_user_id', next)
                                    }
                                    options={options.linkableUsers.map(
                                        (user) => ({
                                            value: String(user.id),
                                            label: [
                                                user.name,
                                                user.email,
                                                user.roles || null,
                                            ]
                                                .filter(Boolean)
                                                .join(' — '),
                                        }),
                                    )}
                                    placeholder="Tidak menautkan akun"
                                    searchPlaceholder="Cari nama atau email…"
                                    allowClear
                                    style={styleFor(
                                        !!errors.link_user_id,
                                        selectStyle,
                                    )}
                                />
                            </Field>
                        ) : null}
                        {hasLogin ? (
                            <div
                                style={{
                                    gridColumn: '1/-1',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    fontSize: 12.5,
                                    color: '#059669',
                                }}
                            >
                                <AIcon
                                    name="badge-check"
                                    size={16}
                                    color="#059669"
                                />
                                Akun login aktif
                            </div>
                        ) : null}
                    </div>
                </div>
            )}

            {step === 1 && (
                <div style={card}>
                    <SectionHeader
                        icon="briefcase"
                        title="Kepegawaian"
                        desc="Posisi & detail kontrak kerja."
                    />
                    <div className="avn-2col" style={sectionGrid}>
                        <Field
                            htmlFor="department_id"
                            label="Departemen"
                            required
                            error={errors.department_id}
                        >
                            <select
                                id="department_id"
                                value={data.department_id}
                                onChange={(event) =>
                                    setData('department_id', event.target.value)
                                }
                                style={styleFor(
                                    !!errors.department_id,
                                    selectStyle,
                                )}
                            >
                                <option value="">Pilih departemen</option>
                                {options.departments.map((department) => (
                                    <option
                                        key={department.id}
                                        value={String(department.id)}
                                    >
                                        {department.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="position_id"
                            label="Jabatan"
                            required
                            error={errors.position_id}
                        >
                            <select
                                id="position_id"
                                value={data.position_id}
                                onChange={(event) =>
                                    setData('position_id', event.target.value)
                                }
                                style={styleFor(
                                    !!errors.position_id,
                                    selectStyle,
                                )}
                            >
                                <option value="">Pilih jabatan</option>
                                {options.positions.map((position) => (
                                    <option
                                        key={position.id}
                                        value={String(position.id)}
                                    >
                                        {position.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="job_level_id"
                            label="Jenjang Jabatan"
                            required
                            error={errors.job_level_id}
                        >
                            <select
                                id="job_level_id"
                                value={data.job_level_id}
                                onChange={(event) =>
                                    setData('job_level_id', event.target.value)
                                }
                                style={styleFor(
                                    !!errors.job_level_id,
                                    selectStyle,
                                )}
                            >
                                <option value="">Pilih jenjang</option>
                                {options.jobLevels.map((level) => (
                                    <option
                                        key={level.id}
                                        value={String(level.id)}
                                    >
                                        {level.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        {/* One control decides the reporting line. Picking the
                        "Tidak ada" entry is what marks an approver puncak —
                        leaving the field empty just means "not filled in yet",
                        which is why the two are not the same choice. */}
                        <Field
                            htmlFor="manager_id"
                            label="Atasan Langsung"
                            required
                            fullWidth
                            error={errors.manager_id}
                        >
                            <SearchableSelect
                                value={data.manager_id}
                                onChange={(value) =>
                                    setData('manager_id', value)
                                }
                                options={[
                                    {
                                        value: UNASSIGNED_MANAGER,
                                        label: 'Belum ditentukan — atur setelah atasannya ada',
                                    },
                                    {
                                        value: NO_MANAGER,
                                        label: 'Tidak ada — Approver Puncak (Direktur / Direksi)',
                                    },
                                    ...options.managers.map((manager) => ({
                                        value: String(manager.id),
                                        label: `${manager.name} (${manager.employee_number})`,
                                    })),
                                ]}
                                placeholder="Pilih atasan"
                                searchPlaceholder="Cari nama karyawan…"
                                style={styleFor(
                                    !!errors.manager_id,
                                    selectStyle,
                                )}
                            />
                            {data.manager_id === NO_MANAGER && (
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: 9,
                                        marginTop: 9,
                                        padding: '11px 13px',
                                        border: '1px solid rgba(220,38,38,.35)',
                                        background: 'rgba(220,38,38,.06)',
                                        borderRadius: 9,
                                    }}
                                >
                                    <AIcon
                                        name="triangle-alert"
                                        size={15}
                                        color={C.red}
                                    />
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.text,
                                            lineHeight: 1.55,
                                        }}
                                    >
                                        Tanpa atasan, pengajuan cuti, lembur,
                                        dan reimbursement karyawan ini{' '}
                                        <strong>langsung disetujui</strong>{' '}
                                        tanpa diperiksa siapa pun. Pilih ini
                                        hanya untuk direksi.
                                    </div>
                                </div>
                            )}
                            {data.manager_id === UNASSIGNED_MANAGER && (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        lineHeight: 1.55,
                                        marginTop: 9,
                                    }}
                                >
                                    Pengajuan karyawan ini menunggu sampai
                                    atasannya diisi — tidak disetujui otomatis.
                                </div>
                            )}
                        </Field>

                        <Field
                            htmlFor="employment_status"
                            label="Status Kepegawaian"
                            required
                            error={errors.employment_status}
                        >
                            <select
                                id="employment_status"
                                value={data.employment_status}
                                onChange={(event) =>
                                    setData(
                                        'employment_status',
                                        event.target.value,
                                    )
                                }
                                style={styleFor(
                                    !!errors.employment_status,
                                    selectStyle,
                                )}
                            >
                                <option value="">Pilih status</option>
                                {options.employmentStatuses.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="contract_number"
                            label="Nomor Kontrak"
                            required
                            error={errors.contract_number}
                        >
                            <input
                                id="contract_number"
                                value={data.contract_number}
                                onChange={(event) =>
                                    setData('contract_number', event.target.value)
                                }
                                placeholder="mis. PKWT-2026-001"
                                style={styleFor(
                                    !!errors.contract_number,
                                    inputStyle,
                                )}
                            />
                        </Field>

                        <Field
                            htmlFor="contract_type"
                            label="Jenis Kontrak"
                            required
                            error={errors.contract_type}
                        >
                            <select
                                id="contract_type"
                                value={data.contract_type}
                                onChange={(event) =>
                                    setData('contract_type', event.target.value)
                                }
                                style={styleFor(
                                    !!errors.contract_type,
                                    selectStyle,
                                )}
                            >
                                <option value="">Pilih jenis kontrak…</option>
                                {options.contractTypes.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="contract_start_date"
                            label="Kontrak Mulai"
                            required
                            error={errors.contract_start_date}
                        >
                            <DatePicker
                                value={data.contract_start_date}
                                onChange={(nextValue) =>
                                    setData('contract_start_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                        </Field>

                        <Field
                            htmlFor="contract_end_date"
                            label="Kontrak Berakhir"
                            // A PKWTT has no end date to type.
                            required={data.contract_type !== 'pkwtt'}
                            error={errors.contract_end_date}
                        >
                            <DatePicker
                                value={data.contract_end_date}
                                onChange={(nextValue) =>
                                    setData('contract_end_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                        </Field>

                        <Field
                            htmlFor="salary_master_id"
                            label="Master Gaji"
                            required
                            error={errors.salary_master_id}
                        >
                            <select
                                id="salary_master_id"
                                value={data.salary_master_id}
                                onChange={(event) =>
                                    setData(
                                        'salary_master_id',
                                        event.target.value,
                                    )
                                }
                                style={styleFor(
                                    !!errors.salary_master_id,
                                    selectStyle,
                                )}
                            >
                                <option value="">Belum ditempel</option>
                                {options.salaryMasters.map((master) => (
                                    <option
                                        key={master.id}
                                        value={String(master.id)}
                                    >
                                        {master.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="bpjs_ketenagakerjaan_number"
                            label="No. BPJS Ketenagakerjaan"
                            error={errors.bpjs_ketenagakerjaan_number}
                        >
                            <input
                                id="bpjs_ketenagakerjaan_number"
                                value={data.bpjs_ketenagakerjaan_number}
                                onChange={(event) =>
                                    setData(
                                        'bpjs_ketenagakerjaan_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="mis. 21012345678"
                                style={styleFor(
                                    !!errors.bpjs_ketenagakerjaan_number,
                                    inputStyle,
                                )}
                            />
                        </Field>

                        <Field
                            htmlFor="bpjs_kesehatan_number"
                            label="No. BPJS Kesehatan"
                            error={errors.bpjs_kesehatan_number}
                        >
                            <input
                                id="bpjs_kesehatan_number"
                                value={data.bpjs_kesehatan_number}
                                onChange={(event) =>
                                    setData(
                                        'bpjs_kesehatan_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="mis. 0001234567890"
                                style={styleFor(
                                    !!errors.bpjs_kesehatan_number,
                                    inputStyle,
                                )}
                            />
                        </Field>

                        <Field
                            htmlFor="ptkp_status"
                            label="Status PTKP"
                            required
                            error={errors.ptkp_status}
                            hint="Dasar perhitungan PPh 21 — tanpa ini payroll memakai TK/0."
                        >
                            <select
                                id="ptkp_status"
                                value={data.ptkp_status}
                                onChange={(event) =>
                                    setData('ptkp_status', event.target.value)
                                }
                                style={styleFor(
                                    !!errors.ptkp_status,
                                    selectStyle,
                                )}
                            >
                                <option value="">Belum ditentukan</option>
                                {PTKP_OPTIONS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="join_date"
                            label="Tanggal Masuk"
                            required
                            error={errors.join_date}
                        >
                            <DatePicker
                                value={data.join_date}
                                onChange={(nextValue) =>
                                    setData('join_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                        </Field>

                        <Field
                            htmlFor="branch_id"
                            label="Cabang"
                            required
                            error={errors.branch_id}
                        >
                            <select
                                id="branch_id"
                                value={data.branch_id}
                                onChange={(event) => {
                                    setData('branch_id', event.target.value);
                                    // A work location belongs to one branch; drop
                                    // the selection when the branch changes.
                                    setData('work_location_id', '');
                                }}
                                style={styleFor(
                                    !!errors.branch_id,
                                    selectStyle,
                                )}
                            >
                                <option value="">Pilih cabang</option>
                                {options.branches.map((branch) => (
                                    <option
                                        key={branch.id}
                                        value={String(branch.id)}
                                    >
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="work_location_id"
                            label="Lokasi Kerja (Absensi)"
                            required
                            error={errors.work_location_id}
                        >
                            <select
                                id="work_location_id"
                                value={data.work_location_id}
                                onChange={(event) =>
                                    setData(
                                        'work_location_id',
                                        event.target.value,
                                    )
                                }
                                style={styleFor(
                                    !!errors.work_location_id,
                                    selectStyle,
                                )}
                            >
                                <option value="">Otomatis (ikut cabang)</option>
                                {availableWorkLocations.map((location) => (
                                    <option
                                        key={location.id}
                                        value={String(location.id)}
                                    >
                                        {location.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            htmlFor="status"
                            label="Status Karyawan"
                            required
                            error={errors.status}
                        >
                            <select
                                id="status"
                                value={data.status}
                                onChange={(event) =>
                                    setData('status', event.target.value)
                                }
                                style={styleFor(!!errors.status, selectStyle)}
                            >
                                {options.statuses.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                </div>
            )}

            {/* Data Tambahan (custom fields per tenant) */}
            {step === 3 && customFields.length > 0 && (
                <div style={card}>
                    <SectionHeader
                        icon="list-plus"
                        title="Data Tambahan"
                        desc="Field kustom sesuai kebutuhan perusahaan."
                    />
                    <div className="avn-2col" style={sectionGrid}>
                        {customFields.map((field) => {
                            const error = (errors as Record<string, string>)[
                                `custom_data.${field.key}`
                            ];
                            const value = data.custom_data?.[field.key] ?? '';

                            return (
                                <Field
                                    key={field.key}
                                    htmlFor={`cf_${field.key}`}
                                    label={field.label}
                                    required={field.is_required}
                                    error={error}
                                >
                                    {field.type === 'select' ? (
                                        <select
                                            id={`cf_${field.key}`}
                                            value={value}
                                            onChange={(event) =>
                                                setCustom(
                                                    field.key,
                                                    event.target.value,
                                                )
                                            }
                                            style={styleFor(
                                                !!error,
                                                selectStyle,
                                            )}
                                        >
                                            <option value="">— Pilih —</option>
                                            {field.options.map((opt) => (
                                                <option key={opt} value={opt}>
                                                    {opt}
                                                </option>
                                            ))}
                                        </select>
                                    ) : field.type === 'date' ? (
                                        // Same picker as Tanggal Lahir, so a
                                        // custom date field does not fall back
                                        // to the browser's native control.
                                        <DatePicker
                                            value={value}
                                            onChange={(next) =>
                                                setCustom(field.key, next)
                                            }
                                            placeholder="Pilih tanggal"
                                            hasError={!!error}
                                            width="100%"
                                        />
                                    ) : (
                                        <input
                                            id={`cf_${field.key}`}
                                            type={
                                                field.type === 'number'
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            value={value}
                                            onChange={(event) =>
                                                setCustom(
                                                    field.key,
                                                    event.target.value,
                                                )
                                            }
                                            placeholder={
                                                field.type === 'date'
                                                    ? undefined
                                                    : `Masukkan ${field.label.toLowerCase()}`
                                            }
                                            style={styleFor(
                                                !!error,
                                                inputStyle,
                                            )}
                                        />
                                    )}
                                </Field>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Footer */}
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    gap: 10,
                    padding: '4px 0 8px',
                    position: 'sticky',
                    bottom: 0,
                }}
            >
                <Link
                    href={cancelHref}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 8,
                        height: 42,
                        padding: '0 18px',
                        background: '#fff',
                        color: C.text,
                        border: `1px solid ${C.border}`,
                        borderRadius: 8,
                        fontSize: 13.5,
                        fontWeight: 500,
                        cursor: 'pointer',
                        textDecoration: 'none',
                        transition: '.15s',
                    }}
                >
                    <AIcon name="x" size={16} />
                    Batal
                </Link>

                {step > 0 && (
                    <button
                        type="button"
                        onClick={() => goToStep(step - 1)}
                        style={navButtonStyle('#fff', C.text)}
                    >
                        <AIcon name="chevron-left" size={16} color={C.text} />
                        Kembali
                    </button>
                )}

                {missing.length > 0 && (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            marginRight: 'auto',
                            fontSize: 12.5,
                            color: C.amber,
                        }}
                    >
                        <AIcon name="circle-alert" size={14} color={C.amber} />
                        Lengkapi dulu: {missing.join(', ')}
                    </span>
                )}

                {step < lastStep ? (
                    <button
                        // Keyed apart from Simpan so React swaps the node
                        // instead of quietly turning this one into a submit.
                        key="wizard-next"
                        type="button"
                        onClick={() => goToStep(step + 1)}
                        disabled={!stepComplete(step)}
                        style={{
                            ...navButtonStyle(C.primary, '#fff'),
                            opacity: stepComplete(step) ? 1 : 0.55,
                            cursor: stepComplete(step)
                                ? 'pointer'
                                : 'not-allowed',
                        }}
                    >
                        Lanjut
                        <AIcon name="chevron-right" size={16} color="#fff" />
                    </button>
                ) : (
                    <button
                        key="wizard-submit"
                        type="submit"
                        disabled={processing || !stepComplete(1)}
                        style={{
                            ...navButtonStyle(C.green, '#fff'),
                            opacity: processing || !stepComplete(1) ? 0.6 : 1,
                            cursor:
                                processing || !stepComplete(1)
                                    ? 'not-allowed'
                                    : 'pointer',
                        }}
                    >
                        <AIcon name="save" size={16} color="#fff" />
                        {submitLabel}
                    </button>
                )}
            </div>
        </form>
    );
}

/** Shared shape for the wizard's Kembali / Lanjut / Simpan buttons. */
function navButtonStyle(background: string, color: string): CSSProperties {
    return {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8,
        height: 42,
        padding: '0 20px',
        background,
        color,
        border: background === '#fff' ? `1px solid ${C.border}` : 'none',
        borderRadius: 8,
        fontSize: 13.5,
        fontWeight: 600,
        cursor: 'pointer',
        transition: '.15s',
    };
}

/**
 * Progress rail above the form. Completed steps stay clickable so the admin can
 * jump back and fix something without losing what they already typed.
 */
function Stepper({
    steps,
    current,
    onJump,
    complete,
}: {
    steps: readonly { title: string; hint: string }[];
    current: number;
    onJump: (step: number) => void;
    complete: (step: number) => boolean;
}) {
    return (
        <div
            className="avn-2col"
            style={{
                display: 'grid',
                gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))`,
                gap: 10,
            }}
        >
            {steps.map((item, index) => {
                const active = index === current;
                const done = index < current && complete(index);

                return (
                    <button
                        key={item.title}
                        type="button"
                        onClick={() => index <= current && onJump(index)}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            padding: '12px 14px',
                            textAlign: 'left',
                            border: `1px solid ${active ? C.primary : C.line}`,
                            borderRadius: 10,
                            background: active ? '#F5F7FF' : '#fff',
                            cursor: index <= current ? 'pointer' : 'default',
                            transition: '.15s',
                        }}
                    >
                        <span
                            style={{
                                display: 'grid',
                                placeItems: 'center',
                                width: 26,
                                height: 26,
                                flex: 'none',
                                borderRadius: '50%',
                                fontSize: 12.5,
                                fontWeight: 700,
                                background: done
                                    ? C.green
                                    : active
                                      ? C.primary
                                      : C.surface,
                                color: done || active ? '#fff' : C.faint,
                            }}
                        >
                            {done ? '✓' : index + 1}
                        </span>
                        <span style={{ minWidth: 0 }}>
                            <span
                                style={{
                                    display: 'block',
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: active ? C.navy : C.text,
                                }}
                            >
                                {item.title}
                            </span>
                            <span
                                style={{
                                    display: 'block',
                                    fontSize: 11.5,
                                    color: C.faint,
                                    marginTop: 2,
                                }}
                            >
                                {item.hint}
                            </span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

export default EmployeeForm;
