import { Head, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    Eye,
    EyeOff,
    Send,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import CompanyRegistrationController from '@/actions/App/Http/Controllers/CompanyRegistrationController';
import { CompanyRegisterShell } from '@/components/marketing/company-register-shell';

const DESCRIPTION =
    'Ajukan trial AvanaHR untuk membantu tim Anda mengelola HR, absensi, dan payroll dalam satu platform.';

const STEPS = [
    { key: 'perusahaan', label: 'Data Perusahaan' },
    { key: 'admin', label: 'Akun Admin' },
] as const;

/** Which step owns which field — used to jump the wizard back to whichever
 * step a server-side validation error belongs to. */
const STEP_FIELDS: Record<number, string[]> = {
    0: ['company_name', 'phone', 'industry', 'employee_count_range'],
    1: ['admin_name', 'admin_email', 'admin_password', 'terms_accepted'],
};

interface PageProps {
    partnerCode?: string | null;
    partnerName?: string | null;
    website: { site_name?: string; logo_url?: string };
    flash?: { success?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export default function CompanyRegistration({
    partnerCode,
    partnerName,
}: {
    partnerCode?: string | null;
    partnerName?: string | null;
}) {
    const { website, errors, flash } = usePage<PageProps>().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Daftar Perusahaan — ${brand}`;
    const isSubmitted = Boolean(flash?.success);

    const [step, setStep] = useState(0);
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] =
        useState(false);

    const form = useForm({
        company_name: '',
        phone: '',
        industry: '',
        employee_count_range: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
        terms_accepted: false,
    });

    // A server-side error always belongs to a specific field — jump back to
    // whichever step owns the first one so it's never left invisible on a
    // later step (see resources/js/pages/avana/klien/create.tsx for the same
    // pattern on the internal admin wizard).
    useEffect(() => {
        const errored = Object.keys(errors);

        if (errored.length === 0) {
            return;
        }

        const stepIndex = Object.entries(STEP_FIELDS).find(([, fields]) =>
            fields.some((f) => errored.includes(f)),
        )?.[0];

        if (stepIndex !== undefined) {
            setStep(Number(stepIndex));
        }
    }, [errors]);

    const stepComplete = (index: number): boolean => {
        if (index === 0) {
            return (
                form.data.company_name.trim() !== '' &&
                form.data.phone.trim() !== '' &&
                form.data.industry !== '' &&
                form.data.employee_count_range !== ''
            );
        }

        return (
            form.data.admin_name.trim() !== '' &&
            form.data.admin_email.trim() !== '' &&
            form.data.admin_password !== '' &&
            form.data.admin_password ===
                form.data.admin_password_confirmation &&
            form.data.terms_accepted
        );
    };

    const next = () => setStep((s) => Math.min(s + 1, STEPS.length - 1));
    const back = () => setStep((s) => Math.max(s - 1, 0));

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (step < STEPS.length - 1) {
            next();

            return;
        }

        form.post(CompanyRegistrationController.store().url);
    };

    return (
        <>
            <Head title={title}>
                <meta name="description" content={DESCRIPTION} />
            </Head>

            <CompanyRegisterShell
                brand={brand}
                logo={logo}
                eyebrow="Daftar Perusahaan"
                title={
                    isSubmitted
                        ? 'Pengajuan terkirim'
                        : 'Buat akun AvanaHR Anda'
                }
                description={
                    isSubmitted
                        ? 'Tim AvanaHR akan meninjau data Anda sebelum akun diaktifkan.'
                        : 'Isi data perusahaan dan admin. Tim AvanaHR akan meninjau pengajuan sebelum akun diaktifkan.'
                }
                partnerCode={partnerCode}
                partnerName={partnerName}
            >
                {isSubmitted ? (
                    <div className="py-4 text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <CheckCircle2 className="h-8 w-8" />
                        </div>
                        <h2 className="mt-5 text-[18px] font-bold text-[#0E1A3A]">
                            Menunggu persetujuan
                        </h2>
                        <p className="mx-auto mt-3 max-w-sm text-[14px] leading-relaxed text-[#5B6478]">
                            {flash?.success}
                        </p>
                        <p className="mx-auto mt-4 max-w-sm text-[13px] leading-relaxed text-[#9AA4B8]">
                            Setelah disetujui, masuk dengan email dan password
                            yang baru saja Anda buat.
                        </p>
                    </div>
                ) : (
                    <>
                        <Stepper current={step} />

                        <form onSubmit={submit} className="mt-6 space-y-4">
                            {step === 0 && (
                                <>
                                    <Field
                                        label="Nama Perusahaan"
                                        error={errors.company_name}
                                    >
                                        <input
                                            value={form.data.company_name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'company_name',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                            placeholder="PT Contoh Sejahtera"
                                            autoFocus
                                        />
                                    </Field>
                                    <Field
                                        label="No. WhatsApp"
                                        error={errors.phone}
                                    >
                                        <input
                                            type="tel"
                                            inputMode="tel"
                                            value={form.data.phone}
                                            onChange={(e) =>
                                                form.setData(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                            placeholder="08xxxxxxxxxx"
                                        />
                                    </Field>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <Field
                                            label="Industri"
                                            error={errors.industry}
                                        >
                                            <select
                                                value={form.data.industry}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'industry',
                                                        e.target.value,
                                                    )
                                                }
                                                className={inputClass}
                                            >
                                                <option value="">
                                                    Pilih industri
                                                </option>
                                                {[
                                                    'Teknologi',
                                                    'Manufaktur',
                                                    'Retail',
                                                    'Jasa',
                                                    'Pendidikan',
                                                    'Kesehatan',
                                                    'Lainnya',
                                                ].map((industry) => (
                                                    <option
                                                        key={industry}
                                                        value={industry}
                                                    >
                                                        {industry}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                        <Field
                                            label="Jumlah Karyawan"
                                            error={errors.employee_count_range}
                                        >
                                            <select
                                                value={
                                                    form.data
                                                        .employee_count_range
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'employee_count_range',
                                                        e.target.value,
                                                    )
                                                }
                                                className={inputClass}
                                            >
                                                <option value="">
                                                    Pilih jumlah
                                                </option>
                                                {[
                                                    '1-10',
                                                    '11-50',
                                                    '51-200',
                                                    '201-500',
                                                    '501+',
                                                ].map((range) => (
                                                    <option
                                                        key={range}
                                                        value={range}
                                                    >
                                                        {range} karyawan
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                    </div>
                                </>
                            )}

                            {step === 1 && (
                                <>
                                    <Field
                                        label="Nama Admin"
                                        error={errors.admin_name}
                                    >
                                        <input
                                            value={form.data.admin_name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'admin_name',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                            placeholder="Nama Anda"
                                            autoFocus
                                        />
                                    </Field>
                                    <Field
                                        label="Email Admin"
                                        error={errors.admin_email}
                                    >
                                        <input
                                            type="email"
                                            value={form.data.admin_email}
                                            onChange={(e) =>
                                                form.setData(
                                                    'admin_email',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                            placeholder="nama@perusahaan.com"
                                        />
                                    </Field>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <Field
                                            label="Password"
                                            error={errors.admin_password}
                                        >
                                            <div className="relative">
                                                <input
                                                    type={
                                                        showPassword
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    value={
                                                        form.data.admin_password
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'admin_password',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={`${inputClass} pr-10`}
                                                    placeholder="Minimal 8 karakter"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowPassword(
                                                            (v) => !v,
                                                        )
                                                    }
                                                    aria-label={
                                                        showPassword
                                                            ? 'Sembunyikan password'
                                                            : 'Tampilkan password'
                                                    }
                                                    className="absolute top-1/2 right-3.5 -translate-y-1/2 text-[#9CA3AF] hover:text-[#5B6478]"
                                                >
                                                    {showPassword ? (
                                                        <EyeOff className="h-4 w-4" />
                                                    ) : (
                                                        <Eye className="h-4 w-4" />
                                                    )}
                                                </button>
                                            </div>
                                        </Field>
                                        <Field label="Konfirmasi Password">
                                            <div className="relative">
                                                <input
                                                    type={
                                                        showPasswordConfirmation
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    value={
                                                        form.data
                                                            .admin_password_confirmation
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'admin_password_confirmation',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={`${inputClass} pr-10`}
                                                    placeholder="Ulangi password"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowPasswordConfirmation(
                                                            (v) => !v,
                                                        )
                                                    }
                                                    aria-label={
                                                        showPasswordConfirmation
                                                            ? 'Sembunyikan password'
                                                            : 'Tampilkan password'
                                                    }
                                                    className="absolute top-1/2 right-3.5 -translate-y-1/2 text-[#9CA3AF] hover:text-[#5B6478]"
                                                >
                                                    {showPasswordConfirmation ? (
                                                        <EyeOff className="h-4 w-4" />
                                                    ) : (
                                                        <Eye className="h-4 w-4" />
                                                    )}
                                                </button>
                                            </div>
                                        </Field>
                                    </div>
                                    <label className="flex items-start gap-2.5 pt-1 text-[13px] leading-relaxed text-[#5B6478]">
                                        <input
                                            type="checkbox"
                                            checked={form.data.terms_accepted}
                                            onChange={(e) =>
                                                form.setData(
                                                    'terms_accepted',
                                                    e.target.checked,
                                                )
                                            }
                                            className="mt-0.5 h-4 w-4 flex-none rounded border-[#E2E9F6] text-[#2F54C9]"
                                        />
                                        Saya menyetujui{' '}
                                        <a
                                            href="/syarat-ketentuan"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-semibold text-[#2F54C9]"
                                        >
                                            Syarat &amp; Ketentuan
                                        </a>{' '}
                                        AvanaHR.
                                    </label>
                                    {errors.terms_accepted && (
                                        <p className="text-[12.5px] text-red-600">
                                            {errors.terms_accepted}
                                        </p>
                                    )}
                                </>
                            )}

                            <div className="flex items-center gap-3 pt-2">
                                {step > 0 && (
                                    <button
                                        type="button"
                                        onClick={back}
                                        className="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-[#E2E9F6] bg-white px-5 text-[14px] font-semibold text-[#5B6478] transition-colors hover:bg-[#F8FAFD]"
                                    >
                                        <ArrowLeft className="h-4 w-4" />
                                        Kembali
                                    </button>
                                )}
                                <button
                                    type="submit"
                                    disabled={
                                        form.processing || !stepComplete(step)
                                    }
                                    className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-[#2F54C9] px-6 text-[14px] font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                                >
                                    {step < STEPS.length - 1 ? (
                                        <>
                                            Lanjut
                                            <ArrowRight className="h-4 w-4" />
                                        </>
                                    ) : form.processing ? (
                                        'Mengirim…'
                                    ) : (
                                        <>
                                            <Send className="h-4 w-4" />
                                            Kirim Pengajuan
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    </>
                )}
            </CompanyRegisterShell>
        </>
    );
}

function Stepper({ current }: { current: number }) {
    return (
        <div className="flex items-center gap-2">
            {STEPS.map((s, i) => (
                <div key={s.key} className="flex items-center gap-2">
                    <div className="flex items-center gap-2">
                        <span
                            className={`flex h-6 w-6 flex-none items-center justify-center rounded-full text-[11px] font-bold ${
                                i < current
                                    ? 'bg-[#2F54C9] text-white'
                                    : i === current
                                      ? 'border-2 border-[#2F54C9] text-[#2F54C9]'
                                      : 'border border-[#E2E9F6] text-[#9AA4B8]'
                            }`}
                        >
                            {i < current ? (
                                <Check className="h-3 w-3" />
                            ) : (
                                i + 1
                            )}
                        </span>
                        <span
                            className={`text-[12px] font-semibold ${i <= current ? 'text-[#0E1A3A]' : 'text-[#9AA4B8]'}`}
                        >
                            {s.label}
                        </span>
                    </div>
                    {i < STEPS.length - 1 && (
                        <span className="h-px w-6 bg-[#E2E9F6]" />
                    )}
                </div>
            ))}
        </div>
    );
}

const inputClass =
    'w-full rounded-xl border border-[#E2E9F6] bg-white px-4 py-2.5 text-[14px] text-[#1A2333] outline-none transition-colors focus:border-[#2F54C9]';

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                {label}
            </label>
            {children}
            {error && (
                <p className="mt-1 text-[12.5px] text-red-600">{error}</p>
            )}
        </div>
    );
}
