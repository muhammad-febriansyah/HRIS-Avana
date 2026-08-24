import { Head, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import PartnerRegistrationController from '@/actions/App/Http/Controllers/PartnerRegistrationController';
import { Container, Reveal } from '@/components/marketing/reveal';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';
import { cn } from '@/lib/utils';

const DESCRIPTION =
    'Daftar jadi Partner AvanaHR. Dapatkan link referral unik, tracking otomatis, dan dashboard partner.';

const CHECKS = [
    {
        title: 'Link referral unik',
        body: 'Setiap partner mendapatkan kode dan link referral sendiri.',
    },
    {
        title: 'Tracking otomatis',
        body: 'Referral yang masuk melalui link Anda tercatat di sistem.',
    },
    {
        title: 'Dashboard partner',
        body: 'Pantau trial, customer, dan status reward Anda.',
    },
    {
        title: 'Dukungan tim AvanaHR',
        body: 'Kami membantu proses demo dan onboarding customer.',
    },
];

const PARTNER_TYPES = [
    'HR Consultant',
    'Business Consultant',
    'Agency',
    'Komunitas',
    'Freelancer',
    'Lainnya',
];

const NETWORK_SIZES = ['1–10', '11–50', '51–100', '100+'];

const NETWORK_FOCUSES = [
    'HR',
    'UMKM',
    'Perusahaan Menengah',
    'Enterprise',
    'Campuran',
];

const HOW_DID_YOU_KNOW = [
    'Referral',
    'Instagram',
    'Website',
    'Teman / Relasi',
    'Lainnya',
];

interface FlashProps {
    flash?: { success?: boolean };
    [key: string]: unknown;
}

export default function PartnerRegistration() {
    const { website, flash } = usePage<FlashProps>().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Daftar Jadi Partner — ${brand}`;

    const { data, setData, post, processing, errors, reset } = useForm({
        full_name: '',
        email: '',
        whatsapp: '',
        partner_type: '',
        company_name: '',
        network_size: '1–10',
        network_focus: 'HR',
        network_description: '',
        social_link: '',
        how_did_you_know: 'Referral',
        terms_accepted: false,
    });

    const [step, setStep] = useState(1);
    const totalSteps = 3;

    const isSuccess = flash?.success === true;

    const handleNext = () => {
        if (step < totalSteps) {
            setStep((s) => s + 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    const handleBack = () => {
        if (step > 1) {
            setStep((s) => s - 1);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(PartnerRegistrationController.store().url, {
            onSuccess: () => {
                setStep(totalSteps + 1);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={title}>
                <meta name="description" content={DESCRIPTION} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={title} />
                <meta property="og:description" content={DESCRIPTION} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={title} />
                <meta name="twitter:description" content={DESCRIPTION} />
            </Head>

            <div
                id="top"
                className="min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333] antialiased"
            >
                <SiteNavbar
                    brand={brand}
                    logo={logo}
                    anchorPrefix="/"
                    activePage="partnership"
                />

                <main className="bg-[#F8FAFD]">
                    <Container className="py-12 sm:py-16 lg:py-20">
                        <div className="grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-14">
                            {/* ── Left intro ── */}
                            <div className="lg:col-span-5">
                                <Reveal>
                                    <span className="inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-white px-4 py-1.5 text-[12px] font-bold tracking-[0.08em] text-[#2F54C9] uppercase shadow-sm">
                                        <Users className="h-3.5 w-3.5" />
                                        AvanaHR Partner Program
                                    </span>
                                </Reveal>

                                <Reveal delay={0.05}>
                                    <h1 className="mt-5 text-[32px] leading-[1.05] font-extrabold tracking-[-0.03em] text-[#0E1A3A] sm:text-[44px] lg:text-[50px]">
                                        Mulai Jadi{' '}
                                        <span className="text-[#2F54C9]">
                                            Partner AvanaHR
                                        </span>
                                    </h1>
                                </Reveal>

                                <Reveal delay={0.1}>
                                    <p className="mt-4 text-[15px] leading-relaxed text-[#5B6478] sm:text-[16px]">
                                        Bergabung sebagai partner dan rekomendasikan
                                        AvanaHR kepada perusahaan dalam jaringan Anda.
                                        Kami membantu proses selanjutnya, sementara Anda
                                        dapat memantau referral dan reward melalui
                                        dashboard.
                                    </p>
                                </Reveal>

                                <Reveal delay={0.15}>
                                    <div className="mt-8 space-y-4">
                                        {CHECKS.map((check) => (
                                            <div
                                                key={check.title}
                                                className="flex items-start gap-3"
                                            >
                                                <div className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                                    <Check className="h-3.5 w-3.5 stroke-[3]" />
                                                </div>
                                                <div>
                                                    <p className="text-[14px] font-semibold text-[#0E1A3A]">
                                                        {check.title}
                                                    </p>
                                                    <p className="text-[13.5px] text-[#5B6478]">
                                                        {check.body}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </Reveal>
                            </div>

                            {/* ── Right form ── */}
                            <div className="lg:col-span-7">
                                <Reveal delay={0.1}>
                                    <div className="rounded-3xl border border-[#E2E9F6] bg-white p-6 shadow-soft sm:p-8 lg:p-10">
                                        {!isSuccess && step <= totalSteps && (
                                            <>
                                                <h2 className="text-[22px] font-bold text-[#0E1A3A] sm:text-[24px]">
                                                    Daftar Jadi Partner
                                                </h2>
                                                <p className="mt-1.5 text-[13.5px] text-[#5B6478]">
                                                    Isi data berikut. Setelah disetujui,
                                                    Anda akan mendapatkan akses ke Partner
                                                    Dashboard.
                                                </p>

                                                {/* Progress bar */}
                                                <div className="mt-6 flex gap-2">
                                                    {Array.from({ length: totalSteps }).map((_, i) => (
                                                        <div
                                                            key={i}
                                                            className={cn(
                                                                'h-1.5 flex-1 rounded-full transition-colors duration-300',
                                                                i < step
                                                                    ? 'bg-[#2F54C9]'
                                                                    : 'bg-[#E2E9F6]'
                                                            )}
                                                        />
                                                    ))}
                                                </div>

                                                <form
                                                    onSubmit={handleSubmit}
                                                    className="mt-6"
                                                >
                                                    {/* Step 1 */}
                                                    {step === 1 && (
                                                        <div className="space-y-4">
                                                            <div>
                                                                <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                    Nama Lengkap{' '}
                                                                    <span className="text-red-500">
                                                                        *
                                                                    </span>
                                                                </label>
                                                                <input
                                                                    type="text"
                                                                    value={data.full_name}
                                                                    onChange={(e) =>
                                                                        setData(
                                                                            'full_name',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    placeholder="Contoh: Rudi Pratama"
                                                                    className={cn(
                                                                        'w-full rounded-xl border px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all placeholder:text-[#9CA3AF] focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20',
                                                                        errors.full_name
                                                                            ? 'border-red-300 focus:border-red-400 focus:ring-red-200'
                                                                            : 'border-[#D5DEEC]'
                                                                    )}
                                                                />
                                                                {errors.full_name && (
                                                                    <p className="mt-1 text-[12px] text-red-500">
                                                                        {errors.full_name}
                                                                    </p>
                                                                )}
                                                            </div>

                                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                                <div>
                                                                    <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                        Email{' '}
                                                                        <span className="text-red-500">
                                                                            *
                                                                        </span>
                                                                    </label>
                                                                    <input
                                                                        type="email"
                                                                        value={data.email}
                                                                        onChange={(e) =>
                                                                            setData(
                                                                                'email',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        placeholder="nama@email.com"
                                                                        className={cn(
                                                                            'w-full rounded-xl border px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all placeholder:text-[#9CA3AF] focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20',
                                                                            errors.email
                                                                                ? 'border-red-300 focus:border-red-400 focus:ring-red-200'
                                                                                : 'border-[#D5DEEC]'
                                                                        )}
                                                                    />
                                                                    {errors.email && (
                                                                        <p className="mt-1 text-[12px] text-red-500">
                                                                            {errors.email}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                                <div>
                                                                    <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                        No. WhatsApp{' '}
                                                                        <span className="text-red-500">
                                                                            *
                                                                        </span>
                                                                    </label>
                                                                    <input
                                                                        type="text"
                                                                        value={data.whatsapp}
                                                                        onChange={(e) =>
                                                                            setData(
                                                                                'whatsapp',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        placeholder="08xxxxxxxxxx"
                                                                        className={cn(
                                                                            'w-full rounded-xl border px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all placeholder:text-[#9CA3AF] focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20',
                                                                            errors.whatsapp
                                                                                ? 'border-red-300 focus:border-red-400 focus:ring-red-200'
                                                                                : 'border-[#D5DEEC]'
                                                                        )}
                                                                    />
                                                                    {errors.whatsapp && (
                                                                        <p className="mt-1 text-[12px] text-red-500">
                                                                            {errors.whatsapp}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            <div>
                                                                <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                    Jenis Partner{' '}
                                                                    <span className="text-red-500">
                                                                        *
                                                                    </span>
                                                                </label>
                                                                <select
                                                                    value={data.partner_type}
                                                                    onChange={(e) =>
                                                                        setData(
                                                                            'partner_type',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    className={cn(
                                                                        'w-full rounded-xl border px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20',
                                                                        errors.partner_type
                                                                            ? 'border-red-300 focus:border-red-400 focus:ring-red-200'
                                                                            : 'border-[#D5DEEC]'
                                                                    )}
                                                                >
                                                                    <option value="">
                                                                        Pilih jenis partner
                                                                    </option>
                                                                    {PARTNER_TYPES.map(
                                                                        (t) => (
                                                                            <option
                                                                                key={t}
                                                                                value={t}
                                                                            >
                                                                                {t}
                                                                            </option>
                                                                        )
                                                                    )}
                                                                </select>
                                                                {errors.partner_type && (
                                                                    <p className="mt-1 text-[12px] text-red-500">
                                                                        {errors.partner_type}
                                                                    </p>
                                                                )}
                                                            </div>

                                                            <div className="flex justify-end pt-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={handleNext}
                                                                    className="inline-flex h-11 items-center gap-2 rounded-full bg-[#2F54C9] px-6 text-[14px] font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg"
                                                                >
                                                                    Lanjutkan
                                                                    <ArrowRight className="h-4 w-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {/* Step 2 */}
                                                    {step === 2 && (
                                                        <div className="space-y-4">
                                                            <div>
                                                                <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                    Nama Perusahaan / Brand
                                                                </label>
                                                                <input
                                                                    type="text"
                                                                    value={data.company_name}
                                                                    onChange={(e) =>
                                                                        setData(
                                                                            'company_name',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    placeholder="Contoh: Rudi HR Consulting"
                                                                    className="w-full rounded-xl border border-[#D5DEEC] px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all placeholder:text-[#9CA3AF] focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20"
                                                                />
                                                            </div>

                                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                                <div>
                                                                    <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                        Jumlah Network Perusahaan
                                                                    </label>
                                                                    <select
                                                                        value={data.network_size}
                                                                        onChange={(e) =>
                                                                            setData(
                                                                                'network_size',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        className="w-full rounded-xl border border-[#D5DEEC] px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20"
                                                                    >
                                                                        {NETWORK_SIZES.map(
                                                                            (s) => (
                                                                                <option
                                                                                    key={s}
                                                                                    value={s}
                                                                                >
                                                                                    {s}
                                                                                </option>
                                                                            )
                                                                        )}
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                        Fokus Network
                                                                    </label>
                                                                    <select
                                                                        value={data.network_focus}
                                                                        onChange={(e) =>
                                                                            setData(
                                                                                'network_focus',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        className="w-full rounded-xl border border-[#D5DEEC] px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20"
                                                                    >
                                                                        {NETWORK_FOCUSES.map(
                                                                            (f) => (
                                                                                <option
                                                                                    key={f}
                                                                                    value={f}
                                                                                >
                                                                                    {f}
                                                                                </option>
                                                                            )
                                                                        )}
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div>
                                                                <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                    Ceritakan Network Anda
                                                                </label>
                                                                <textarea
                                                                    rows={4}
                                                                    value={data.network_description}
                                                                    onChange={(e) =>
                                                                        setData(
                                                                            'network_description',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    placeholder="Contoh: Saya memiliki jaringan sekitar 30 perusahaan yang membutuhkan solusi HR dan payroll."
                                                                    className="w-full resize-none rounded-xl border border-[#D5DEEC] px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all placeholder:text-[#9CA3AF] focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20"
                                                                />
                                                            </div>

                                                            <div className="flex justify-between pt-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={handleBack}
                                                                    className="inline-flex h-11 items-center gap-2 rounded-full border border-[#D5DEEC] bg-white px-6 text-[14px] font-bold text-[#0E1A3A] transition-all hover:bg-[#F4F7FF]"
                                                                >
                                                                    <ArrowLeft className="h-4 w-4" />
                                                                    Kembali
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={handleNext}
                                                                    className="inline-flex h-11 items-center gap-2 rounded-full bg-[#2F54C9] px-6 text-[14px] font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg"
                                                                >
                                                                    Lanjutkan
                                                                    <ArrowRight className="h-4 w-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {/* Step 3 */}
                                                    {step === 3 && (
                                                        <div className="space-y-4">
                                                            <div>
                                                                <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                    Website / LinkedIn / Instagram (opsional)
                                                                </label>
                                                                <input
                                                                    type="text"
                                                                    value={data.social_link}
                                                                    onChange={(e) =>
                                                                        setData(
                                                                            'social_link',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    placeholder="https://..."
                                                                    className="w-full rounded-xl border border-[#D5DEEC] px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all placeholder:text-[#9CA3AF] focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20"
                                                                />
                                                            </div>

                                                            <div>
                                                                <label className="mb-1.5 block text-[13px] font-semibold text-[#0E1A3A]">
                                                                    Bagaimana Anda mengetahui AvanaHR?
                                                                </label>
                                                                <select
                                                                    value={data.how_did_you_know}
                                                                    onChange={(e) =>
                                                                        setData(
                                                                            'how_did_you_know',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    className="w-full rounded-xl border border-[#D5DEEC] px-4 py-3 text-[14px] text-[#0E1A3A] outline-none transition-all focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/20"
                                                                >
                                                                    {HOW_DID_YOU_KNOW.map(
                                                                        (h) => (
                                                                            <option
                                                                                key={h}
                                                                                value={h}
                                                                            >
                                                                                {h}
                                                                            </option>
                                                                        )
                                                                    )}
                                                                </select>
                                                            </div>

                                                            <div className="rounded-xl border border-[#E2E9F6] bg-[#F8FAFD] p-4">
                                                                <p className="text-[13px] leading-relaxed text-[#5B6478]">
                                                                    <span className="font-semibold text-[#0E1A3A]">
                                                                        Informasi partnership:
                                                                    </span>{' '}
                                                                    Besaran reward mengikuti
                                                                    program partnership yang
                                                                    berlaku. Detail skema dan
                                                                    status reward dapat dilihat
                                                                    setelah akun partner disetujui
                                                                    melalui dashboard.
                                                                </p>
                                                            </div>

                                                            <div>
                                                                <label className="flex cursor-pointer items-start gap-3">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={data.terms_accepted}
                                                                        onChange={(e) =>
                                                                            setData(
                                                                                'terms_accepted',
                                                                                e.target.checked
                                                                            )
                                                                        }
                                                                        className="mt-0.5 h-4.5 w-4.5 shrink-0 rounded-md border-[#D5DEEC] text-[#2F54C9] accent-[#2F54C9]"
                                                                    />
                                                                    <span className="text-[13px] leading-relaxed text-[#5B6478]">
                                                                        Saya menyetujui{' '}
                                                                        <a
                                                                            href="/syarat-ketentuan"
                                                                            className="font-semibold text-[#2F54C9] underline hover:text-[#2348B0]"
                                                                        >
                                                                            syarat dan ketentuan
                                                                        </a>{' '}
                                                                        program partnership
                                                                        AvanaHR.
                                                                    </span>
                                                                </label>
                                                                {errors.terms_accepted && (
                                                                    <p className="mt-1 text-[12px] text-red-500">
                                                                        {errors.terms_accepted}
                                                                    </p>
                                                                )}
                                                            </div>

                                                            <div className="flex justify-between pt-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={handleBack}
                                                                    className="inline-flex h-11 items-center gap-2 rounded-full border border-[#D5DEEC] bg-white px-6 text-[14px] font-bold text-[#0E1A3A] transition-all hover:bg-[#F4F7FF]"
                                                                >
                                                                    <ArrowLeft className="h-4 w-4" />
                                                                    Kembali
                                                                </button>
                                                                <button
                                                                    type="submit"
                                                                    disabled={processing}
                                                                    className="inline-flex h-11 items-center gap-2 rounded-full bg-[#2F54C9] px-6 text-[14px] font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg disabled:opacity-60 disabled:hover:translate-y-0"
                                                                >
                                                                    {processing
                                                                        ? 'Mengirim...'
                                                                        : 'Kirim Pendaftaran'}
                                                                    <Check className="h-4 w-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    )}
                                                </form>
                                            </>
                                        )}

                                        {/* Success state */}
                                        {(isSuccess || step > totalSteps) && (
                                            <div className="py-8 text-center sm:py-12">
                                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 sm:h-20 sm:w-20">
                                                    <CheckCircle2 className="h-8 w-8 sm:h-10 sm:w-10" />
                                                </div>
                                                <h2 className="mt-5 text-[22px] font-bold text-[#0E1A3A] sm:text-[26px]">
                                                    Pendaftaran Berhasil!
                                                </h2>
                                                <p className="mx-auto mt-3 max-w-md text-[14px] leading-relaxed text-[#5B6478]">
                                                    Terima kasih telah mendaftar sebagai
                                                    Partner AvanaHR. Tim kami akan meninjau
                                                    data Anda. Setelah disetujui, Anda akan
                                                    mendapatkan akses ke Partner Dashboard
                                                    dan kode referral unik.
                                                </p>
                                                <a
                                                    href="/partner"
                                                    className="mt-7 inline-flex h-11 items-center gap-2 rounded-full bg-[#2F54C9] px-6 text-[14px] font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg"
                                                >
                                                    Kembali ke Halaman Partner
                                                    <ArrowRight className="h-4 w-4" />
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                </Reveal>
                            </div>
                        </div>
                    </Container>
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />
                <WhatsAppFab />
            </div>
        </>
    );
}
