import { AlertCircle } from 'lucide-react';
import { PAIN_POINTS } from './content';
import { Container, Reveal } from './reveal';

/** Short rose-pill callout per pain point — restates the cost, not a stat. */
const HIGHLIGHTS: Record<string, string> = {
    'Data tersebar di banyak sistem':
        'Silo data menghambat efisiensi operasional.',
    'Proses masih administratif': 'Waktu tersita untuk kerja manual.',
    'Pengajuan sulit dipantau': 'Approval tercecer di banyak kanal.',
    'Data belum jadi insight': 'Keputusan tanpa data yang utuh.',
};

/**
 * Problem framing: the scattered-tooling status quo AvanaHR replaces.
 *
 * Ported from the reference site's spotlight-card + stacked-list layout — a
 * human illustration card on the left, the real pain points from
 * `PAIN_POINTS` as a numbered list on the right.
 */
export function ProblemSection() {
    return (
        <section className="relative overflow-hidden border-y border-avana-border bg-avana-soft py-20 lg:py-28">
            <Container>
                <Reveal className="mx-auto max-w-3xl space-y-4 text-center">
                    <span className="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold tracking-wide text-rose-700 uppercase">
                        <AlertCircle className="h-3.5 w-3.5" aria-hidden />
                        Tantangan Nyata Praktisi HR
                    </span>

                    <h2 className="text-3xl leading-tight font-extrabold text-avana-navy sm:text-4xl lg:text-[44px]">
                        Data HR Bertambah. <br className="hidden sm:inline" />
                        <span className="text-avana-blue">
                            Apakah Insight-nya Ikut Bertambah?
                        </span>
                    </h2>

                    <p className="text-base leading-relaxed font-normal text-avana-text sm:text-lg">
                        Saat perusahaan berkembang, data HR semakin banyak.
                        Tantangannya bukan hanya menyimpan data di
                        spreadsheet, tetapi menghubungkannya agar HR dapat
                        melihat kondisi workforce secara utuh.
                    </p>
                </Reveal>

                <div className="mt-10 grid grid-cols-1 items-center gap-8 lg:mt-16 lg:grid-cols-12">
                    {/* Left: human context card */}
                    <Reveal className="relative flex flex-col justify-between overflow-hidden rounded-3xl border border-avana-border bg-white p-6 shadow-avana-card lg:col-span-4">
                        <div className="relative z-10 space-y-3">
                            <span className="inline-block rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-bold tracking-wider text-rose-600 uppercase">
                                Sering Dialami Tim HR
                            </span>
                            <h3 className="text-xl font-extrabold text-avana-navy">
                                &ldquo;Berapa banyak waktu yang habis hanya
                                untuk rekap absensi &amp; hitung pajak
                                manual?&rdquo;
                            </h3>
                        </div>

                        <div className="relative my-4 flex items-center justify-center">
                            <div className="w-full max-w-[280px] overflow-hidden rounded-2xl">
                                <img
                                    src="/avana/landing/images/human-thinking-problem.png"
                                    alt="Praktisi HR menghadapi kompleksitas data"
                                    className="h-auto w-full transform object-contain transition-transform duration-500 hover:scale-105"
                                    loading="lazy"
                                />
                            </div>
                        </div>

                        <div className="relative z-10 border-t border-avana-border pt-3 text-xs text-avana-muted">
                            <strong className="text-avana-navy">
                                AvanaHR hadir
                            </strong>{' '}
                            untuk mengotomasi pekerjaan repetitif agar tim HR
                            bisa fokus pada pengembangan karyawan.
                        </div>
                    </Reveal>

                    {/* Right: pain point cards */}
                    <div className="space-y-4 lg:col-span-8">
                        {PAIN_POINTS.map((point, i) => (
                            <Reveal
                                key={point.title}
                                delay={0.06 + i * 0.05}
                                className="group flex flex-col items-start justify-between gap-5 rounded-2xl border border-avana-border/80 bg-white p-6 shadow-sm transition-all duration-300 hover:border-avana-blue/40 hover:shadow-avana-hover sm:flex-row sm:items-center sm:p-7"
                            >
                                <div className="flex items-start gap-4">
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-avana-light text-avana-blue shadow-sm transition-colors duration-300 group-hover:bg-avana-blue group-hover:text-white">
                                        <point.icon
                                            className="h-6 w-6"
                                            aria-hidden
                                        />
                                    </div>
                                    <div>
                                        <h3 className="mb-1.5 text-base font-bold text-avana-navy sm:text-lg">
                                            {point.title}
                                        </h3>
                                        <p className="max-w-xl text-xs leading-relaxed text-avana-text/80 sm:text-sm">
                                            {point.body}
                                        </p>
                                    </div>
                                </div>

                                <span className="shrink-0 pt-2 text-xs font-semibold text-rose-600 sm:pt-0 sm:text-right">
                                    <span className="inline-block rounded-full border border-rose-100 bg-rose-50 px-3 py-1">
                                        {HIGHLIGHTS[point.title] ?? point.body}
                                    </span>
                                </span>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </Container>
        </section>
    );
}
