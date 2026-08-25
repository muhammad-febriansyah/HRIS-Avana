import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2 } from 'lucide-react';
import type { FormEvent } from 'react';
import ReferralLeadController from '@/actions/App/Http/Controllers/ReferralLeadController';
import { CompanyRegisterShell } from '@/components/marketing/company-register-shell';

const DESCRIPTION = 'Daftarkan perusahaan Anda untuk mencoba AvanaHR. Tim kami akan menghubungi Anda untuk menjadwalkan demo.';

interface FlashProps {
    flash?: { success?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export default function CompanyInquiry() {
    const { website, flash } = usePage<FlashProps>().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Daftar Perusahaan — ${brand}`;
    const isSuccess = Boolean(flash?.success);

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

            <CompanyRegisterShell
                brand={brand}
                logo={logo}
                eyebrow="Daftar Perusahaan"
                title="Coba AvanaHR untuk perusahaan Anda"
                description="Isi data singkat berikut. Tim kami akan menghubungi Anda untuk menjadwalkan demo dan membantu proses onboarding."
            >
                {!isSuccess ? (
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <Field label="Nama Perusahaan" error={errors.company_name}>
                            <input
                                value={data.company_name}
                                onChange={(e) => setData('company_name', e.target.value)}
                                className={inputClass}
                                placeholder="PT Contoh Sejahtera"
                            />
                        </Field>
                        <Field label="Nama Kontak" error={errors.contact_name}>
                            <input
                                value={data.contact_name}
                                onChange={(e) => setData('contact_name', e.target.value)}
                                className={inputClass}
                                placeholder="Nama Anda"
                            />
                        </Field>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Email" error={errors.email}>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className={inputClass}
                                    placeholder="nama@perusahaan.com"
                                />
                            </Field>
                            <Field label="No. WhatsApp" error={errors.phone}>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className={inputClass}
                                    placeholder="08xxxxxxxxxx"
                                />
                            </Field>
                        </div>
                        <Field label="Catatan (opsional)" error={errors.note}>
                            <textarea
                                value={data.note}
                                onChange={(e) => setData('note', e.target.value)}
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
                    <div className="py-4 text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <CheckCircle2 className="h-8 w-8" />
                        </div>
                        <h2 className="mt-5 text-[20px] font-bold text-[#0E1A3A]">Terima kasih!</h2>
                        <p className="mx-auto mt-3 max-w-sm text-[14px] leading-relaxed text-[#5B6478]">
                            Data Anda sudah kami terima. Tim AvanaHR akan segera menghubungi Anda.
                        </p>
                    </div>
                )}
            </CompanyRegisterShell>
        </>
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
