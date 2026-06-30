import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AiAssistantController from '@/actions/App/Http/Controllers/Avana/AiAssistantController';
import { AIcon, btnOut, C } from '@/lib/avana';

interface ChatMessage {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
}

interface AiProps {
    messages: ChatMessage[];
    model: string | null;
}

const SUGGESTIONS = [
    'Bagaimana cara menjalankan payroll bulanan?',
    'Jelaskan alur pengajuan cuti karyawan',
    'Apa saja modul absensi yang tersedia?',
    'Ringkas cara membuat slip gaji',
];

/** Read a cookie value by name. */
function cookie(name: string): string {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

/** Minimal markdown → HTML for bold, inline code, and line breaks. */
function renderMarkdown(text: string): string {
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    return escaped
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code style="background:rgba(15,23,42,.06);padding:1px 5px;border-radius:5px;font-size:12.5px">$1</code>')
        .replace(/\n/g, '<br/>');
}

export default function AiAssistant({ messages: initial, model }: AiProps) {
    const [messages, setMessages] = useState<ChatMessage[]>(initial);
    const [input, setInput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages]);

    const send = async (text: string) => {
        const message = text.trim();
        if (!message || streaming) {
            return;
        }

        setInput('');
        setStreaming(true);
        setMessages((prev) => [...prev, { role: 'user', content: message }, { role: 'assistant', content: '' }]);

        try {
            const res = await fetch(AiAssistantController.stream().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
                    Accept: 'text/plain',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message }),
            });

            if (!res.ok || !res.body) {
                throw new Error('Gagal menghubungi asisten.');
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();

            for (;;) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }
                const chunk = decoder.decode(value, { stream: true });
                setMessages((prev) => {
                    const next = [...prev];
                    const last = next[next.length - 1];
                    next[next.length - 1] = { ...last, content: last.content + chunk };
                    return next;
                });
            }
        } catch {
            setMessages((prev) => {
                const next = [...prev];
                const last = next[next.length - 1];
                next[next.length - 1] = { ...last, content: (last.content || '') + '\n\n[Terjadi kesalahan. Coba lagi.]' };
                return next;
            });
        } finally {
            setStreaming(false);
        }
    };

    const clearChat = () => {
        router.post(
            AiAssistantController.clear().url,
            {},
            { preserveScroll: true, onSuccess: () => setMessages([]) },
        );
    };

    return (
        <>
            <Head title="AI Assistant" />
            <div style={{ display: 'flex', flexDirection: 'column', height: 'calc(100vh - 64px)', maxWidth: 860, margin: '0 auto', width: '100%' }}>
                {/* header */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '20px 24px 14px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <div style={{ width: 38, height: 38, borderRadius: 11, background: `linear-gradient(135deg,${C.primary},#7c3aed)`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <AIcon name="sparkles" size={19} color="#fff" />
                        </div>
                        <div>
                            <div style={{ fontSize: 17, fontWeight: 600, color: C.navy }}>AI Assistant</div>
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                {model ? `OpenAI · ${model}` : 'Kunci OpenAI belum diatur'}
                            </div>
                        </div>
                    </div>
                    {messages.length > 0 && (
                        <button onClick={clearChat} style={{ ...btnOut, height: 36 }}>
                            <AIcon name="plus" size={15} />
                            Chat Baru
                        </button>
                    )}
                </div>

                {/* messages */}
                <div ref={scrollRef} style={{ flex: 1, overflowY: 'auto', padding: '8px 24px' }}>
                    {messages.length === 0 ? (
                        <div style={{ height: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', textAlign: 'center', gap: 22 }}>
                            <div style={{ width: 60, height: 60, borderRadius: 18, background: `linear-gradient(135deg,${C.primary},#7c3aed)`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <AIcon name="sparkles" size={28} color="#fff" />
                            </div>
                            <div>
                                <div style={{ fontSize: 20, fontWeight: 600, color: C.navy }}>Ada yang bisa dibantu?</div>
                                <div style={{ fontSize: 13.5, color: C.muted, marginTop: 6 }}>Tanya apa saja seputar HR, payroll, absensi, dan modul AvanaHR.</div>
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, maxWidth: 560, width: '100%' }}>
                                {SUGGESTIONS.map((s) => (
                                    <button
                                        key={s}
                                        onClick={() => send(s)}
                                        style={{ textAlign: 'left', padding: '13px 15px', border: `1px solid ${C.border}`, borderRadius: 12, background: '#fff', cursor: 'pointer', fontSize: 13, color: C.text, transition: '.15s' }}
                                    >
                                        {s}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 18, paddingBottom: 12 }}>
                            {messages.map((m, index) => (
                                <div key={m.id ?? `tmp-${index}`} style={{ display: 'flex', gap: 12, flexDirection: m.role === 'user' ? 'row-reverse' : 'row' }}>
                                    <div style={{ flexShrink: 0, width: 32, height: 32, borderRadius: 9, display: 'flex', alignItems: 'center', justifyContent: 'center', background: m.role === 'user' ? 'rgba(47,84,201,.12)' : `linear-gradient(135deg,${C.primary},#7c3aed)` }}>
                                        {m.role === 'user' ? (
                                            <AIcon name="user" size={16} color={C.primary} />
                                        ) : (
                                            <AIcon name="sparkles" size={16} color="#fff" />
                                        )}
                                    </div>
                                    <div
                                        style={{
                                            maxWidth: '78%',
                                            padding: '11px 15px',
                                            borderRadius: 14,
                                            fontSize: 14,
                                            lineHeight: 1.62,
                                            color: m.role === 'user' ? '#fff' : C.text,
                                            background: m.role === 'user' ? C.primary : '#fff',
                                            border: m.role === 'user' ? 'none' : `1px solid ${C.border}`,
                                            wordBreak: 'break-word',
                                        }}
                                    >
                                        {m.role === 'assistant' && m.content === '' && streaming ? (
                                            <span style={{ color: C.faint, display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                <AIcon name="loader" size={14} color={C.muted} /> mengetik…
                                            </span>
                                        ) : (
                                            <span dangerouslySetInnerHTML={{ __html: renderMarkdown(m.content) }} />
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* composer */}
                <div style={{ padding: '12px 24px 22px' }}>
                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10, border: `1px solid ${C.border}`, borderRadius: 16, padding: '8px 8px 8px 16px', background: '#fff', boxShadow: '0 1px 3px rgba(15,23,42,.05)' }}>
                        <textarea
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    send(input);
                                }
                            }}
                            placeholder="Tulis pertanyaan… (Enter kirim, Shift+Enter baris baru)"
                            rows={1}
                            style={{ flex: 1, border: 'none', outline: 'none', resize: 'none', fontSize: 14, color: C.text, maxHeight: 160, lineHeight: 1.5, padding: '7px 0', fontFamily: 'inherit', background: 'transparent' }}
                        />
                        <button
                            onClick={() => send(input)}
                            disabled={streaming || input.trim() === ''}
                            style={{ flexShrink: 0, width: 40, height: 40, borderRadius: 11, border: 'none', background: streaming || input.trim() === '' ? C.border : C.primary, color: '#fff', cursor: streaming || input.trim() === '' ? 'not-allowed' : 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', transition: '.15s' }}
                        >
                            <AIcon name={streaming ? 'loader' : 'arrow-up'} size={18} color="#fff" />
                        </button>
                    </div>
                    <div style={{ textAlign: 'center', fontSize: 11, color: C.faint, marginTop: 8 }}>
                        AI dapat keliru. Verifikasi informasi penting.
                    </div>
                </div>
            </div>
        </>
    );
}
