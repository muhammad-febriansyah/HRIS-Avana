import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CreditCard,
    HeadphonesIcon,
    LayoutDashboard,
} from 'lucide-react';
import { Container, Reveal } from './reveal';
import { useCtaTargets } from './use-cta';

const FEATURES = [
    {
        icon: CheckCircle2,
        title: 'Setup & Migrasi Gratis',
        note: 'Tim kami siap membantu Anda',
    },
    {
        icon: HeadphonesIcon,
        title: 'Prioritas Support Tim',
        note: 'Pendampingan penuh ahli HR',
    },
    {
        icon: CreditCard,
        title: 'Tanpa Kartu Kredit',
        note: 'Tidak ada tagihan mendadak',
    },
    {
        icon: LayoutDashboard,
        title: 'Akses Penuh Semua Fitur',
        note: 'Eksplorasi seluruh modul',
    },
];

/** Free-trial promo banner — 3-month trial, same claim as the navbar's top bar. */
export function FreeTrialBanner() {
    const { login } = useCtaTargets();

    return (
        <section className="relative bg-white py-16 md:py-24">
            <Container>
                <Reveal>
                    <div className="group relative flex flex-col overflow-hidden rounded-[28px] border border-white/10 bg-gradient-to-r from-avana-blue to-[#416BDB] shadow-2xl transition-all duration-300 hover:shadow-blue-900/20 lg:flex-row lg:items-stretch">
                        {/* Left: image */}
                        <div className="relative flex h-72 w-full items-end justify-center overflow-hidden bg-blue-100 pt-8 lg:h-auto lg:w-[30%]">
                            <div className="absolute inset-0 z-0 bg-gradient-to-t from-blue-200/50 to-transparent" />
                            <div className="absolute right-0 bottom-0 left-0 z-0 h-[80%] -translate-x-4 rounded-tr-[120px] rounded-tl-[40px] bg-white/30" />

                            <img
                                src="https://ik.imagekit.io/b8izy6rik/trial.png"
                                alt="Profesional HR menggunakan AvanaHR"
                                className="z-10 h-auto w-full max-w-[280px] object-contain object-bottom drop-shadow-xl transition-transform duration-500 group-hover:scale-105 lg:max-w-[95%]"
                            />
                        </div>

                        {/* Middle: content */}
                        <div className="relative z-10 flex w-full flex-col items-center justify-center p-8 text-center text-white lg:w-[40%] lg:items-start lg:px-10 lg:py-14 lg:text-left">
                            <div className="mb-3 flex items-center gap-2 text-xs font-bold tracking-widest text-blue-200 uppercase">
                                <span className="hidden h-px w-8 bg-blue-300/50 lg:block" />
                                Penawaran Terbatas
                            </div>

                            <h2 className="mb-4 text-3xl leading-tight font-extrabold lg:text-4xl">
                                Hai, Coba Gratis{' '}
                                <br className="hidden lg:block" />
                                3 Bulan Pertama!
                            </h2>

                            <p className="mx-auto mb-8 max-w-sm text-sm leading-relaxed text-blue-50 opacity-90 md:text-base lg:mx-0">
                                Tinggalkan cara lama rekap data manual.
                                Beralih ke sistem HR terpadu dan otomatis.
                                Buktikan sendiri kemudahannya sekarang juga!
                            </p>

                            <Link
                                href={login}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-8 py-3.5 font-extrabold text-avana-blue shadow-xl shadow-black/10 transition-all duration-300 hover:-translate-y-1 hover:bg-blue-50 hover:shadow-2xl hover:shadow-black/20 sm:w-auto"
                            >
                                Mulai Uji Coba Gratis
                                <ArrowRight className="h-4 w-4" aria-hidden />
                            </Link>
                        </div>

                        {/* Right: feature list */}
                        <div className="z-10 flex w-full flex-col justify-center border-t border-white/10 bg-white/10 p-8 backdrop-blur-md lg:w-[30%] lg:border-t-0 lg:border-l lg:px-8 lg:py-12">
                            <ul className="space-y-6">
                                {FEATURES.map(({ icon: Icon, title, note }) => (
                                    <li
                                        key={title}
                                        className="group/item flex items-center gap-4 text-white"
                                    >
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 transition-colors duration-300 group-hover/item:bg-white/20">
                                            <Icon
                                                className="h-5 w-5 text-blue-100"
                                                aria-hidden
                                            />
                                        </div>
                                        <div>
                                            <div className="text-[14px] font-bold">
                                                {title}
                                            </div>
                                            <div className="mt-0.5 text-xs text-blue-100/70">
                                                {note}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
