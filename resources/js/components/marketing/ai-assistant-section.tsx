import { FileText, SendHorizonal, Sparkles } from 'lucide-react';
import { motion } from 'motion/react';
import { DemoButton } from './cta-buttons';
import { Container, Reveal } from './reveal';

const MESSAGES: { from: 'user' | 'ai'; text: string }[] = [
    { from: 'user', text: 'Bagaimana prosedur reimbursement?' },
    {
        from: 'ai',
        text: 'Jawaban disusun dari SOP perusahaan yang dipublikasikan di AvanaHR.',
    },
    { from: 'user', text: 'Apa aturan SOP perusahaan terkait proses ini?' },
];

/**
 * AI assistant — the one section allowed to look a little different. The mock
 * conversation only claims what the product does: answer from published SOP
 * documents.
 */
export function AiAssistantSection() {
    return (
        <section
            id="ai"
            className="relative scroll-mt-28 overflow-hidden bg-[#0E1A3A] py-20 lg:py-28"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute -top-32 right-[-10%] h-[420px] w-[560px] rounded-full bg-[radial-gradient(closest-side,rgba(47,84,201,0.35),transparent)]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute -bottom-40 left-[-8%] h-[380px] w-[520px] rounded-full bg-[radial-gradient(closest-side,rgba(110,155,230,0.18),transparent)]"
            />

            <Container className="relative">
                <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <Reveal>
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1.5 text-[12px] font-semibold tracking-[0.08em] text-white/85 uppercase">
                                <Sparkles className="h-3.5 w-3.5" aria-hidden />
                                AI Assistant
                            </span>
                            <h2 className="mt-6 text-[28px] leading-[1.15] font-bold tracking-[-0.02em] text-balance text-white sm:text-4xl lg:text-[42px]">
                                HR Anda Sekarang Punya AI Assistant.
                            </h2>
                            <p className="mt-4 max-w-xl text-[15px] leading-relaxed text-white/70 sm:text-[17px]">
                                SOP perusahaan yang dipublikasikan dapat dibaca
                                karyawan dan digunakan sebagai sumber jawaban AI
                                Assistant.
                            </p>
                            <div className="mt-8">
                                <DemoButton
                                    variant="inverse"
                                    withArrow={false}
                                />
                            </div>
                        </Reveal>
                    </div>

                    <Reveal delay={0.1}>
                        <div className="rounded-3xl border border-white/10 bg-white/[0.06] p-4 shadow-[0_32px_72px_-32px_rgba(3,10,32,0.8)] backdrop-blur-sm sm:p-6">
                            <div className="flex items-center gap-2.5 border-b border-white/10 pb-4">
                                <span className="grid h-9 w-9 place-items-center rounded-full bg-[#2F54C9] text-white">
                                    <Sparkles className="h-4 w-4" aria-hidden />
                                </span>
                                <span className="text-[14px] font-medium text-white">
                                    Tanyakan pada Avana
                                </span>
                                <span className="ml-auto inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] text-white/70">
                                    <span
                                        aria-hidden
                                        className="h-1.5 w-1.5 rounded-full bg-[#4ADE80]"
                                    />
                                    Online
                                </span>
                            </div>

                            <ul className="mt-4 space-y-3">
                                {MESSAGES.map((message, i) => (
                                    <motion.li
                                        key={message.text}
                                        data-reveal
                                        initial={{ opacity: 0, y: 10 }}
                                        whileInView={{ opacity: 1, y: 0 }}
                                        viewport={{ once: true, amount: 0.4 }}
                                        transition={{
                                            duration: 0.4,
                                            delay: 0.15 * i,
                                        }}
                                        className={
                                            message.from === 'user'
                                                ? 'ml-auto w-fit max-w-[85%] rounded-2xl rounded-br-sm bg-[#2F54C9] px-4 py-2.5 text-[14px] text-white'
                                                : 'w-fit max-w-[85%] rounded-2xl rounded-bl-sm bg-white/10 px-4 py-2.5 text-[14px] text-white/85'
                                        }
                                    >
                                        {message.text}
                                    </motion.li>
                                ))}
                                <motion.li
                                    data-reveal
                                    initial={{ opacity: 0 }}
                                    whileInView={{ opacity: 1 }}
                                    viewport={{ once: true, amount: 0.4 }}
                                    transition={{
                                        duration: 0.4,
                                        delay: 0.15 * MESSAGES.length,
                                    }}
                                    className="flex w-fit items-center gap-1.5 rounded-2xl rounded-bl-sm bg-white/10 px-4 py-3"
                                    aria-label="AI sedang mengetik"
                                >
                                    {[0, 1, 2].map((dot) => (
                                        <span
                                            key={dot}
                                            aria-hidden
                                            className="avn-typing-dot text-white/60"
                                            style={{
                                                animationDelay: `${dot * 0.15}s`,
                                            }}
                                        />
                                    ))}
                                </motion.li>
                            </ul>

                            <div className="mt-5 flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3.5 py-3">
                                <FileText
                                    className="h-4 w-4 shrink-0 text-white/60"
                                    aria-hidden
                                />
                                <span className="text-[12.5px] text-white/60">
                                    Sumber: SOP perusahaan yang dipublikasikan
                                </span>
                            </div>

                            <div className="mt-3 flex items-center gap-2 rounded-full border border-white/10 bg-white/5 py-2 pr-2 pl-4">
                                <span className="flex-1 text-[13px] text-white/40">
                                    Tulis pertanyaan Anda…
                                </span>
                                <span className="grid h-8 w-8 place-items-center rounded-full bg-[#2F54C9] text-white">
                                    <SendHorizonal
                                        className="h-3.5 w-3.5"
                                        aria-hidden
                                    />
                                </span>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
