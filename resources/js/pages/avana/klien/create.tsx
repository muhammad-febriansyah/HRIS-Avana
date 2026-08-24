import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    withError,
} from './components';
import {
    BILLING_STATUS_OPTIONS,
    emptyTenantForm,
    STATUS_OPTIONS,
} from './types';
import type { FlashProps, PackageOption, TenantFormData } from './types';
import {
    applyPackage,
    CYCLE_LABELS,
    formatDate,
    formatRupiah,
    formatTokens,
    periodDays,
    periodEnd,
    STEP_FIELDS,
    today,
    TRIAL_PRESETS,
} from './wizard';

interface KlienCreateProps {
    packages: PackageOption[];
    /** Present when arriving from Referral > Leads "Jadikan Klien". */
    referralLead: {
        id: number;
        company_name: string;
        partner_code: string | null;
    } | null;
}

const STEPS = [
    {
        title: 'Paket & Langganan',
        hint: 'Pilih harga, periode dihitung otomatis',
    },
    { title: 'Data Klien', hint: 'Identitas dan kuota' },
    { title: 'Akun Admin', hint: 'Login pertama klien' },
];

export default function KlienCreate({ packages, referralLead }: KlienCreateProps) {
    const { flash } = usePage<FlashProps>().props;
    const [step, setStep] = useState(0);

    const form = useForm<TenantFormData>({
        ...emptyTenantForm,
        start_date: today(),
        end_date: periodEnd(today(), 'trial', 'monthly', '14'),
        referral_lead_id: referralLead ? String(referralLead.id) : '',
    });
    const { data, setData, errors, processing } = form;

    const selectedPackage = useMemo(
        () =>
            packages.find((pkg) => String(pkg.id) === data.package_id) ?? null,
        [packages, data.package_id],
    );

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    /** Any change to the period inputs re-derives the end date. */
    const reprice = (patch: Partial<TenantFormData>) => {
        const next = { ...data, ...patch };

        setData({
            ...next,
            end_date: periodEnd(
                next.start_date,
                next.status,
                next.billing_cycle,
                next.trial_days,
            ),
        });
    };

    const pickPackage = (pkg: PackageOption) => {
        setData({ ...data, ...applyPackage(data, pkg) });
    };

    const stepHasErrors = (index: number) =>
        STEP_FIELDS[index].some((field) => !!errors[field]);

    // Server-side validation errors send the wizard back to the step that owns them.
    useEffect(() => {
        const firstBroken = [0, 1, 2].find(stepHasErrors);

        if (firstBroken !== undefined) {
            setStep(firstBroken);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errors]);

    const stepComplete = (index: number): boolean => {
        if (index === 0) {
            return !!data.start_date && !!data.end_date;
        }

        if (index === 1) {
            return data.name.trim().length > 0;
        }

        return (
            data.admin_name.trim().length > 0 &&
            data.admin_email.trim().length > 0
        );
    };

    const handleSubmit = () => {
        form.submit(TenantController.store());
    };

    const days = periodDays(data.start_date, data.end_date);
    const isTrial = data.status === 'trial';

    // A client without a package gets no free monthly allowance at all — its AI
    // tokens can only come from a purchased pack, so say so rather than "0".
    const tokenQuotaLabel = selectedPackage?.ai_token_quota
        ? `${formatTokens(selectedPackage.ai_token_quota)} / bulan`
        : 'Beli paket token';

    return (
        <>
            <Head title="Tambah Klien" />
            <div style={{ padding: '28px 32px' }}>
                <div style={breadcrumbStyle}>
                    <Link href={TenantController.index()} style={crumbLink}>
                        Klien
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Klien</span>
                </div>
                <h1 style={titleStyle}>Tambah Klien Baru</h1>

                {referralLead && (
                    <div
                        style={{
                            marginTop: 12,
                            padding: '10px 14px',
                            borderRadius: 8,
                            background: '#EEF2FF',
                            border: `1px solid ${C.primary}33`,
                            fontSize: 13,
                            color: C.navy,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                        }}
                    >
                        <AIcon name="handshake" size={15} color={C.primary} />
                        Dari lead referral <strong>{referralLead.company_name}</strong>
                        {referralLead.partner_code
                            ? ` — mitra ${referralLead.partner_code} akan mendapat komisi begitu invoice pertama klien ini lunas.`
                            : '.'}
                    </div>
                )}

                <Stepper
                    current={step}
                    onJump={setStep}
                    complete={stepComplete}
                />

                <div style={{ ...card, marginTop: 18 }}>
                    {step === 0 && (
                        <div style={{ padding: '22px 24px' }}>
                            <SectionTitle
                                title="Paket Langganan"
                                hint="Kuota pengguna, karyawan, cabang dan token AI bulanan mengikuti paket yang dipilih."
                            />
                            <div style={packageGridStyle}>
                                {packages.map((pkg) => {
                                    const active =
                                        String(pkg.id) === data.package_id;

                                    return (
                                        <button
                                            key={pkg.id}
                                            type="button"
                                            onClick={() => pickPackage(pkg)}
                                            style={{
                                                ...packageCardStyle,
                                                borderColor: active
                                                    ? C.primary
                                                    : C.line,
                                                background: active
                                                    ? '#F5F7FF'
                                                    : '#fff',
                                                boxShadow: active
                                                    ? `0 0 0 1px ${C.primary}`
                                                    : 'none',
                                            }}
                                        >
                                            <div style={packageHeadStyle}>
                                                <span
                                                    style={{
                                                        fontSize: 14,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {pkg.name}
                                                </span>
                                                {pkg.is_popular && (
                                                    <span style={popularStyle}>
                                                        Populer
                                                    </span>
                                                )}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 17,
                                                    fontWeight: 700,
                                                    color: C.primary,
                                                    marginTop: 6,
                                                }}
                                            >
                                                {formatRupiah(pkg.price ?? 0)}
                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        fontWeight: 500,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {' '}
                                                    /{' '}
                                                    {(
                                                        CYCLE_LABELS[
                                                            pkg.billing_cycle ??
                                                                'monthly'
                                                        ] ?? 'Bulanan'
                                                    ).toLowerCase()}
                                                </span>
                                            </div>
                                            <div style={packageMetaStyle}>
                                                {pkg.max_users} pengguna ·{' '}
                                                {pkg.max_employees} karyawan ·{' '}
                                                {pkg.max_branches} cabang
                                            </div>
                                            <div style={packageTokenStyle}>
                                                <AIcon
                                                    name="sparkles"
                                                    size={12}
                                                    color={C.faint}
                                                />
                                                {pkg.ai_token_quota
                                                    ? `${formatTokens(pkg.ai_token_quota)} token AI / bulan`
                                                    : 'Tanpa token AI bulanan'}
                                            </div>
                                        </button>
                                    );
                                })}
                                <button
                                    type="button"
                                    onClick={() =>
                                        setData({
                                            ...data,
                                            ...applyPackage(data, null),
                                        })
                                    }
                                    style={{
                                        ...packageCardStyle,
                                        borderColor:
                                            data.package_id === ''
                                                ? C.primary
                                                : C.line,
                                        background:
                                            data.package_id === ''
                                                ? '#F5F7FF'
                                                : '#fff',
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 14,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        Tanpa Paket
                                    </div>
                                    <div style={packageMetaStyle}>
                                        Kuota diisi manual di langkah
                                        berikutnya.
                                    </div>
                                    <div style={packageTokenStyle}>
                                        <AIcon
                                            name="sparkles"
                                            size={12}
                                            color={C.faint}
                                        />
                                        Token AI hanya dari beli paket token
                                    </div>
                                </button>
                            </div>
                            <FieldError message={errors.package_id} />

                            <div style={{ height: 22 }} />
                            <SectionTitle
                                title="Periode Langganan"
                                hint="Tanggal selesai dihitung dari tanggal mulai — tidak perlu diisi manual."
                            />

                            <div style={twoColStyle}>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Status
                                    </label>
                                    <select
                                        value={data.status}
                                        onChange={(event) =>
                                            reprice({
                                                status: event.target.value,
                                            })
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!errors.status,
                                        )}
                                    >
                                        {STATUS_OPTIONS.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={errors.status} />
                                </div>

                                {isTrial ? (
                                    <div>
                                        <label style={fieldLabelStyle}>
                                            Durasi Trial
                                        </label>
                                        <div
                                            style={{ display: 'flex', gap: 8 }}
                                        >
                                            {TRIAL_PRESETS.map((preset) => (
                                                <button
                                                    key={preset}
                                                    type="button"
                                                    onClick={() =>
                                                        reprice({
                                                            trial_days:
                                                                String(preset),
                                                        })
                                                    }
                                                    style={{
                                                        ...chipStyle,
                                                        borderColor:
                                                            data.trial_days ===
                                                            String(preset)
                                                                ? C.primary
                                                                : C.line,
                                                        color:
                                                            data.trial_days ===
                                                            String(preset)
                                                                ? C.primary
                                                                : C.text,
                                                        background:
                                                            data.trial_days ===
                                                            String(preset)
                                                                ? '#F5F7FF'
                                                                : '#fff',
                                                    }}
                                                >
                                                    {preset} hari
                                                </button>
                                            ))}
                                            <input
                                                type="number"
                                                min={1}
                                                placeholder="cth. 30"
                                                value={data.trial_days}
                                                onChange={(event) =>
                                                    reprice({
                                                        trial_days:
                                                            event.target.value,
                                                    })
                                                }
                                                style={{
                                                    ...inputStyle,
                                                    width: 92,
                                                }}
                                            />
                                        </div>
                                        <FieldError
                                            message={errors.trial_days}
                                        />
                                    </div>
                                ) : (
                                    <div>
                                        <label style={fieldLabelStyle}>
                                            Siklus Tagihan
                                        </label>
                                        <select
                                            value={data.billing_cycle}
                                            onChange={(event) =>
                                                reprice({
                                                    billing_cycle:
                                                        event.target.value,
                                                })
                                            }
                                            style={withError(
                                                selectStyle,
                                                !!errors.billing_cycle,
                                            )}
                                        >
                                            {Object.entries(CYCLE_LABELS).map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <FieldError
                                            message={errors.billing_cycle}
                                        />
                                    </div>
                                )}

                                <div>
                                    <label style={fieldLabelStyle}>
                                        Mulai Langganan
                                    </label>
                                    <DatePicker
                                        value={data.start_date}
                                        onChange={(nextValue) =>
                                            reprice({ start_date: nextValue })
                                        }
                                        placeholder="Pilih tanggal"
                                        hasError={!!errors.start_date}
                                        width="100%"
                                    />
                                    <FieldError message={errors.start_date} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Selesai Langganan
                                    </label>
                                    <DatePicker
                                        value={data.end_date}
                                        onChange={(nextValue) =>
                                            setData('end_date', nextValue)
                                        }
                                        placeholder="Pilih tanggal"
                                        hasError={!!errors.end_date}
                                        width="100%"
                                    />
                                    <div style={helpStyle}>
                                        Terhitung otomatis. Ubah hanya bila
                                        periodenya memang khusus.
                                    </div>
                                    <FieldError message={errors.end_date} />
                                </div>
                            </div>

                            <div style={summaryStyle}>
                                <SummaryItem
                                    label="Paket"
                                    value={
                                        selectedPackage?.name ?? 'Tanpa paket'
                                    }
                                />
                                <SummaryItem
                                    label={isTrial ? 'Biaya Trial' : 'Tagihan'}
                                    value={
                                        isTrial
                                            ? 'Rp 0'
                                            : `${formatRupiah(selectedPackage?.price ?? 0)} / ${(CYCLE_LABELS[data.billing_cycle] ?? 'Bulanan').toLowerCase()}`
                                    }
                                />
                                <SummaryItem
                                    label="Periode"
                                    value={`${formatDate(data.start_date)} – ${formatDate(data.end_date)}`}
                                />
                                <SummaryItem
                                    label="Lama"
                                    value={days === null ? '—' : `${days} hari`}
                                />
                                <SummaryItem
                                    label="Token AI"
                                    value={tokenQuotaLabel}
                                />
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div style={{ padding: '22px 24px' }}>
                            <SectionTitle
                                title="Identitas Klien"
                                hint="Slug dipakai untuk sub-domain dan email admin bawaan."
                            />
                            <div style={twoColStyle}>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Nama Klien{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        value={data.name}
                                        onChange={(event) =>
                                            setData('name', event.target.value)
                                        }
                                        placeholder="PT Nusantara Jaya"
                                        style={withError(
                                            inputStyle,
                                            !!errors.name,
                                        )}
                                    />
                                    <FieldError message={errors.name} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Nama Perusahaan
                                    </label>
                                    <input
                                        value={data.company_name}
                                        onChange={(event) =>
                                            setData(
                                                'company_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="PT Nusantara Jaya Tbk"
                                        style={withError(
                                            inputStyle,
                                            !!errors.company_name,
                                        )}
                                    />
                                    <FieldError message={errors.company_name} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>Slug</label>
                                    <input
                                        value={data.slug}
                                        onChange={(event) =>
                                            setData('slug', event.target.value)
                                        }
                                        placeholder="otomatis dari nama"
                                        style={withError(
                                            inputStyle,
                                            !!errors.slug,
                                        )}
                                    />
                                    <FieldError message={errors.slug} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Status Tagihan
                                    </label>
                                    <select
                                        value={data.billing_status}
                                        onChange={(event) =>
                                            setData(
                                                'billing_status',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!errors.billing_status,
                                        )}
                                    >
                                        {BILLING_STATUS_OPTIONS.map(
                                            (option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <FieldError
                                        message={errors.billing_status}
                                    />
                                </div>
                            </div>

                            <div style={{ height: 22 }} />
                            <SectionTitle
                                title="Kuota"
                                hint={
                                    selectedPackage
                                        ? `Terisi dari paket ${selectedPackage.name}. Ubah bila klien ini dapat perlakuan khusus.`
                                        : 'Tanpa paket, isi batas pemakaian secara manual.'
                                }
                                action={
                                    selectedPackage ? (
                                        <button
                                            type="button"
                                            title={`Isi ulang ketiga kuota dengan angka bawaan paket ${selectedPackage.name}`}
                                            onClick={() =>
                                                pickPackage(selectedPackage)
                                            }
                                            style={{ ...btnOut, height: 32 }}
                                        >
                                            <AIcon
                                                name="rotate-ccw"
                                                size={14}
                                                color={C.text}
                                            />
                                            Kembalikan ke paket
                                        </button>
                                    ) : undefined
                                }
                            />
                            <div style={threeColStyle}>
                                <QuotaField
                                    label="Maks. Pengguna"
                                    value={data.max_users}
                                    onChange={(value) =>
                                        setData('max_users', value)
                                    }
                                    error={errors.max_users}
                                    packageValue={selectedPackage?.max_users}
                                />
                                <QuotaField
                                    label="Maks. Karyawan"
                                    value={data.max_employees}
                                    onChange={(value) =>
                                        setData('max_employees', value)
                                    }
                                    error={errors.max_employees}
                                    packageValue={
                                        selectedPackage?.max_employees
                                    }
                                />
                                <QuotaField
                                    label="Maks. Cabang"
                                    value={data.max_branches}
                                    onChange={(value) =>
                                        setData('max_branches', value)
                                    }
                                    error={errors.max_branches}
                                    packageValue={selectedPackage?.max_branches}
                                />
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div style={{ padding: '22px 24px' }}>
                            <SectionTitle
                                title="Akun Admin Tenant"
                                hint="Login pertama klien, otomatis memegang role Admin Tenant / HR beserta seluruh menunya. Password ditampilkan sekali setelah klien dibuat."
                            />
                            <div style={twoColStyle}>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Nama Admin{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        value={data.admin_name}
                                        onChange={(event) =>
                                            setData(
                                                'admin_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="cth. Rina Anggraeni"
                                        style={withError(
                                            inputStyle,
                                            !!errors.admin_name,
                                        )}
                                    />
                                    <FieldError message={errors.admin_name} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Email Admin{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        type="email"
                                        value={data.admin_email}
                                        onChange={(event) =>
                                            setData(
                                                'admin_email',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="admin@klien.co.id"
                                        style={withError(
                                            inputStyle,
                                            !!errors.admin_email,
                                        )}
                                    />
                                    <FieldError message={errors.admin_email} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Password
                                    </label>
                                    <input
                                        type="text"
                                        value={data.admin_password}
                                        onChange={(event) =>
                                            setData(
                                                'admin_password',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="kosongkan = dibuat otomatis"
                                        style={withError(
                                            inputStyle,
                                            !!errors.admin_password,
                                        )}
                                    />
                                    <FieldError
                                        message={errors.admin_password}
                                    />
                                </div>
                            </div>

                            <div style={{ height: 22 }} />
                            <SectionTitle title="Ringkasan" />
                            <div style={summaryStyle}>
                                <SummaryItem
                                    label="Klien"
                                    value={data.name || '—'}
                                />
                                <SummaryItem
                                    label="Paket"
                                    value={
                                        selectedPackage?.name ?? 'Tanpa paket'
                                    }
                                />
                                <SummaryItem
                                    label="Status"
                                    value={
                                        STATUS_OPTIONS.find(
                                            (option) =>
                                                option.value === data.status,
                                        )?.label ?? data.status
                                    }
                                />
                                <SummaryItem
                                    label="Periode"
                                    value={`${formatDate(data.start_date)} – ${formatDate(data.end_date)}`}
                                />
                                <SummaryItem
                                    label="Kuota"
                                    value={`${data.max_users || 0} pengguna · ${data.max_employees || 0} karyawan · ${data.max_branches || 0} cabang`}
                                />
                                <SummaryItem
                                    label="Token AI"
                                    value={tokenQuotaLabel}
                                />
                                <SummaryItem
                                    label="Admin"
                                    value={data.admin_email || '—'}
                                />
                            </div>
                        </div>
                    )}

                    <div style={footerStyle}>
                        {step === 0 ? (
                            <Link
                                href={TenantController.index()}
                                style={{
                                    ...btnOut,
                                    height: 44,
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon name="x" size={16} color={C.text} />
                                Batal
                            </Link>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setStep(step - 1)}
                                style={{ ...btnOut, height: 44 }}
                            >
                                <AIcon
                                    name="chevron-left"
                                    size={16}
                                    color={C.text}
                                />
                                Kembali
                            </button>
                        )}

                        {step < STEPS.length - 1 ? (
                            <button
                                type="button"
                                onClick={() => setStep(step + 1)}
                                disabled={!stepComplete(step)}
                                style={{
                                    ...btnP,
                                    height: 44,
                                    opacity: stepComplete(step) ? 1 : 0.55,
                                    cursor: stepComplete(step)
                                        ? 'pointer'
                                        : 'not-allowed',
                                }}
                            >
                                Lanjut
                                <AIcon
                                    name="chevron-right"
                                    size={16}
                                    color="#fff"
                                />
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={handleSubmit}
                                disabled={processing || !stepComplete(2)}
                                style={{
                                    ...btnP,
                                    height: 44,
                                    opacity:
                                        processing || !stepComplete(2)
                                            ? 0.7
                                            : 1,
                                    cursor:
                                        processing || !stepComplete(2)
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Buat Klien & Akun Admin
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function Stepper({
    current,
    onJump,
    complete,
}: {
    current: number;
    onJump: (step: number) => void;
    complete: (step: number) => boolean;
}) {
    return (
        <div style={stepperStyle}>
            {STEPS.map((item, index) => {
                const active = index === current;
                const done = index < current && complete(index);

                return (
                    <button
                        key={item.title}
                        type="button"
                        onClick={() => index <= current && onJump(index)}
                        style={{
                            ...stepItemStyle,
                            cursor: index <= current ? 'pointer' : 'default',
                            borderColor: active ? C.primary : C.line,
                            background: active ? '#F5F7FF' : '#fff',
                        }}
                    >
                        <span
                            style={{
                                ...stepBadgeStyle,
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
                        <span>
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
                                    fontSize: 12,
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

function SectionTitle({
    title,
    hint,
    action,
}: {
    title: string;
    hint?: string;
    action?: ReactNode;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'space-between',
                gap: 12,
                marginBottom: 14,
            }}
        >
            <div>
                <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                {hint && (
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.muted,
                            marginTop: 3,
                            lineHeight: 1.55,
                            maxWidth: 620,
                        }}
                    >
                        {hint}
                    </div>
                )}
            </div>
            {action}
        </div>
    );
}

function QuotaField({
    label,
    value,
    onChange,
    error,
    packageValue,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    packageValue?: number;
}) {
    const overridden =
        packageValue !== undefined && value !== String(packageValue);

    return (
        <div>
            <label style={fieldLabelStyle}>{label}</label>
            <input
                type="number"
                min={0}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder="0"
                style={withError(inputStyle, !!error)}
            />
            {packageValue !== undefined && (
                <div style={helpStyle}>
                    {overridden
                        ? `Berbeda dari paket (${packageValue})`
                        : `Sesuai paket (${packageValue})`}
                </div>
            )}
            <FieldError message={error} />
        </div>
    );
}

function SummaryItem({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div
                style={{
                    fontSize: 11.5,
                    color: C.faint,
                    textTransform: 'uppercase',
                    letterSpacing: '.04em',
                }}
            >
                {label}
            </div>
            <div
                style={{
                    fontSize: 13.5,
                    fontWeight: 600,
                    color: C.navy,
                    marginTop: 3,
                }}
            >
                {value}
            </div>
        </div>
    );
}

const breadcrumbStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 7,
    fontSize: 12.5,
    color: C.faint,
    marginBottom: 14,
};

const crumbLink: CSSProperties = {
    color: C.faint,
    textDecoration: 'none',
    cursor: 'pointer',
};

const titleStyle: CSSProperties = {
    fontSize: 24,
    fontWeight: 600,
    color: C.navy,
    margin: '0 0 20px',
    letterSpacing: '-.01em',
};

const stepperStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: 12,
};

const stepItemStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 11,
    textAlign: 'left',
    padding: '12px 14px',
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'solid',
};

const stepBadgeStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: 26,
    height: 26,
    borderRadius: 100,
    fontSize: 12.5,
    fontWeight: 700,
    flexShrink: 0,
};

const twoColStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
    gap: 16,
};

const threeColStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
    gap: 16,
};

const packageGridStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(210px, 1fr))',
    gap: 12,
};

const packageCardStyle: CSSProperties = {
    textAlign: 'left',
    padding: '14px 16px',
    borderRadius: 12,
    borderWidth: 1,
    borderStyle: 'solid',
    cursor: 'pointer',
};

const packageHeadStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
};

const popularStyle: CSSProperties = {
    fontSize: 11,
    fontWeight: 600,
    color: '#B45309',
    background: '#FEF3C7',
    padding: '2px 8px',
    borderRadius: 100,
};

const packageMetaStyle: CSSProperties = {
    fontSize: 12,
    color: C.faint,
    marginTop: 8,
    lineHeight: 1.5,
};

const packageTokenStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 5,
    fontSize: 12,
    color: C.muted,
    marginTop: 6,
    paddingTop: 6,
    borderTop: `1px solid ${C.line}`,
};

const chipStyle: CSSProperties = {
    height: 42,
    padding: '0 14px',
    borderRadius: 8,
    borderWidth: 1,
    borderStyle: 'solid',
    fontSize: 13,
    fontWeight: 500,
    cursor: 'pointer',
};

const helpStyle: CSSProperties = {
    fontSize: 11.5,
    color: C.faint,
    marginTop: 5,
};

const summaryStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
    gap: 14,
    marginTop: 18,
    padding: '15px 16px',
    borderRadius: 10,
    background: '#F8FAFF',
    border: `1px solid ${C.line}`,
};

const footerStyle: CSSProperties = {
    display: 'flex',
    gap: 10,
    justifyContent: 'space-between',
    padding: '16px 24px',
    borderTop: `1px solid ${C.line}`,
};
