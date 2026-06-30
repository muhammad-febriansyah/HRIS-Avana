import { Head, router } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import AiAssistantController from '@/actions/App/Http/Controllers/Avana/AiAssistantController';
import { AIcon, btnP, C, card } from '@/lib/avana';

interface AiIndexProps {
    question: string | null;
    answer: string | null;
}

interface ChatMessage {
    role: 'user' | 'assistant';
    text: string;
}

const WELCOME = 'Halo! Saya asisten HR AvanaHR (mode demo). Tanyakan apa saja tentang payroll, cuti, absensi, atau data karyawan.';

const bubbleBase: CSSProperties = { maxWidth: '72%', padding: '11px 14px', borderRadius: 14, fontSize: 13.5, lineHeight: 1.55, whiteSpace: 'pre-wrap' };

export default function AiIndex({ question, answer }: AiIndexProps) {
    const [messages, setMessages] = useState<ChatMessage[]>(() => {
        const base: ChatMessage[] = [{ role: 'assistant', text: WELCOME }];
        if (question && answer) {
            base.push({ role: 'user', text: question }, { role: 'assistant', text: answer });
        }

        return base;
    });
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages]);

    const send = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const q = input.trim();
        if (!q || sending) {
            return;
        }

        setMessages((prev) => [...prev, { role: 'user', text: q }]);
        setInput('');
        setSending(true);

        router.post(
            AiAssistantController.ask().url,
            { message: q },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const reply = (page.props as { answer?: string | null }).answer;
                    if (reply) {
                        setMessages((prev) => [...prev, { role: 'assistant', text: reply }]);
                    }
                },
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="AI Assistant" />
            <div style={{ padding: '28px 32px', display: 'flex', flexDirection: 'column', height: 'calc(100vh - 64px)' }}>
                {/* Header */}
                <div style={{ marginBottom: 18, flex: 'none' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                        <span>Sistem</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>AI Assistant</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <div style={{ width: 42, height: 42, borderRadius: 11, background: 'linear-gradient(135deg,#2F54C9,#6E9BE6)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <AIcon name="sparkles" size={20} color="#fff" />
                        </div>
                        <div>
                            <h1 style={{ fontSize: 22, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>AI Assistant</h1>
                            <div style={{ fontSize: 13, color: C.muted, marginTop: 2, display: 'flex', alignItems: 'center', gap: 7 }}>
                                <span style={{ display: 'inline-block', padding: '2px 8px', borderRadius: 100, fontSize: 11, fontWeight: 600, color: C.amber, background: 'rgba(217,119,6,.12)' }}>mode demo</span>
                                Jawaban berbasis aturan, tanpa API eksternal.
                            </div>
                        </div>
                    </div>
                </div>

                {/* Chat window */}
                <div style={{ ...card, flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
                    <div ref={scrollRef} style={{ flex: 1, overflowY: 'auto', padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
                        {messages.map((message, idx) => {
                            const isUser = message.role === 'user';

                            return (
                                <div key={idx} style={{ display: 'flex', justifyContent: isUser ? 'flex-end' : 'flex-start', gap: 10, alignItems: 'flex-end' }}>
                                    {!isUser && (
                                        <div style={{ width: 30, height: 30, flex: 'none', borderRadius: 8, background: 'linear-gradient(135deg,#2F54C9,#6E9BE6)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <AIcon name="sparkles" size={15} color="#fff" />
                                        </div>
                                    )}
                                    <div
                                        style={{
                                            ...bubbleBase,
                                            background: isUser ? C.primary : C.surface,
                                            color: isUser ? '#fff' : C.text,
                                            borderBottomRightRadius: isUser ? 4 : 14,
                                            borderBottomLeftRadius: isUser ? 14 : 4,
                                        }}
                                    >
                                        {message.text}
                                    </div>
                                </div>
                            );
                        })}
                        {sending && (
                            <div style={{ display: 'flex', justifyContent: 'flex-start', gap: 10, alignItems: 'flex-end' }}>
                                <div style={{ width: 30, height: 30, flex: 'none', borderRadius: 8, background: 'linear-gradient(135deg,#2F54C9,#6E9BE6)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name="sparkles" size={15} color="#fff" />
                                </div>
                                <div style={{ ...bubbleBase, background: C.surface, color: C.muted }}>Mengetik…</div>
                            </div>
                        )}
                    </div>

                    {/* Composer */}
                    <form onSubmit={send} style={{ borderTop: `1px solid ${C.border}`, padding: 14, display: 'flex', gap: 10, flex: 'none' }}>
                        <input
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Tulis pertanyaan… (cth: bagaimana cara menjalankan payroll?)"
                            style={{ flex: 1, height: 44, padding: '0 16px', border: `1px solid ${C.border}`, borderRadius: 10, fontSize: 13.5, color: C.text, outline: 'none', background: '#fff' }}
                        />
                        <button type="submit" disabled={sending || input.trim() === ''} style={{ ...btnP, height: 44, opacity: sending || input.trim() === '' ? 0.6 : 1, cursor: sending || input.trim() === '' ? 'not-allowed' : 'pointer' }}>
                            <AIcon name="send" size={16} color="#fff" />
                            Kirim
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
