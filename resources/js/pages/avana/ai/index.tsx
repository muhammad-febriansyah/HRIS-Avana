import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AiAssistantController from '@/actions/App/Http/Controllers/Avana/AiAssistantController';
import { AIcon, btnOut, C } from '@/lib/avana';

interface ChatMessage {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
}

interface Conversation {
    id: number;
    title: string;
    updated_at: string | null;
}

interface AiProps {
    conversations: Conversation[];
    activeId: number | null;
    messages: ChatMessage[];
    ready: boolean;
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
    const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return escaped
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code style="background:rgba(15,23,42,.06);padding:1px 5px;border-radius:5px;font-size:12.5px">$1</code>')
        .replace(/\n/g, '<br/>');
}

export default function AiAssistant({ conversations: propConversations, activeId: propActiveId, messages: propMessages, ready }: AiProps) {
    const [conversations, setConversations] = useState<Conversation[]>(propConversations);
    const [activeId, setActiveId] = useState<number | null>(propActiveId);
    const [messages, setMessages] = useState<ChatMessage[]>(propMessages);
    const [input, setInput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    // Re-seed from the server when navigating between conversations.
    useEffect(() => {
        setActiveId(propActiveId);
        setMessages(propMessages);
        setConversations(propConversations);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [propActiveId]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages]);

    const newChat = () => {
        setActiveId(null);
        setMessages([]);
        setInput('');
        window.history.replaceState({}, '', '/avana/ai');
    };

    const openConversation = (id: number) => {
        if (id === activeId || streaming) {
            return;
        }
        router.visit('/avana/ai?c=' + id, { preserveScroll: true });
    };

    const deleteConversation = (id: number) => {
        router.delete(AiAssistantController.destroyConversation(id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setConversations((prev) => prev.filter((c) => c.id !== id));
                if (id === activeId) {
                    newChat();
                }
            },
        });
    };

    const send = async (text: string) => {
        const message = text.trim();
        if (!message || streaming) {
            return;
        }

        const wasNew = activeId === null;
        setInput('');
        setStreaming(true);
        setMessages((prev) => [...prev, { role: 'user', content: message }, { role: 'assistant', content: '' }]);

        try {
            const res = await fetch(AiAssistantController.stream().url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': cookie('XSRF-TOKEN'), Accept: 'text/plain' },
                credentials: 'same-origin',
                body: JSON.stringify({ message, conversation_id: activeId }),
            });

            if (!res.ok || !res.body) {
                throw new Error('Gagal menghubungi asisten.');
            }

            const newId = Number(res.headers.get('X-Conversation-Id')) || null;
            if (wasNew && newId) {
                setActiveId(newId);
                setConversations((prev) => [{ id: newId, title: message.slice(0, 48), updated_at: 'baru saja' }, ...prev]);
                window.history.replaceState({}, '', '/avana/ai?c=' + newId);
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

    return (
        <>
            <Head title="AI Assistant" />
            <div style={{ display: 'flex', height: 'calc(100vh - 64px)', width: '100%' }}>
                {/* history sidebar */}
                <div style={{ width: 262, flexShrink: 0, borderRight: `1px solid ${C.border}`, display: 'flex', flexDirection: 'column', background: '#fff' }}>
                    <div style={{ padding: 14 }}>
                        <button onClick={newChat} style={{ ...btnOut, width: '100%', justifyContent: 'center', height: 40 }}>
                            <AIcon name="plus" size={16} />
                            Chat Baru
                        </button>
                    </div>
                    <div style={{ padding: '0 10px 4px', fontSize: 11, fontWeight: 600, color: C.faint, textTransform: 'uppercase', letterSpacing: '.04em' }}>Riwayat</div>
                    <div style={{ flex: 1, overflowY: 'auto', padding: '4px 10px 12px' }}>
                        {conversations.length === 0 ? (
                            <div style={{ fontSize: 12.5, color: C.faint, padding: '10px 6px' }}>Belum ada percakapan.</div>
                        ) : (
                            conversations.map((c) => (
                                <div
                                    key={c.id}
                                    onClick={() => openConversation(c.id)}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        padding: '9px 10px',
                                        borderRadius: 9,
                                        cursor: 'pointer',
                                        marginBottom: 2,
                                        background: c.id === activeId ? 'rgba(47,84,201,.08)' : 'transparent',
                                    }}
                                >
                                    <AIcon name="message-square" size={14} color={c.id === activeId ? C.primary : C.faint} />
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 13, color: c.id === activeId ? C.primary : C.text, fontWeight: c.id === activeId ? 600 : 500, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                            {c.title}
                                        </div>
                                        {c.updated_at ? <div style={{ fontSize: 10.5, color: C.faint }}>{c.updated_at}</div> : null}
                                    </div>
                                    <button
                                        onClick={(e) => { e.stopPropagation(); deleteConversation(c.id); }}
                                        title="Hapus"
                                        style={{ border: 'none', background: 'transparent', cursor: 'pointer', padding: 3, display: 'inline-flex' }}
                                    >
                                        <AIcon name="trash-2" size={13} color={C.faint} />
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* chat */}
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', maxWidth: 820, margin: '0 auto', width: '100%' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '20px 24px 14px' }}>
                        <div style={{ width: 38, height: 38, borderRadius: 11, background: `linear-gradient(135deg,${C.primary},#7c3aed)`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <AIcon name="sparkles" size={19} color="#fff" />
                        </div>
                        <div>
                            <div style={{ fontSize: 17, fontWeight: 600, color: C.navy }}>AI Assistant</div>
                            <div style={{ fontSize: 11.5, color: C.faint }}>{ready ? 'Asisten cerdas AvanaHR' : 'Asisten belum aktif'}</div>
                        </div>
                    </div>

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
                                        <button key={s} onClick={() => send(s)} style={{ textAlign: 'left', padding: '13px 15px', border: `1px solid ${C.border}`, borderRadius: 12, background: '#fff', cursor: 'pointer', fontSize: 13, color: C.text }}>
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
                                            <AIcon name={m.role === 'user' ? 'user' : 'sparkles'} size={16} color={m.role === 'user' ? C.primary : '#fff'} />
                                        </div>
                                        <div style={{ maxWidth: '78%', padding: '11px 15px', borderRadius: 14, fontSize: 14, lineHeight: 1.62, color: m.role === 'user' ? '#fff' : C.text, background: m.role === 'user' ? C.primary : '#fff', border: m.role === 'user' ? 'none' : `1px solid ${C.border}`, wordBreak: 'break-word' }}>
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
                                style={{ flexShrink: 0, width: 40, height: 40, borderRadius: 11, border: 'none', background: streaming || input.trim() === '' ? C.border : C.primary, color: '#fff', cursor: streaming || input.trim() === '' ? 'not-allowed' : 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                            >
                                <AIcon name={streaming ? 'loader' : 'arrow-up'} size={18} color="#fff" />
                            </button>
                        </div>
                        <div style={{ textAlign: 'center', fontSize: 11, color: C.faint, marginTop: 8 }}>AI dapat keliru. Verifikasi informasi penting.</div>
                    </div>
                </div>
            </div>
        </>
    );
}
