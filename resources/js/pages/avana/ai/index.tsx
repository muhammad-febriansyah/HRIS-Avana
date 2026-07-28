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

interface SopCitation {
    code: string;
    title: string;
    url: string;
}

interface TokenUsage {
    used: number;
    quota: number | null;
    period: string;
    /** Monthly cap for THIS user; null = only the company pool applies. */
    user_cap: number | null;
    user_used: number;
    user_remaining: number | null;
    /** Company pool left: free monthly quota not yet used, plus the wallet. */
    free_remaining: number;
    wallet_balance: number;
}

interface AiProps {
    conversations: Conversation[];
    activeId: number | null;
    messages: ChatMessage[];
    ready: boolean;
    tokenUsage: TokenUsage;
    sopCitations: SopCitation[];
}

const numberFormatter = new Intl.NumberFormat('id-ID');

const SUGGESTIONS = [
    'Berapa sisa cuti saya tahun ini?',
    'Jelaskan alur pengajuan cuti karyawan',
    'Buatkan draf SOP pengajuan cuti karyawan',
    'Bagaimana cara menjalankan payroll bulanan?',
];

/** Read a cookie value by name. */
function cookie(name: string): string {
    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * WhatsApp-style "sedang mengetik" indicator: three dots bouncing in sequence,
 * shown while the model is thinking and no token has arrived yet.
 */
function TypingDots() {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                color: C.muted,
                height: 18,
            }}
            aria-label="Asisten sedang mengetik"
        >
            {[0, 1, 2].map((index) => (
                <span
                    key={index}
                    className="avn-typing-dot"
                    style={{ animationDelay: `${index * 0.16}s` }}
                />
            ))}
        </span>
    );
}

/** Escape a string for safe use inside a regular expression. */
function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Turn every SOP code the assistant cited into a link to that PDF.
 *
 * Runs on already-escaped HTML: an SOP code is alphanumeric plus dashes, so
 * escaping never rewrites it. Only codes the server sent are matched, and the
 * server sends only what this user may read — so a private SOP can never be
 * linked into someone else's chat.
 */
function linkifyCitations(html: string, citations: SopCitation[]): string {
    return citations.reduce((carry, citation) => {
        if (!citation.code) {
            return carry;
        }

        return carry.replace(
            new RegExp(
                `(?<![\\w-])${escapeRegExp(citation.code)}(?![\\w-])`,
                'g',
            ),
            `<a href="${citation.url}" target="_blank" rel="noopener" title="Unduh ${citation.title}" style="color:${C.primary};font-weight:600;text-decoration:underline">${citation.code}</a>`,
        );
    }, html);
}

/**
 * Images the assistant drew, as `![alt](url)`.
 *
 * Only our own `/storage/ai-images/` path is accepted, and only its path is
 * kept — the host, if the model wrote one, is dropped so the browser resolves
 * it against this origin. A reply is model-controlled text, so a permissive
 * pattern here would let it embed anything from anywhere.
 */
const AI_IMAGE =
    /!\[([^\]]*)\]\((?:https?:\/\/[^/)\s]+)?(\/storage\/ai-images\/[A-Za-z0-9/_.-]+)\)/g;

/** Minimal markdown → HTML for bold, inline code, images, and line breaks. */
function renderMarkdown(text: string, citations: SopCitation[] = []): string {
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    return linkifyCitations(escaped, citations)
        .replace(
            AI_IMAGE,
            (_match, alt: string, path: string) =>
                // A bare <img> leaves saving the picture to right-click, which
                // is no help on a phone, so the drawing carries its own link.
                `<span style="display:block;margin:10px 0">` +
                `<img src="${path}" alt="${alt}" loading="lazy" style="display:block;width:100%;max-width:420px;height:auto;border-radius:10px" />` +
                `<a href="${path}" download style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:5px 11px;border:1px solid ${C.border};border-radius:8px;font-size:12.5px;font-weight:600;color:${C.text};text-decoration:none">` +
                `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>` +
                `Unduh gambar</a>` +
                `</span>`,
        )
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(
            /`([^`]+)`/g,
            '<code style="background:rgba(15,23,42,.06);padding:1px 5px;border-radius:5px;font-size:12.5px">$1</code>',
        )
        .replace(/\n/g, '<br/>');
}

/**
 * Compact monthly AI token meter shown in the chat header.
 *
 * It reports whichever limit actually stops this user. A per-user monthly cap
 * is usually far smaller than the company pool, so showing the pool made the
 * meter read "sisa 486.298" right next to a "batas token Anda habis" reply.
 * The cap wins when one is set; the pool is shown underneath as context.
 */
function TokenMeter({ usage }: { usage: TokenUsage }) {
    const { used, quota, period, user_cap, user_used } = usage;

    const capped = user_cap !== null && user_cap > 0;
    const limit = capped ? user_cap : quota;
    const spent = capped ? user_used : used;
    const hasLimit = limit !== null && limit > 0;

    const percent = hasLimit
        ? Math.min(100, Math.round((spent / (limit as number)) * 100))
        : 0;

    const barColor =
        percent >= 90 ? '#dc2626' : percent >= 70 ? '#f59e0b' : C.primary;

    const poolLeft = usage.free_remaining + usage.wallet_balance;

    return (
        <div
            style={{
                marginLeft: 'auto',
                width: 210,
                flexShrink: 0,
            }}
            title={
                capped
                    ? `Jatah token Anda bulan ${period}. Kuota perusahaan tersisa ${numberFormatter.format(poolLeft)} token.`
                    : `Token AI perusahaan terpakai bulan ${period}`
            }
        >
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    gap: 8,
                    fontSize: 11,
                    color: C.faint,
                    marginBottom: 5,
                }}
            >
                <span
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 5,
                    }}
                >
                    <AIcon name="zap" size={12} color={C.faint} />
                    {capped ? 'Jatah Anda' : 'Token AI'} · {period}
                </span>
                <span style={{ color: C.muted, fontWeight: 600 }}>
                    {numberFormatter.format(spent)}
                    {hasLimit
                        ? ` / ${numberFormatter.format(limit as number)}`
                        : ''}
                </span>
            </div>
            <div
                style={{
                    height: 6,
                    borderRadius: 99,
                    background: 'rgba(15,23,42,.07)',
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        height: '100%',
                        width: hasLimit ? `${percent}%` : '0%',
                        borderRadius: 99,
                        background: barColor,
                        transition: 'width .4s ease',
                    }}
                />
            </div>
            <div
                style={{
                    fontSize: 10,
                    color: C.faint,
                    marginTop: 4,
                    textAlign: 'right',
                }}
            >
                {!hasLimit
                    ? 'Kuota tak terbatas'
                    : percent >= 100
                      ? 'Jatah bulan ini habis'
                      : `Sisa ${numberFormatter.format(Math.max(0, (limit as number) - spent))} token`}
            </div>
        </div>
    );
}

export default function AiAssistant({
    conversations: propConversations,
    activeId: propActiveId,
    messages: propMessages,
    ready,
    tokenUsage,
    sopCitations,
}: AiProps) {
    const [conversations, setConversations] =
        useState<Conversation[]>(propConversations);
    const [activeId, setActiveId] = useState<number | null>(propActiveId);
    const [messages, setMessages] = useState<ChatMessage[]>(propMessages);
    const [input, setInput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    // Tokens land in bursts; these drive the typewriter that drains them at a
    // steady pace so the reply reads as if it were being typed.
    const bufferRef = useRef('');
    const revealedRef = useRef(0);
    const frameRef = useRef<number | null>(null);

    // Re-seed from the server when navigating between conversations.
    useEffect(() => {
        setActiveId(propActiveId);
        setMessages(propMessages);
        setConversations(propConversations);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [propActiveId]);

    useEffect(() => {
        scrollRef.current?.scrollTo({
            top: scrollRef.current.scrollHeight,
            behavior: 'smooth',
        });
    }, [messages]);

    // Stop the typewriter if the component unmounts mid-stream.
    useEffect(
        () => () => {
            if (frameRef.current !== null) {
                cancelAnimationFrame(frameRef.current);
            }
        },
        [],
    );

    /** Write `text` into the assistant bubble currently being streamed. */
    const setLastAssistantContent = (text: string) => {
        setMessages((prev) => {
            const next = [...prev];
            const last = next[next.length - 1];

            if (!last || last.role !== 'assistant') {
                return prev;
            }

            next[next.length - 1] = { ...last, content: text };

            return next;
        });
    };

    /**
     * Reveal buffered tokens a few characters per frame. The step scales with
     * the backlog so a fast model never lags behind, while a slow one still
     * types smoothly instead of pasting whole sentences at once.
     */
    const startTypewriter = () => {
        if (frameRef.current !== null) {
            return;
        }

        const tick = () => {
            const pending = bufferRef.current.length - revealedRef.current;

            if (pending > 0) {
                revealedRef.current += Math.max(1, Math.ceil(pending / 10));
                setLastAssistantContent(
                    bufferRef.current.slice(0, revealedRef.current),
                );
            }

            frameRef.current = requestAnimationFrame(tick);
        };

        frameRef.current = requestAnimationFrame(tick);
    };

    /** Stop the typewriter and show whatever is left in the buffer at once. */
    const stopTypewriter = () => {
        if (frameRef.current !== null) {
            cancelAnimationFrame(frameRef.current);
            frameRef.current = null;
        }

        if (bufferRef.current.length > revealedRef.current) {
            revealedRef.current = bufferRef.current.length;
            setLastAssistantContent(bufferRef.current);
        }
    };

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
        bufferRef.current = '';
        revealedRef.current = 0;
        setMessages((prev) => [
            ...prev,
            { role: 'user', content: message },
            { role: 'assistant', content: '' },
        ]);

        try {
            const res = await fetch(AiAssistantController.stream().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
                    Accept: 'text/plain',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message, conversation_id: activeId }),
            });

            if (!res.ok || !res.body) {
                throw new Error('Gagal menghubungi asisten.');
            }

            const newId = Number(res.headers.get('X-Conversation-Id')) || null;

            if (wasNew && newId) {
                setActiveId(newId);
                setConversations((prev) => [
                    {
                        id: newId,
                        title: message.slice(0, 48),
                        updated_at: 'baru saja',
                    },
                    ...prev,
                ]);
                window.history.replaceState({}, '', '/avana/ai?c=' + newId);
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();

            startTypewriter();

            for (;;) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                bufferRef.current += decoder.decode(value, { stream: true });
            }

            stopTypewriter();
        } catch {
            bufferRef.current += '\n\n[Terjadi kesalahan. Coba lagi.]';
            stopTypewriter();
        } finally {
            setStreaming(false);
            // Refresh only the token meter; local chat state is preserved.
            router.reload({ only: ['tokenUsage'] });
        }
    };

    return (
        <>
            <Head title="AI Assistant" />
            <div
                style={{
                    display: 'flex',
                    height: 'calc(100vh - 64px)',
                    width: '100%',
                }}
            >
                {/* history sidebar */}
                <div
                    style={{
                        width: 262,
                        flexShrink: 0,
                        borderRight: `1px solid ${C.border}`,
                        display: 'flex',
                        flexDirection: 'column',
                        background: '#fff',
                    }}
                >
                    <div style={{ padding: 14 }}>
                        <button
                            onClick={newChat}
                            style={{
                                ...btnOut,
                                width: '100%',
                                justifyContent: 'center',
                                height: 40,
                            }}
                        >
                            <AIcon name="plus" size={16} />
                            Chat Baru
                        </button>
                    </div>
                    <div
                        style={{
                            padding: '0 10px 4px',
                            fontSize: 11,
                            fontWeight: 600,
                            color: C.faint,
                            textTransform: 'uppercase',
                            letterSpacing: '.04em',
                        }}
                    >
                        Riwayat
                    </div>
                    <div
                        style={{
                            flex: 1,
                            overflowY: 'auto',
                            padding: '4px 10px 12px',
                        }}
                    >
                        {conversations.length === 0 ? (
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.faint,
                                    padding: '10px 6px',
                                }}
                            >
                                Belum ada percakapan.
                            </div>
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
                                        background:
                                            c.id === activeId
                                                ? 'rgba(47,84,201,.08)'
                                                : 'transparent',
                                    }}
                                >
                                    <AIcon
                                        name="message-square"
                                        size={14}
                                        color={
                                            c.id === activeId
                                                ? C.primary
                                                : C.faint
                                        }
                                    />
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: 13,
                                                color:
                                                    c.id === activeId
                                                        ? C.primary
                                                        : C.text,
                                                fontWeight:
                                                    c.id === activeId
                                                        ? 600
                                                        : 500,
                                                whiteSpace: 'nowrap',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                            }}
                                        >
                                            {c.title}
                                        </div>
                                        {c.updated_at ? (
                                            <div
                                                style={{
                                                    fontSize: 10.5,
                                                    color: C.faint,
                                                }}
                                            >
                                                {c.updated_at}
                                            </div>
                                        ) : null}
                                    </div>
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            deleteConversation(c.id);
                                        }}
                                        title="Hapus"
                                        style={{
                                            border: '1px solid rgba(220,38,38,.35)',
                                            background: 'rgba(220,38,38,.07)',
                                            borderRadius: 6,
                                            cursor: 'pointer',
                                            padding: 4,
                                            display: 'inline-flex',
                                        }}
                                    >
                                        <AIcon
                                            name="trash-2"
                                            size={13}
                                            color={C.red}
                                        />
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* chat */}
                <div
                    style={{
                        flex: 1,
                        display: 'flex',
                        flexDirection: 'column',
                        maxWidth: 820,
                        margin: '0 auto',
                        width: '100%',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: '20px 24px 14px',
                        }}
                    >
                        <div
                            style={{
                                width: 38,
                                height: 38,
                                borderRadius: 11,
                                background: `linear-gradient(135deg,${C.primary},#7c3aed)`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <AIcon name="sparkles" size={19} color="#fff" />
                        </div>
                        <div>
                            <div
                                style={{
                                    fontSize: 17,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                AI Assistant
                            </div>
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                {ready
                                    ? 'Asisten cerdas AvanaHR'
                                    : 'Asisten belum aktif'}
                            </div>
                        </div>

                        <TokenMeter usage={tokenUsage} />
                    </div>

                    <div
                        ref={scrollRef}
                        style={{
                            flex: 1,
                            overflowY: 'auto',
                            padding: '8px 24px',
                        }}
                    >
                        {messages.length === 0 ? (
                            <div
                                style={{
                                    height: '100%',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    textAlign: 'center',
                                    gap: 22,
                                }}
                            >
                                <div
                                    style={{
                                        width: 60,
                                        height: 60,
                                        borderRadius: 18,
                                        background: `linear-gradient(135deg,${C.primary},#7c3aed)`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name="sparkles"
                                        size={28}
                                        color="#fff"
                                    />
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 20,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        Ada yang bisa dibantu?
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            color: C.muted,
                                            marginTop: 6,
                                        }}
                                    >
                                        Tanya apa saja seputar HR, payroll,
                                        absensi, dan modul AvanaHR.
                                    </div>
                                </div>
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 10,
                                        maxWidth: 560,
                                        width: '100%',
                                    }}
                                >
                                    {SUGGESTIONS.map((s) => (
                                        <button
                                            key={s}
                                            onClick={() => send(s)}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'flex-start',
                                                gap: 9,
                                                textAlign: 'left',
                                                padding: '13px 15px',
                                                border: `1px solid ${C.border}`,
                                                borderRadius: 12,
                                                background: '#fff',
                                                cursor: 'pointer',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    flex: 'none',
                                                    display: 'flex',
                                                    marginTop: 1,
                                                }}
                                            >
                                                <AIcon
                                                    name="sparkles"
                                                    size={15}
                                                    color={C.primary}
                                                />
                                            </span>
                                            <span>{s}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 18,
                                    paddingBottom: 12,
                                }}
                            >
                                {messages.map((m, index) => (
                                    <div
                                        key={m.id ?? `tmp-${index}`}
                                        style={{
                                            display: 'flex',
                                            gap: 12,
                                            flexDirection:
                                                m.role === 'user'
                                                    ? 'row-reverse'
                                                    : 'row',
                                        }}
                                    >
                                        <div
                                            style={{
                                                flexShrink: 0,
                                                width: 32,
                                                height: 32,
                                                borderRadius: 9,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background:
                                                    m.role === 'user'
                                                        ? 'rgba(47,84,201,.12)'
                                                        : `linear-gradient(135deg,${C.primary},#7c3aed)`,
                                            }}
                                        >
                                            <AIcon
                                                name={
                                                    m.role === 'user'
                                                        ? 'user'
                                                        : 'sparkles'
                                                }
                                                size={16}
                                                color={
                                                    m.role === 'user'
                                                        ? C.primary
                                                        : '#fff'
                                                }
                                            />
                                        </div>
                                        <div
                                            style={{
                                                maxWidth: '78%',
                                                padding: '11px 15px',
                                                borderRadius: 14,
                                                fontSize: 14,
                                                lineHeight: 1.62,
                                                color:
                                                    m.role === 'user'
                                                        ? '#fff'
                                                        : C.text,
                                                background:
                                                    m.role === 'user'
                                                        ? C.primary
                                                        : '#fff',
                                                border:
                                                    m.role === 'user'
                                                        ? 'none'
                                                        : `1px solid ${C.border}`,
                                                wordBreak: 'break-word',
                                            }}
                                        >
                                            {m.role === 'assistant' &&
                                            m.content === '' &&
                                            streaming ? (
                                                <TypingDots />
                                            ) : (
                                                <>
                                                    <span
                                                        dangerouslySetInnerHTML={{
                                                            __html: renderMarkdown(
                                                                m.content,
                                                                sopCitations,
                                                            ),
                                                        }}
                                                    />
                                                    {m.role === 'assistant' &&
                                                    streaming &&
                                                    index ===
                                                        messages.length - 1 ? (
                                                        <span
                                                            className="avn-stream-caret"
                                                            style={{
                                                                color: C.muted,
                                                            }}
                                                        />
                                                    ) : null}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div style={{ padding: '12px 24px 22px' }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-end',
                                gap: 10,
                                border: `1px solid ${C.border}`,
                                borderRadius: 16,
                                padding: '8px 8px 8px 16px',
                                background: '#fff',
                                boxShadow: '0 1px 3px rgba(15,23,42,.05)',
                            }}
                        >
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
                                style={{
                                    flex: 1,
                                    border: 'none',
                                    outline: 'none',
                                    resize: 'none',
                                    fontSize: 14,
                                    color: C.text,
                                    maxHeight: 160,
                                    lineHeight: 1.5,
                                    padding: '7px 0',
                                    fontFamily: 'inherit',
                                    background: 'transparent',
                                }}
                            />
                            <button
                                onClick={() => send(input)}
                                disabled={streaming || input.trim() === ''}
                                style={{
                                    flexShrink: 0,
                                    width: 40,
                                    height: 40,
                                    borderRadius: 11,
                                    border: 'none',
                                    background:
                                        streaming || input.trim() === ''
                                            ? C.border
                                            : C.primary,
                                    color: '#fff',
                                    cursor:
                                        streaming || input.trim() === ''
                                            ? 'not-allowed'
                                            : 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon
                                    name={streaming ? 'loader' : 'arrow-up'}
                                    size={18}
                                    color="#fff"
                                />
                            </button>
                        </div>
                        <div
                            style={{
                                textAlign: 'center',
                                fontSize: 11,
                                color: C.faint,
                                marginTop: 8,
                            }}
                        >
                            AI dapat keliru. Verifikasi informasi penting.
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
