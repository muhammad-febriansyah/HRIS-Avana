import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Sparkles } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import CompanyRegistrationController from '@/actions/App/Http/Controllers/CompanyRegistrationController';
import { CompanyRegisterShell } from '@/components/marketing/company-register-shell';

const DESCRIPTION = 'Buat akun AvanaHR Anda sendiri — trial 14 hari, tanpa kartu kredit.';

const STEPS = [
    { key: 'perusahaan', label: 'Data Perusahaan' },
    { key: 'admin', label: 'Akun Admin' },
] as const;

/** Which step owns which field — used to jump the wizard back to whichever
 * step a server-side validation error belongs to. */
const STEP_FIELDS: Record<number, string[]> = {
    0: ['company_name', 'phone'],
    1: ['admin_name', 'admin_email', 'admin_password', 'terms_accepted'],
};

interface PageProps {
    partnerCode: string;
    website: { site_name?: string; logo_url?: string };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export default function CompanyRegistration({ partnerCode }: { partnerCode: string }) {
    const { website, errors } = usePage<PageProps>().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Daftar Perusahaan — ${brand}`;

    const [step, setStep] = useState(0);

    const form = useForm({
        company_name: '',
        phone: '',
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

        const stepIndex = Object.entries(STEP_FIELDS).find(([, fields]) => fields.some((f) => errored.includes(f)))?.[0];

        if (stepIndex !== undefined) {
            setStep(Number(stepIndex));
        }
    }, [errors]);

    const stepComplete = (index: number): boolean => {
        if (index === 0) {
            return form.data.company_name.trim() !== '' && form.data.phone.trim() !== '';
        }

        return (
            form.data.admin_name.trim() !== '' &&
            form.data.admin_email.trim() !== '' &&
            form.data.admin_password !== '' &&
            form.data.admin_password === form.data.admin_password_confirmation &&
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
                title="Buat akun AvanaHR Anda"
                description="Trial 14 hari, semua fitur terbuka — pilih paket kapan saja setelah masuk."
                partnerCode={partnerCode}
            >
                <Stepper current={step} />

                <form onSubmit={submit} className="mt-6 space-y-4">
                    {step === 0 && (
                        <>
                            <Field label="Nama Perusahaan" error={errors.company_name}>
                                <input
                                    value={form.data.company_name}
                                    onChange={(e) => form.setData('company_name', e.target.value)}
                                    className={inputClass}
                                    placeholder="PT Contoh Sejahtera"
                                    autoFocus
                                />
                            </Field>
                            <Field label="No. WhatsApp" error={errors.phone}>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                    className={inputClass}
                                    placeholder="08xxxxxxxxxx"
                                />
                            </Field>
                        </>
                    )}

                    {step === 1 && (
                        <>
                            <Field label="Nama Admin" error={errors.admin_name}>
                                <input
                                    value={form.data.admin_name}
                                    onChange={(e) => form.setData('admin_name', e.target.value)}
                                    className={inputClass}
                                    placeholder="Nama Anda"
                                    autoFocus
                                />
                            </Field>
                            <Field label="Email Admin" error={errors.admin_email}>
                                <input
                                    type="email"
                                    value={form.data.admin_email}
                                    onChange={(e) => form.setData('admin_email', e.target.value)}
                                    className={inputClass}
                                    placeholder="nama@perusahaan.com"
                                />
                            </Field>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Password" error={errors.admin_password}>
                                    <input
                                        type="password"
                                        value={form.data.admin_password}
                                        onChange={(e) => form.setData('admin_password', e.target.value)}
                                        className={inputClass}
                                        placeholder="Minimal 8 karakter"
                                    />
                                </Field>
                                <Field label="Konfirmasi Password">
                                    <input
                                        type="password"
                                        value={form.data.admin_password_confirmation}
                                        onChange={(e) => form.setData('admin_password_confirmation', e.target.value)}
                                        className={inputClass}
                                        placeholder="Ulangi password"
                                    />
                                </Field>
                            </div>
                            <label className="flex items-start gap-2.5 pt-1 text-[13px] leading-relaxed text-[#5B6478]">
                                <input
                                    type="checkbox"
                                    checked={form.data.terms_accepted}
                                    onChange={(e) => form.setData('terms_accepted', e.target.checked)}
                                    className="mt-0.5 h-4 w-4 flex-none rounded border-[#E2E9F6] text-[#2F54C9]"
                                />
                                Saya menyetujui{' '}
                                <a href="/syarat-ketentuan" target="_blank" rel="noreferrer" className="font-semibold text-[#2F54C9]">
                                    Syarat &amp; Ketentuan
                                </a>{' '}
                                AvanaHR.
                            </label>
                            {errors.terms_accepted && <p className="text-[12.5px] text-red-600">{errors.terms_accepted}</p>}
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
                            disabled={form.processing || !stepComplete(step)}
                            className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-[#2F54C9] px-6 text-[14px] font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                        >
                            {step < STEPS.length - 1 ? (
                                <>
                                    Lanjut
                                    <ArrowRight className="h-4 w-4" />
                                </>
                            ) : form.processing ? (
                                'Membuat akun…'
                            ) : (
                                <>
                                    <Sparkles className="h-4 w-4" />
                                    Buat Akun &amp; Mulai Trial
                                </>
                            )}
                        </button>
                    </div>
                </form>
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
                            {i < current ? <Check className="h-3 w-3" /> : i + 1}
                        </span>
                        <span className={`text-[12px] font-semibold ${i <= current ? 'text-[#0E1A3A]' : 'text-[#9AA4B8]'}`}>{s.label}</span>
                    </div>
                    {i < STEPS.length - 1 && <span className="h-px w-6 bg-[#E2E9F6]" />}
                </div>
            ))}
        </div>
    );
}

const inputClass =
    'w-full rounded-xl border border-[#E2E9F6] bg-white px-4 py-2.5 text-[14px] text-[#1A2333] outline-none transition-colors focus:border-[#2F54C9]';

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">{label}</label>
            {children}
            {error && <p className="mt-1 text-[12.5px] text-red-600">{error}</p>}
        </div>
    );
}
