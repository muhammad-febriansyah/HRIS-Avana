import {
    CheckCircle2,
    FileSearch,
    MessagesSquare,
    Send,
    Sparkles,
} from 'lucide-react';
import { motion } from 'motion/react';
import { DemoButton } from './cta-buttons';
import { Container, Reveal } from './reveal';

const PILLARS = [
    {
        step: 'TANYA',
        icon: MessagesSquare,
        title: 'Karyawan Bertanya Seperti Biasa',
        desc: 'Cukup ketik pertanyaan seputar kebijakan HR — cuti, reimbursement, jam kerja — tanpa perlu mencari-cari dokumen sendiri.',
    },
    {
        step: 'BACA SOP',
        icon: FileSearch,
        title: 'SOP Sebagai Sumber Jawaban',
        desc: 'AI Assistant membaca SOP perusahaan yang telah dipublikasikan di AvanaHR, bukan mengarang jawaban dari luar konteks perusahaan.',
    },
    {
        step: 'KONFIGURASI',
        icon: Sparkles,
        title: 'Provider & Model Bisa Diatur',
        desc: 'Tim IT/HR dapat memilih provider dan model AI yang digunakan, sesuai kebijakan dan anggaran perusahaan.',
    },
] as const;

const MESSAGES: { from: 'user' | 'ai'; text: string }[] = [
    { from: 'user', text: 'Bagaimana prosedur reimbursement?' },
    {
        from: 'ai',
        text: 'Jawaban disusun dari SOP perusahaan yang dipublikasikan di AvanaHR.',
    },
];

/**
 * AI Assistant / workforce-insight section. Visual language borrowed from the
 * reference build's three-pillar layout, but the copy stays honest to what
 * ships: a chat-style assistant that answers only from published company SOP
 * documents, with a configurable AI provider/model.
 */
export function AiInsightSection() {
    return (
        <section
            id="ai"
            className="relative scroll-mt-28 overflow-hidden bg-gradient-to-b from-avana-soft via-avana-light/60 to-avana-soft py-20 md:py-28"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute top-1/2 left-0 h-96 w-96 -translate-y-1/2 rounded-full bg-blue-300/20 blur-3xl"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute right-0 bottom-0 h-96 w-96 rounded-full bg-avana-light blur-3xl"
            />

            <Container className="relative">
                <Reveal className="mx-auto max-w-2xl text-center">
                    <span className="inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-white px-3.5 py-1.5 text-[12px] font-semibold tracking-[0.08em] text-avana-navy uppercase shadow-sm">
                        <Sparkles
                            className="h-3.5 w-3.5 text-avana-blue"
                            aria-hidden
                        />
                        AI Assistant
                    </span>
                    <h2 className="mt-4 text-[28px] leading-[1.15] font-bold tracking-[-0.02em] text-balance text-avana-navy sm:text-4xl lg:text-[42px]">
                        HR Anda Sekarang Punya{' '}
                        <span className="text-avana-blue">AI Assistant</span>
                    </h2>
                    <p className="mt-4 text-[15px] leading-relaxed text-pretty text-avana-text sm:text-[17px]">
                        Bukan sekadar chatbot generik. Jawabannya disusun dari
                        SOP perusahaan yang dipublikasikan, jadi HR dan
                        karyawan tetap mendapat informasi yang sesuai
                        kebijakan internal.
                    </p>
                </Reveal>

                <div className="mt-14 grid items-center gap-10 lg:grid-cols-12 lg:gap-10">
                    <div className="space-y-4 lg:col-span-5">
                        {PILLARS.map((item, i) => (
                            <Reveal
                                key={item.step}
                                delay={i * 0.08}
                                className="rounded-2xl border border-blue-200 bg-white/90 p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-avana-card"
                            >
                                <div className="mb-2.5 flex items-center gap-3.5">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-avana-light text-avana-blue">
                                        <item.icon
                                            className="h-5 w-5"
                                            aria-hidden
                                        />
                                    </span>
                                    <div>
                                        <div className="text-[11px] font-black tracking-wider text-avana-blue uppercase">
                                            {item.step}
                                        </div>
                                        <h3 className="text-base font-bold text-avana-navy">
                                            {item.title}
                                        </h3>
                                    </div>
                                </div>
                                <p className="pl-13.5 text-sm leading-relaxed text-avana-text/85">
                                    {item.desc}
                                </p>
                            </Reveal>
                        ))}

                        <Reveal delay={PILLARS.length * 0.08} className="pt-2">
                            <DemoButton />
                        </Reveal>
                    </div>

                    <Reveal delay={0.15} className="lg:col-span-7">
                        <div className="relative rounded-3xl border border-avana-border bg-white/95 p-3 shadow-avana-hover">
                            <div className="mb-2 flex items-center justify-between border-b border-gray-100 px-4 py-2">
                                <div className="flex items-center gap-2">
                                    <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                                    <span className="text-xs font-bold text-avana-navy">
                                        Tanyakan pada Avana
                                    </span>
                                </div>
                                <span className="rounded-md bg-avana-soft px-2.5 py-0.5 text-[11px] font-medium text-avana-muted">
                                    Online
                                </span>
                            </div>

                            <div className="space-y-3 rounded-2xl border border-gray-100 bg-white p-5">
                                <ul className="space-y-3">
                                    {MESSAGES.map((message, i) => (
                                        <motion.li
                                            key={message.text}
                                            data-reveal
                                            initial={{ opacity: 0, y: 10 }}
                                            whileInView={{
                                                opacity: 1,
                                                y: 0,
                                            }}
                                            viewport={{
                                                once: true,
                                                amount: 0.4,
                                            }}
                                            transition={{
                                                duration: 0.4,
                                                delay: 0.15 * i,
                                            }}
                                            className={
                                                message.from === 'user'
                                                    ? 'ml-auto w-fit max-w-[85%] rounded-2xl rounded-br-sm bg-avana-blue px-4 py-2.5 text-[14px] text-white'
                                                    : 'w-fit max-w-[85%] rounded-2xl rounded-bl-sm bg-avana-soft px-4 py-2.5 text-[14px] text-avana-navy'
                                            }
                                        >
                                            {message.text}
                                        </motion.li>
                                    ))}
                                </ul>

                                <div className="flex items-center gap-2 rounded-full border border-avana-border bg-avana-soft py-2 pr-2 pl-4">
                                    <span className="flex-1 text-[13px] text-avana-muted">
                                        Tulis pertanyaan Anda…
                                    </span>
                                    <span className="grid h-8 w-8 place-items-center rounded-full bg-avana-blue text-white">
                                        <Send
                                            className="h-3.5 w-3.5"
                                            aria-hidden
                                        />
                                    </span>
                                </div>
                            </div>

                            <div className="mt-3 flex items-start gap-2.5 rounded-xl border border-blue-200/80 bg-avana-light/80 p-3.5 text-xs text-avana-navy">
                                <CheckCircle2
                                    className="mt-0.5 h-4 w-4 shrink-0 text-avana-blue"
                                    aria-hidden
                                />
                                <span>
                                    <strong>Sumber terverifikasi:</strong>{' '}
                                    jawaban AI Assistant diambil dari SOP
                                    perusahaan yang dipublikasikan, bukan dari
                                    internet.
                                </span>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
