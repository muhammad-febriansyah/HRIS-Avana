import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import CompanySetupController from '@/actions/App/Http/Controllers/Avana/CompanySetupController';
import CompanySwitcherController from '@/actions/App/Http/Controllers/Avana/CompanySwitcherController';
import MultiCompanyController from '@/actions/App/Http/Controllers/Avana/MultiCompanyController';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AIcon, C, card, hexA } from '@/lib/avana';

type Company = {
    id: number;
    name: string;
    company_name: string | null;
    status: string;
    is_primary: boolean;
    employees_count: number;
    branches_count: number;
    is_current: boolean;
};

type Props = {
    group: { id: number; name: string };
    entitlement: {
        enabled: boolean;
        company_limit: number;
        used: number;
        remaining: number;
    };
    companies: Company[];
};

type Step = 1 | 2;

const inputStyle: CSSProperties = {
    width: '100%',
    minHeight: 46,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 9,
    outline: 'none',
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    transition: 'border-color .18s ease, box-shadow .18s ease',
};

const ghostButton: CSSProperties = {
    minHeight: 44,
    border: `1px solid ${C.border}`,
    borderRadius: 9,
    padding: '0 15px',
    background: '#fff',
    color: C.text,
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    transition:
        'transform .18s ease, border-color .18s ease, background .18s ease',
};

const primaryButton: CSSProperties = {
    minHeight: 44,
    border: 'none',
    borderRadius: 9,
    padding: '0 17px',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 700,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    boxShadow: '0 7px 16px rgba(47,84,201,.18)',
    transition: 'transform .18s ease, background .18s ease, opacity .18s ease',
};

export default function MyCompanies({ group, entitlement, companies }: Props) {
    const [isAddDialogOpen, setIsAddDialogOpen] = useState(false);
    const [step, setStep] = useState<Step>(1);
    const [showAdvanced, setShowAdvanced] = useState(false);
    const form = useForm({
        name: '',
        company_name: '',
        slug: '',
    });

    const openAddDialog = () => {
        form.clearErrors();
        setStep(1);
        setShowAdvanced(false);
        setIsAddDialogOpen(true);
    };

    const closeAddDialog = () => {
        if (!form.processing) {
            setIsAddDialogOpen(false);
            setStep(1);
        }
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(MultiCompanyController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setIsAddDialogOpen(false);
                setStep(1);
                toast.success('Perusahaan berhasil ditambahkan.');
            },
        });
    };

    const continueToReview = () => {
        form.clearErrors('name', 'company_name', 'slug');

        if (!form.data.name.trim()) {
            form.setError('name', 'Nama perusahaan wajib diisi.');

            return;
        }

        setStep(2);
    };

    const switchCompany = (companyId: number) => {
        router.post(
            CompanySwitcherController.store().url,
            { tenant_id: companyId },
            { preserveScroll: false },
        );
    };

    const usagePercentage = Math.min(
        100,
        Math.round(
            (entitlement.used / Math.max(entitlement.company_limit, 1)) * 100,
        ),
    );

    return (
        <>
            <Head title="Grup Perusahaan" />

            <div className="mc-page" style={pageStyle}>
                <div style={breadcrumbStyle}>
                    <span>Pengaturan</span>
                    <AIcon name="chevron-right" size={13} color={C.faint} />
                    <span style={{ color: C.muted }}>Grup Perusahaan</span>
                </div>

                <div className="mc-page-header" style={pageHeaderStyle}>
                    <div style={{ minWidth: 0 }}>
                        <div style={eyebrowStyle}>MANAJEMEN GRUP</div>
                        <h1 style={titleStyle}>Grup Perusahaan</h1>
                        <p style={descriptionStyle}>
                            Kelola entitas perusahaan dan akses kerja dalam grup{' '}
                            <strong style={{ color: C.text }}>
                                {group.name}
                            </strong>
                            .
                        </p>
                    </div>

                    {entitlement.enabled && entitlement.remaining > 0 && (
                        <button
                            type="button"
                            onClick={openAddDialog}
                            style={primaryButton}
                        >
                            <AIcon name="plus" size={17} color="#fff" />
                            Tambah perusahaan
                        </button>
                    )}
                </div>

                <section
                    style={{
                        ...card,
                        padding: 0,
                        overflow: 'hidden',
                        marginBottom: 18,
                    }}
                >
                    <div className="mc-summary-grid" style={summaryGridStyle}>
                        <SummaryItem
                            icon="building-2"
                            label="Entitas terdaftar"
                            value={`${entitlement.used} / ${entitlement.company_limit}`}
                            note="perusahaan aktif dalam grup"
                        />
                        <SummaryItem
                            icon="layers-2"
                            label="Slot tersedia"
                            value={String(entitlement.remaining)}
                            note={
                                entitlement.remaining > 0
                                    ? 'siap digunakan'
                                    : 'kuota penuh'
                            }
                            tone={entitlement.remaining > 0 ? C.green : C.amber}
                        />
                        <SummaryItem
                            icon="badge-check"
                            label="Status add-on"
                            value={
                                entitlement.enabled ? 'Aktif' : 'Tidak aktif'
                            }
                            note={
                                entitlement.enabled
                                    ? 'Multi Company tersedia'
                                    : 'hubungi Super Admin'
                            }
                            tone={entitlement.enabled ? C.green : C.muted}
                        />
                    </div>
                    <div style={usageBarWrapStyle}>
                        <div style={usageBarHeaderStyle}>
                            <span>Penggunaan kuota perusahaan</span>
                            <strong>
                                {entitlement.used} dari{' '}
                                {entitlement.company_limit} slot
                            </strong>
                        </div>
                        <div
                            aria-label={`${usagePercentage}% kuota perusahaan terpakai`}
                            role="progressbar"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={usagePercentage}
                            style={progressTrackStyle}
                        >
                            <div
                                style={{
                                    ...progressValueStyle,
                                    width: `${usagePercentage}%`,
                                }}
                            />
                        </div>
                    </div>
                </section>

                {!entitlement.enabled && (
                    <div style={noticeStyle} role="status">
                        <span style={noticeIconStyle}>
                            <AIcon name="info" size={17} color={C.amber} />
                        </span>
                        <div style={{ minWidth: 0 }}>
                            <div style={noticeTitleStyle}>
                                Multi Company belum aktif
                            </div>
                            <div style={noticeTextStyle}>
                                Anda masih dapat mengakses perusahaan yang sudah
                                terdaftar. Hubungi Super Admin untuk menambah
                                slot perusahaan baru.
                            </div>
                        </div>
                    </div>
                )}

                <section
                    style={{
                        ...card,
                        padding: 0,
                        overflow: 'hidden',
                        marginTop: entitlement.enabled ? 0 : 18,
                    }}
                >
                    <div style={sectionHeaderStyle}>
                        <div>
                            <h2 style={sectionTitleStyle}>Struktur grup</h2>
                            <p style={sectionDescriptionStyle}>
                                Perusahaan inti dan perusahaan bawahan tetap
                                memiliki data operasional yang terpisah.
                            </p>
                        </div>
                        <span style={sectionCountStyle}>
                            {companies.length} entitas
                        </span>
                    </div>

                    {companies.length > 0 ? (
                        <div style={{ overflowX: 'auto' }}>
                            <div style={{ minWidth: 720 }}>
                                <div style={companyHeaderStyle}>
                                    <span>Entitas</span>
                                    <span>Karyawan</span>
                                    <span>Cabang</span>
                                    <span>Status</span>
                                    <span style={{ textAlign: 'right' }}>
                                        Aksi
                                    </span>
                                </div>
                                {companies.map((company) => (
                                    <CompanyRow
                                        key={company.id}
                                        company={company}
                                        onSwitch={switchCompany}
                                    />
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div style={emptyStateStyle}>
                            <span style={emptyIconStyle}>
                                <AIcon
                                    name="building-2"
                                    size={22}
                                    color={C.primary}
                                />
                            </span>
                            <h3 style={emptyTitleStyle}>
                                Belum ada perusahaan
                            </h3>
                            <p style={emptyTextStyle}>
                                Tambahkan entitas pertama untuk mulai mengelola
                                grup perusahaan.
                            </p>
                        </div>
                    )}
                </section>

                <div style={footerInfoStyle}>
                    <span style={footerInfoIconStyle}>
                        <AIcon
                            name="shield-check"
                            size={16}
                            color={C.primary}
                        />
                    </span>
                    <span>
                        Data antarperusahaan tetap terisolasi. Untuk mengelola
                        karyawan dan struktur organisasi perusahaan yang sedang
                        aktif, buka{' '}
                        <Link
                            href={CompanySetupController.index().url}
                            style={footerLinkStyle}
                        >
                            pengaturan perusahaan
                        </Link>
                        .
                    </span>
                </div>
            </div>

            <Dialog
                open={isAddDialogOpen}
                onOpenChange={(open) =>
                    open ? setIsAddDialogOpen(true) : closeAddDialog()
                }
            >
                <DialogContent className="max-h-[92vh] max-w-[860px] gap-0 overflow-y-auto border-[#e5e9f2] bg-white p-0 shadow-[0_24px_70px_rgba(14,26,58,.2)] sm:max-w-[860px]">
                    <DialogHeader className="border-b border-[#eef1f6] px-6 py-5 text-left sm:px-8">
                        <div style={dialogEyebrowStyle}>ENTITAS BARU</div>
                        <DialogTitle className="mt-1 text-[21px] font-semibold tracking-[-0.02em] text-[#0e1a3a]">
                            Tambah perusahaan
                        </DialogTitle>
                        <DialogDescription className="mt-1 max-w-[600px] text-[13px] leading-6 text-[#6b7280]">
                            Tambahkan perusahaan ke grup {group.name}. Data
                            karyawan dan operasional perusahaan baru akan
                            dikelola terpisah.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit}>
                        <div className="grid gap-0 md:grid-cols-[230px_minmax(0,1fr)]">
                            <aside className="border-b border-[#eef1f6] bg-[#f8faff] px-6 py-6 md:border-r md:border-b-0 md:px-7">
                                <div style={stepperIntroStyle}>
                                    PROSES PENAMBAHAN
                                </div>
                                <div
                                    style={{
                                        display: 'grid',
                                        gap: 8,
                                        marginTop: 18,
                                    }}
                                >
                                    <StepItem
                                        active={step === 1}
                                        complete={step > 1}
                                        number="01"
                                        title="Data perusahaan"
                                        description="Identitas dasar entitas"
                                    />
                                    <StepConnector active={step > 1} />
                                    <StepItem
                                        active={step === 2}
                                        complete={false}
                                        number="02"
                                        title="Konfirmasi"
                                        description="Tinjau sebelum dibuat"
                                    />
                                </div>
                                <div style={dialogSideNoteStyle}>
                                    <AIcon
                                        name="lock-keyhole"
                                        size={16}
                                        color={C.primary}
                                    />
                                    <span>
                                        Perusahaan baru akan mendapatkan ruang
                                        data terpisah.
                                    </span>
                                </div>
                            </aside>

                            <div className="min-w-0 px-6 py-6 md:px-8 md:py-7">
                                {step === 1 ? (
                                    <div style={{ display: 'grid', gap: 20 }}>
                                        <div>
                                            <h3 style={dialogSectionTitleStyle}>
                                                Identitas perusahaan
                                            </h3>
                                            <p style={dialogSectionTextStyle}>
                                                Gunakan nama yang mudah dikenali
                                                oleh admin dan pengguna saat
                                                berpindah perusahaan.
                                            </p>
                                        </div>

                                        <Field
                                            label="Nama perusahaan"
                                            required
                                            error={form.errors.name}
                                            htmlFor="company-name"
                                        >
                                            <input
                                                id="company-name"
                                                value={form.data.name}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                                style={inputStyle}
                                                placeholder="Contoh: LearnPath Teknologi"
                                                autoComplete="organization"
                                                autoFocus
                                            />
                                            <span style={helperTextStyle}>
                                                Nama singkat yang tampil di
                                                switcher dan daftar perusahaan.
                                            </span>
                                        </Field>

                                        <Field
                                            label="Nama legal / display"
                                            error={form.errors.company_name}
                                            htmlFor="company-legal-name"
                                        >
                                            <input
                                                id="company-legal-name"
                                                value={form.data.company_name}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'company_name',
                                                        event.target.value,
                                                    )
                                                }
                                                style={inputStyle}
                                                placeholder="Contoh: PT LearnPath Teknologi Indonesia"
                                                autoComplete="organization"
                                            />
                                            <span style={helperTextStyle}>
                                                Opsional. Isi jika berbeda dari
                                                nama perusahaan.
                                            </span>
                                        </Field>

                                        <div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setShowAdvanced(
                                                        (visible) => !visible,
                                                    )
                                                }
                                                style={advancedToggleStyle}
                                                aria-expanded={showAdvanced}
                                            >
                                                <AIcon
                                                    name={
                                                        showAdvanced
                                                            ? 'chevron-down'
                                                            : 'chevron-right'
                                                    }
                                                    size={15}
                                                    color={C.primary}
                                                />
                                                Pengaturan lanjutan
                                            </button>
                                            {showAdvanced && (
                                                <div style={{ marginTop: 13 }}>
                                                    <Field
                                                        label="Slug"
                                                        error={form.errors.slug}
                                                        htmlFor="company-slug"
                                                    >
                                                        <input
                                                            id="company-slug"
                                                            value={
                                                                form.data.slug
                                                            }
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'slug',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            style={inputStyle}
                                                            placeholder="otomatis dari nama perusahaan"
                                                            autoComplete="off"
                                                        />
                                                        <span
                                                            style={
                                                                helperTextStyle
                                                            }
                                                        >
                                                            Biarkan kosong untuk
                                                            membuat slug secara
                                                            otomatis.
                                                        </span>
                                                    </Field>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <div style={{ display: 'grid', gap: 20 }}>
                                        <div>
                                            <h3 style={dialogSectionTitleStyle}>
                                                Periksa data perusahaan
                                            </h3>
                                            <p style={dialogSectionTextStyle}>
                                                Pastikan identitas entitas sudah
                                                benar sebelum dibuat.
                                            </p>
                                        </div>

                                        <div style={reviewPanelStyle}>
                                            <ReviewItem
                                                label="Nama perusahaan"
                                                value={form.data.name}
                                            />
                                            <ReviewItem
                                                label="Nama legal / display"
                                                value={
                                                    form.data.company_name ||
                                                    'Mengikuti nama perusahaan'
                                                }
                                            />
                                            <ReviewItem
                                                label="Slug"
                                                value={
                                                    form.data.slug ||
                                                    'Dibuat otomatis'
                                                }
                                            />
                                        </div>

                                        <div style={reviewNoticeStyle}>
                                            <span style={reviewNoticeIconStyle}>
                                                <AIcon
                                                    name="info"
                                                    size={16}
                                                    color={C.primary}
                                                />
                                            </span>
                                            <div>
                                                <div
                                                    style={{
                                                        fontWeight: 700,
                                                        color: C.navy,
                                                        fontSize: 12.5,
                                                    }}
                                                >
                                                    Yang akan terjadi
                                                </div>
                                                <div
                                                    style={{
                                                        marginTop: 3,
                                                        color: C.muted,
                                                        fontSize: 12.5,
                                                        lineHeight: 1.55,
                                                    }}
                                                >
                                                    Sistem membuat perusahaan
                                                    aktif, memasang konfigurasi
                                                    dasar, dan memberikan akses
                                                    admin kepada akun Anda.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-col-reverse gap-2 border-t border-[#eef1f6] px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                            <button
                                type="button"
                                onClick={closeAddDialog}
                                disabled={form.processing}
                                style={ghostButton}
                            >
                                Batal
                            </button>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                {step === 2 && (
                                    <button
                                        type="button"
                                        onClick={() => setStep(1)}
                                        disabled={form.processing}
                                        style={ghostButton}
                                    >
                                        <AIcon
                                            name="arrow-left"
                                            size={15}
                                            color={C.muted}
                                        />
                                        Kembali
                                    </button>
                                )}
                                {step === 1 ? (
                                    <button
                                        key="continue"
                                        type="button"
                                        onClick={continueToReview}
                                        style={primaryButton}
                                    >
                                        Lanjutkan
                                        <AIcon
                                            name="arrow-right"
                                            size={16}
                                            color="#fff"
                                        />
                                    </button>
                                ) : (
                                    <button
                                        key="submit"
                                        type="submit"
                                        disabled={form.processing}
                                        style={{
                                            ...primaryButton,
                                            opacity: form.processing ? 0.65 : 1,
                                        }}
                                    >
                                        <AIcon
                                            name={
                                                form.processing
                                                    ? 'loader-circle'
                                                    : 'check'
                                            }
                                            size={16}
                                            color="#fff"
                                        />
                                        {form.processing
                                            ? 'Menyimpan…'
                                            : 'Buat perusahaan'}
                                    </button>
                                )}
                            </div>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function SummaryItem({
    icon,
    label,
    value,
    note,
    tone = C.navy,
}: {
    icon: string;
    label: string;
    value: string;
    note: string;
    tone?: string;
}) {
    return (
        <div style={summaryItemStyle}>
            <span style={{ ...summaryIconStyle, background: hexA(tone, 0.1) }}>
                <AIcon name={icon} size={18} color={tone} />
            </span>
            <div style={{ minWidth: 0 }}>
                <div style={summaryLabelStyle}>{label}</div>
                <div style={{ ...summaryValueStyle, color: tone }}>{value}</div>
                <div style={summaryNoteStyle}>{note}</div>
            </div>
        </div>
    );
}

function CompanyRow({
    company,
    onSwitch,
}: {
    company: Company;
    onSwitch: (id: number) => void;
}) {
    const displayName = company.company_name || company.name;
    const initials = displayName
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    return (
        <div className="mc-company-row" style={companyRowStyle}>
            <div style={companyIdentityStyle}>
                <span style={companyAvatarStyle}>{initials || 'PT'}</span>
                <div style={{ minWidth: 0 }}>
                    <div style={companyNameStyle}>{displayName}</div>
                    <div style={companyMetaStyle}>
                        {company.is_primary
                            ? 'Perusahaan utama'
                            : 'Perusahaan bawahan'}
                    </div>
                </div>
            </div>
            <Metric value={company.employees_count} label="karyawan" />
            <Metric value={company.branches_count} label="cabang" />
            <StatusPill status={company.status} />
            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                {company.is_current ? (
                    <span style={activeCompanyStyle}>
                        <AIcon name="check" size={14} color={C.green} />
                        Sedang aktif
                    </span>
                ) : (
                    <button
                        type="button"
                        onClick={() => onSwitch(company.id)}
                        style={switchButtonStyle}
                    >
                        Buka perusahaan
                        <AIcon
                            name="arrow-up-right"
                            size={14}
                            color={C.primary}
                        />
                    </button>
                )}
            </div>
        </div>
    );
}

function Metric({ value, label }: { value: number; label: string }) {
    return (
        <div>
            <div style={metricValueStyle}>{value}</div>
            <div style={metricLabelStyle}>{label}</div>
        </div>
    );
}

function StatusPill({ status }: { status: string }) {
    const isActive = status === 'active';

    return (
        <span
            style={{
                ...statusPillStyle,
                color: isActive ? C.green : C.muted,
                background: isActive ? '#ECFDF3' : '#F3F4F6',
            }}
        >
            <span
                style={{
                    ...statusDotStyle,
                    background: isActive ? C.green : C.faint,
                }}
            />
            {isActive ? 'Aktif' : status}
        </span>
    );
}

function StepItem({
    active,
    complete,
    number,
    title,
    description,
}: {
    active: boolean;
    complete: boolean;
    number: string;
    title: string;
    description: string;
}) {
    return (
        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 11 }}>
            <span
                style={{
                    ...stepNumberStyle,
                    background: active || complete ? C.primary : '#fff',
                    color: active || complete ? '#fff' : C.faint,
                    borderColor: active || complete ? C.primary : C.border,
                }}
            >
                {complete ? (
                    <AIcon name="check" size={14} color="#fff" />
                ) : (
                    number
                )}
            </span>
            <div>
                <div
                    style={{
                        fontSize: 12.5,
                        fontWeight: 700,
                        color: active ? C.navy : C.muted,
                    }}
                >
                    {title}
                </div>
                <div
                    style={{
                        marginTop: 2,
                        fontSize: 11.5,
                        color: C.faint,
                        lineHeight: 1.4,
                    }}
                >
                    {description}
                </div>
            </div>
        </div>
    );
}

function StepConnector({ active }: { active: boolean }) {
    return (
        <div
            style={{
                width: 1,
                height: 18,
                marginLeft: 14,
                background: active ? C.primary : C.border,
            }}
        />
    );
}

function ReviewItem({ label, value }: { label: string; value: string }) {
    return (
        <div style={reviewItemStyle}>
            <span style={reviewLabelStyle}>{label}</span>
            <span style={reviewValueStyle}>{value}</span>
        </div>
    );
}

function Field({
    label,
    required,
    error,
    htmlFor,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    htmlFor: string;
    children: ReactNode;
}) {
    return (
        <label htmlFor={htmlFor} style={{ display: 'grid', gap: 7 }}>
            <span style={fieldLabelStyle}>
                {label} {required && <span style={{ color: C.red }}>*</span>}
            </span>
            {children}
            {error && (
                <span role="alert" style={errorTextStyle}>
                    {error}
                </span>
            )}
        </label>
    );
}

const pageStyle: CSSProperties = {
    width: '100%',
    maxWidth: 1220,
    padding: '30px 34px 42px',
};
const breadcrumbStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 7,
    fontSize: 12.5,
    color: C.faint,
    marginBottom: 16,
};
const pageHeaderStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    gap: 20,
    marginBottom: 24,
};
const eyebrowStyle: CSSProperties = {
    fontSize: 10.5,
    letterSpacing: '.12em',
    fontWeight: 800,
    color: C.primary,
    marginBottom: 8,
};
const titleStyle: CSSProperties = {
    margin: 0,
    color: C.navy,
    fontSize: 28,
    lineHeight: 1.15,
    letterSpacing: '-.025em',
    fontWeight: 700,
};
const descriptionStyle: CSSProperties = {
    margin: '8px 0 0',
    color: C.muted,
    fontSize: 14,
    lineHeight: 1.6,
};
const summaryGridStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
};
const summaryItemStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 13,
    minHeight: 112,
    padding: '20px 22px',
    borderRight: `1px solid ${C.line}`,
};
const summaryIconStyle: CSSProperties = {
    width: 38,
    height: 38,
    flex: 'none',
    borderRadius: 10,
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
};
const summaryLabelStyle: CSSProperties = {
    color: C.muted,
    fontSize: 12.5,
    fontWeight: 600,
};
const summaryValueStyle: CSSProperties = {
    marginTop: 2,
    fontSize: 22,
    lineHeight: 1.1,
    fontWeight: 750,
    letterSpacing: '-.02em',
};
const summaryNoteStyle: CSSProperties = {
    marginTop: 5,
    color: C.faint,
    fontSize: 11.5,
};
const usageBarWrapStyle: CSSProperties = {
    padding: '14px 22px 18px',
    borderTop: `1px solid ${C.line}`,
};
const usageBarHeaderStyle: CSSProperties = {
    display: 'flex',
    justifyContent: 'space-between',
    gap: 12,
    color: C.muted,
    fontSize: 11.5,
    marginBottom: 8,
};
const progressTrackStyle: CSSProperties = {
    width: '100%',
    height: 6,
    overflow: 'hidden',
    borderRadius: 99,
    background: '#EEF1F7',
};
const progressValueStyle: CSSProperties = {
    height: '100%',
    borderRadius: 99,
    background: C.primary,
    transition: 'width .25s ease',
};
const noticeStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'flex-start',
    gap: 12,
    padding: '14px 16px',
    marginBottom: 18,
    border: '1px solid #FDE7B2',
    borderRadius: 11,
    background: '#FFFBEB',
};
const noticeIconStyle: CSSProperties = {
    width: 32,
    height: 32,
    flex: 'none',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 9,
    background: '#FEF3C7',
};
const noticeTitleStyle: CSSProperties = {
    fontSize: 12.5,
    fontWeight: 700,
    color: '#92400E',
};
const noticeTextStyle: CSSProperties = {
    marginTop: 3,
    color: '#A16207',
    fontSize: 12.5,
    lineHeight: 1.5,
};
const sectionHeaderStyle: CSSProperties = {
    minHeight: 78,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
    padding: '17px 22px',
    borderBottom: `1px solid ${C.line}`,
};
const sectionTitleStyle: CSSProperties = {
    margin: 0,
    color: C.navy,
    fontSize: 15.5,
    fontWeight: 700,
};
const sectionDescriptionStyle: CSSProperties = {
    margin: '4px 0 0',
    color: C.faint,
    fontSize: 12,
    lineHeight: 1.45,
};
const sectionCountStyle: CSSProperties = {
    flex: 'none',
    padding: '6px 9px',
    borderRadius: 7,
    background: '#F4F6FB',
    color: C.muted,
    fontSize: 11.5,
    fontWeight: 700,
};
const companyHeaderStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns:
        'minmax(280px, 1.8fr) 100px 90px 110px minmax(150px, .8fr)',
    alignItems: 'center',
    gap: 16,
    padding: '11px 22px',
    borderBottom: `1px solid ${C.line}`,
    color: C.faint,
    fontSize: 10.5,
    fontWeight: 800,
    letterSpacing: '.06em',
    textTransform: 'uppercase',
};
const companyRowStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns:
        'minmax(280px, 1.8fr) 100px 90px 110px minmax(150px, .8fr)',
    alignItems: 'center',
    gap: 16,
    minHeight: 86,
    padding: '13px 22px',
    borderBottom: `1px solid ${C.line}`,
    color: C.text,
};
const companyIdentityStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 12,
    minWidth: 0,
};
const companyAvatarStyle: CSSProperties = {
    width: 38,
    height: 38,
    flex: 'none',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    background: '#EEF2FF',
    color: C.primary,
    fontSize: 11,
    fontWeight: 800,
};
const companyNameStyle: CSSProperties = {
    overflow: 'hidden',
    color: C.navy,
    fontSize: 13.5,
    fontWeight: 700,
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
};
const companyMetaStyle: CSSProperties = {
    marginTop: 4,
    color: C.faint,
    fontSize: 11.5,
};
const metricValueStyle: CSSProperties = {
    color: C.navy,
    fontSize: 14,
    fontWeight: 700,
};
const metricLabelStyle: CSSProperties = {
    marginTop: 3,
    color: C.faint,
    fontSize: 11,
};
const statusPillStyle: CSSProperties = {
    width: 'fit-content',
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    padding: '6px 9px',
    borderRadius: 99,
    fontSize: 11.5,
    fontWeight: 700,
};
const statusDotStyle: CSSProperties = {
    width: 6,
    height: 6,
    borderRadius: '50%',
};
const activeCompanyStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 5,
    color: C.green,
    fontSize: 11.5,
    fontWeight: 700,
};
const switchButtonStyle: CSSProperties = {
    minHeight: 40,
    border: `1px solid ${hexA(C.primary, 0.2)}`,
    borderRadius: 8,
    padding: '0 11px',
    background: hexA(C.primary, 0.05),
    color: C.primary,
    fontSize: 11.5,
    fontWeight: 700,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    transition: 'transform .18s ease, background .18s ease',
};
const emptyStateStyle: CSSProperties = {
    display: 'grid',
    justifyItems: 'center',
    gap: 8,
    padding: '54px 24px',
    textAlign: 'center',
};
const emptyIconStyle: CSSProperties = {
    width: 48,
    height: 48,
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
    background: '#EEF2FF',
};
const emptyTitleStyle: CSSProperties = {
    margin: '5px 0 0',
    color: C.navy,
    fontSize: 15,
    fontWeight: 700,
};
const emptyTextStyle: CSSProperties = {
    maxWidth: 380,
    margin: 0,
    color: C.muted,
    fontSize: 12.5,
    lineHeight: 1.55,
};
const footerInfoStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'flex-start',
    gap: 9,
    marginTop: 16,
    color: C.muted,
    fontSize: 12.5,
    lineHeight: 1.55,
};
const footerInfoIconStyle: CSSProperties = {
    width: 26,
    height: 26,
    flex: 'none',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    background: '#EEF2FF',
};
const footerLinkStyle: CSSProperties = {
    color: C.primary,
    fontWeight: 700,
    textDecoration: 'none',
};
const dialogEyebrowStyle: CSSProperties = {
    color: C.primary,
    fontSize: 10.5,
    letterSpacing: '.12em',
    fontWeight: 800,
};
const stepperIntroStyle: CSSProperties = {
    color: C.faint,
    fontSize: 10,
    letterSpacing: '.1em',
    fontWeight: 800,
};
const stepNumberStyle: CSSProperties = {
    width: 29,
    height: 29,
    flex: 'none',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    border: '1px solid',
    borderRadius: '50%',
    fontSize: 10.5,
    fontWeight: 800,
};
const dialogSideNoteStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'flex-start',
    gap: 8,
    marginTop: 42,
    padding: '11px 12px',
    border: `1px solid ${hexA(C.primary, 0.14)}`,
    borderRadius: 9,
    color: C.muted,
    background: '#fff',
    fontSize: 11.5,
    lineHeight: 1.45,
};
const dialogSectionTitleStyle: CSSProperties = {
    margin: 0,
    color: C.navy,
    fontSize: 16,
    fontWeight: 700,
};
const dialogSectionTextStyle: CSSProperties = {
    margin: '5px 0 0',
    color: C.muted,
    fontSize: 12.5,
    lineHeight: 1.55,
};
const fieldLabelStyle: CSSProperties = {
    color: C.text,
    fontSize: 12.5,
    fontWeight: 700,
};
const helperTextStyle: CSSProperties = {
    color: C.faint,
    fontSize: 11.5,
    lineHeight: 1.4,
};
const errorTextStyle: CSSProperties = {
    color: C.red,
    fontSize: 11.5,
    lineHeight: 1.4,
};
const advancedToggleStyle: CSSProperties = {
    border: 0,
    padding: 0,
    background: 'transparent',
    color: C.primary,
    fontSize: 12.5,
    fontWeight: 700,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    gap: 7,
};
const reviewPanelStyle: CSSProperties = {
    overflow: 'hidden',
    border: `1px solid ${C.border}`,
    borderRadius: 10,
    background: '#FBFCFE',
};
const reviewItemStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'minmax(130px, .7fr) minmax(0, 1.3fr)',
    gap: 16,
    padding: '14px 16px',
    borderBottom: `1px solid ${C.line}`,
};
const reviewLabelStyle: CSSProperties = { color: C.muted, fontSize: 12 };
const reviewValueStyle: CSSProperties = {
    overflow: 'hidden',
    color: C.navy,
    fontSize: 12.5,
    fontWeight: 700,
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
};
const reviewNoticeStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'flex-start',
    gap: 10,
    padding: '12px 13px',
    border: `1px solid ${hexA(C.primary, 0.16)}`,
    borderRadius: 10,
    background: '#F7F9FF',
};
const reviewNoticeIconStyle: CSSProperties = {
    width: 28,
    height: 28,
    flex: 'none',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    background: '#E8EDFF',
};
