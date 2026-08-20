import { Check, ShieldCheck, Sparkles } from 'lucide-react';
import { PRICE_NOTE } from './content';
import { DemoButton } from './cta-buttons';
import { Container, Reveal } from './reveal';
import { useCtaTargets } from './use-cta';

const VALUE_POINTS = [
    'Proses HR lebih terhubung tanpa silo data',
    'Workforce data terpusat dalam satu platform cloud',
    'Executive Analytics dan fitur AI Assistant',
    'Kalkulasi otomatis PPh 21 TER & BPJS',
    'Mobile app absensi dengan GPS & Face Recognition',
    'Dukungan implementasi dan migrasi data',
];

const INCLUSIONS = [
    'Aplikasi Web & Mobile Karyawan',
    'Modul Core HR, Absensi & Payroll',
    'Update Regulasi Pajak PPh 21 Otomatis',
    'Workforce Analytics & Reporting',
];

/**
 * Pricing/value section — single starting-price teaser (no invented tiers,
 * since AvanaHR hasn't published tiered plans/limits yet) driving straight to
 * the demo CTA. Visual language ported from the AvanaHR reference site.
 */
export function PricingSection() {
    const { whatsappWith } = useCtaTargets();
    const enterpriseHref = whatsappWith(
        'Halo AvanaHR, saya ingin mengetahui penawaran harga paket untuk perusahaan kami.',
    );

    return (
        <section
            id="harga"
            className="border-t border-avana-border/60 bg-avana-soft py-20 md:py-28"
        >
            <Container>
                <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-12">
                    <Reveal className="text-left lg:col-span-7">
                        <div className="inline-flex items-center gap-2 rounded-full border border-avana-border bg-white px-3 py-1 text-xs font-bold tracking-wide text-avana-navy uppercase shadow-avana-subtle">
                            <Sparkles
                                className="h-3.5 w-3.5 text-avana-blue"
                                aria-hidden
                            />
                            <span>Investasi Terjangkau &amp; Transparan</span>
                        </div>

                        <h2 className="mt-6 text-3xl leading-tight font-extrabold text-avana-navy sm:text-4xl lg:text-[44px]">
                            Platform HR yang Siap <br className="hidden sm:inline" />
                            <span className="text-avana-blue">
                                Mengikuti Pertumbuhan Bisnis
                            </span>
                        </h2>

                        <p className="mt-4 text-base leading-relaxed font-normal text-avana-text sm:text-lg">
                            Dapatkan platform HRIS terintegrasi yang fleksibel
                            untuk tim kecil hingga ribuan karyawan, dengan
                            model harga yang transparan per pengguna.
                        </p>

                        <div className="mt-6 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                            {VALUE_POINTS.map((point) => (
                                <div
                                    key={point}
                                    className="flex items-start gap-2.5 text-sm font-medium text-avana-navy"
                                >
                                    <div className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <Check className="h-3.5 w-3.5 stroke-[3]" />
                                    </div>
                                    <span>{point}</span>
                                </div>
                            ))}
                        </div>

                        <div className="mt-6 flex items-center gap-2 text-xs text-avana-muted">
                            <ShieldCheck
                                className="h-4 w-4 shrink-0 text-avana-blue"
                                aria-hidden
                            />
                            <span>
                                Tersedia penyesuaian paket sesuai kebutuhan
                                modul dan jumlah karyawan.
                            </span>
                        </div>
                    </Reveal>

                    <Reveal delay={0.08} className="lg:col-span-5">
                        <div className="relative rounded-3xl border-2 border-avana-blue/30 bg-white p-8 shadow-avana-hover sm:p-10">
                            <div className="absolute -top-3.5 right-8 rounded-full bg-gradient-to-r from-avana-blue to-blue-600 px-4 py-1 text-xs font-bold tracking-wider text-white uppercase shadow-sm">
                                Harga Resmi AvanaHR
                            </div>

                            <div className="mb-6">
                                <span className="text-xs font-bold tracking-wider text-avana-muted uppercase">
                                    {PRICE_NOTE.prefix}
                                </span>

                                <div className="mt-1 flex items-baseline gap-2">
                                    <span className="text-4xl font-extrabold tracking-tight text-avana-navy sm:text-5xl">
                                        {PRICE_NOTE.amount}
                                    </span>
                                    <span className="text-sm font-medium text-avana-muted">
                                        {PRICE_NOTE.period}
                                    </span>
                                </div>

                                <p className="mt-2 text-xs text-avana-text/80">
                                    Solusi terjangkau untuk mengotomasi
                                    payroll, kehadiran, dan data karyawan
                                    tanpa biaya tersembunyi.
                                </p>
                            </div>

                            <hr className="my-6 border-gray-100" />

                            <div className="mb-8 space-y-3">
                                <div className="text-xs font-bold tracking-wider text-avana-navy uppercase">
                                    Sudah Termasuk:
                                </div>

                                {INCLUSIONS.map((item) => (
                                    <div
                                        key={item}
                                        className="flex items-center gap-2.5 text-xs text-avana-text sm:text-sm"
                                    >
                                        <Check
                                            className="h-4 w-4 shrink-0 text-avana-blue"
                                            aria-hidden
                                        />
                                        <span>{item}</span>
                                    </div>
                                ))}
                            </div>

                            <DemoButton className="w-full">
                                Lihat Paket &amp; Konsultasi
                            </DemoButton>

                            {enterpriseHref && (
                                <div className="mt-4 text-center">
                                    <a
                                        href={enterpriseHref}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-xs font-semibold text-avana-blue hover:underline"
                                    >
                                        Butuh penawaran custom enterprise?
                                        Hubungi Sales →
                                    </a>
                                </div>
                            )}
                        </div>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
