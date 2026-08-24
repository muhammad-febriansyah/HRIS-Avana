import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, CheckCircle2 } from 'lucide-react';
import type { FormEvent } from 'react';
import ReferralLeadController from '@/actions/App/Http/Controllers/ReferralLeadController';
import { Container, Reveal } from '@/components/marketing/reveal';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

const DESCRIPTION =
    'Daftarkan perusahaan Anda untuk mencoba AvanaHR. Tim kami akan menghubungi Anda untuk menjadwalkan demo.';

interface FlashProps {
    flash?: { success?: boolean };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export default function CompanyInquiry() {
    const { website, flash } = usePage<FlashProps>().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Daftar Perusahaan — ${brand}`;
    const isSuccess = flash?.success === true;

    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '',
        contact_name: '',
        email: '',
        phone: '',
        note: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(ReferralLeadController.store().url, { onSuccess: () => reset() });
    };

    return (
        <>
            <Head title={title}>
                <meta name="description" content={DESCRIPTION} />
            </Head>

            <div className="min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333] antialiased">
                <SiteNavbar brand={brand} logo={logo} anchorPrefix="/" />

                <main className="bg-[#F8FAFD]">
                    <Container className="py-12 sm:py-16 lg:py-20">
                        <div className="mx-auto max-w-xl">
                            <Reveal>
                                <span className="inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-white px-4 py-1.5 text-[12px] font-bold tracking-[0.08em] text-[#2F54C9] uppercase shadow-sm">
                                    <Building2 className="h-3.5 w-3.5" />
                                    Daftar Perusahaan
                                </span>
                                <h1 className="mt-5 text-[28px] leading-[1.1] font-extrabold tracking-[-0.03em] text-[#0E1A3A] sm:text-[36px]">
                                    Coba AvanaHR untuk perusahaan Anda
                                </h1>
                                <p className="mt-4 text-[15px] leading-relaxed text-[#5B6478]">
                                    Isi data singkat berikut. Tim kami akan
                                    menghubungi Anda untuk menjadwalkan demo dan
                                    membantu proses onboarding.
                                </p>
                            </Reveal>

                            <Reveal delay={0.1}>
                                <div className="mt-8 rounded-3xl border border-[#E2E9F6] bg-white p-6 shadow-soft sm:p-8">
                                    {!isSuccess ? (
                                        <form
                                            onSubmit={handleSubmit}
                                            className="space-y-4"
                                        >
                                            <Field
                                                label="Nama Perusahaan"
                                                error={errors.company_name}
                                            >
                                                <input
                                                    value={data.company_name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'company_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={inputClass}
                                                    placeholder="PT Contoh Sejahtera"
                                                />
                                            </Field>
                                            <Field
                                                label="Nama Kontak"
                                                error={errors.contact_name}
                                            >
                                                <input
                                                    value={data.contact_name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'contact_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={inputClass}
                                                    placeholder="Nama Anda"
                                                />
                                            </Field>
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <Field
                                                    label="Email"
                                                    error={errors.email}
                                                >
                                                    <input
                                                        type="email"
                                                        value={data.email}
                                                        onChange={(e) =>
                                                            setData(
                                                                'email',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={inputClass}
                                                        placeholder="nama@perusahaan.com"
                                                    />
                                                </Field>
                                                <Field
                                                    label="No. WhatsApp"
                                                    error={errors.phone}
                                                >
                                                    <input
                                                        value={data.phone}
                                                        onChange={(e) =>
                                                            setData(
                                                                'phone',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={inputClass}
                                                        placeholder="08xxxxxxxxxx"
                                                    />
                                                </Field>
                                            </div>
                                            <Field
                                                label="Catatan (opsional)"
                                                error={errors.note}
                                            >
                                                <textarea
                                                    value={data.note}
                                                    onChange={(e) =>
                                                        setData(
                                                            'note',
                                                            e.target.value,
                                                        )
                                                    }
                                                    rows={3}
                                                    className={inputClass}
                                                    placeholder="Jumlah karyawan, kebutuhan spesifik, dsb."
                                                />
                                            </Field>

                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#2F54C9] px-6 text-[14px] font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-[#2348B0] hover:shadow-lg disabled:opacity-60"
                                            >
                                                Kirim Pendaftaran
                                                <ArrowRight className="h-4 w-4" />
                                            </button>
                                        </form>
                                    ) : (
                                        <div className="py-6 text-center">
                                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                                <CheckCircle2 className="h-8 w-8" />
                                            </div>
                                            <h2 className="mt-5 text-[20px] font-bold text-[#0E1A3A]">
                                                Terima kasih!
                                            </h2>
                                            <p className="mx-auto mt-3 max-w-sm text-[14px] leading-relaxed text-[#5B6478]">
                                                Data Anda sudah kami terima. Tim
                                                AvanaHR akan segera menghubungi
                                                Anda.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </Reveal>
                        </div>
                    </Container>
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />
                <WhatsAppFab />
            </div>
        </>
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
