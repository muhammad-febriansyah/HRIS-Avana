import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, C } from '@/lib/avana';
import { EmptyState, PageHeader, PageShell, Panel } from './components';

interface CategoryRow {
    id: number;
    name: string;
    icon: string | null;
    color: string | null;
}

interface ReplyRow {
    id: number;
    body: string;
    author: string;
    author_photo: string | null;
    is_mine: boolean;
    parent_id: number | null;
    created_at: string | null;
}

interface CommentRow extends ReplyRow {
    replies: ReplyRow[];
}

interface PostRow {
    id: number;
    body: string;
    image_url: string | null;
    likes_count: number;
    comments_count: number;
    liked: boolean;
    is_mine: boolean;
    author: string;
    category: string | null;
    category_icon: string | null;
    category_color: string | null;
    author_photo: string | null;
    created_at: string | null;
    edited: boolean;
}

interface LeaderRow {
    rank: number;
    employee_id: number;
    name: string;
    photo: string | null;
    posts: number;
    likes: number;
    comments: number;
    points: number;
    is_me: boolean;
}

interface SayaSosmedProps {
    posts: {
        data: PostRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    categories: CategoryRow[];
    leaderboard: LeaderRow[];
    weights: Record<string, number>;
    me: { name: string; photo: string | null };
    filters: { category: number | null };
}

interface ComposeData {
    body: string;
    social_category_id: string;
    image: File | null;
    [key: string]: string | File | null;
}

type ComposeForm = InertiaFormProps<ComposeData>;

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

/** "2 jam lalu" — the wall reads as a stream, so absolute dates are noise. */
function timeAgo(value: string | null): string {
    if (!value) {
        return '';
    }

    const then = new Date(value.replace(' ', 'T')).getTime();
    const minutes = Math.max(0, Math.round((Date.now() - then) / 60000));

    if (minutes < 1) {
        return 'baru saja';
    }

    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours} jam lalu`;
    }

    const days = Math.round(hours / 24);

    if (days < 7) {
        return `${days} hari lalu`;
    }

    return new Date(value.replace(' ', 'T')).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** KB below a megabyte — "0.0 MB" reads as if nothing was attached. */
function fileSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function initials(name: string): string {
    return (
        name
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((word) => word.charAt(0).toUpperCase())
            .join('') || '?'
    );
}

function Avatar({
    name,
    photo = null,
    size = 38,
}: {
    name: string;
    photo?: string | null;
    size?: number;
}) {
    if (photo) {
        return (
            <img
                src={photo}
                alt={name}
                style={{
                    width: size,
                    height: size,
                    borderRadius: '50%',
                    objectFit: 'cover',
                    flexShrink: 0,
                }}
            />
        );
    }

    return (
        <div
            style={{
                width: size,
                height: size,
                borderRadius: '50%',
                background: C.surface,
                color: C.primary,
                fontSize: size * 0.36,
                fontWeight: 600,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
            }}
        >
            {initials(name)}
        </div>
    );
}

/** A comment or reply line. Replies are the same shape, one indent deeper. */
function CommentLine({
    entry,
    onReply,
    onRemoved,
}: {
    entry: ReplyRow;
    onReply?: () => void;
    onRemoved: () => void;
}) {
    const remove = () => {
        if (!window.confirm('Hapus komentar ini?')) {
            return;
        }

        router.delete(`/avana/saya/sosmed/komentar/${entry.id}`, {
            preserveScroll: true,
            onSuccess: onRemoved,
        });
    };

    return (
        <div style={{ display: 'flex', gap: 9, alignItems: 'flex-start' }}>
            <Avatar name={entry.author} photo={entry.author_photo} size={26} />
            <div style={{ flex: 1, minWidth: 0 }}>
                <div
                    style={{
                        fontSize: 12.5,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    {entry.author}
                </div>
                <div
                    style={{
                        fontSize: 13,
                        color: C.text,
                        lineHeight: 1.55,
                        whiteSpace: 'pre-wrap',
                    }}
                >
                    {entry.body}
                </div>
                <div style={{ display: 'flex', gap: 12, marginTop: 3 }}>
                    {onReply && (
                        <button
                            type="button"
                            onClick={onReply}
                            style={{
                                border: 'none',
                                background: 'none',
                                padding: 0,
                                cursor: 'pointer',
                                fontSize: 11.5,
                                color: C.muted,
                            }}
                        >
                            Balas
                        </button>
                    )}
                    {entry.is_mine && (
                        <button
                            type="button"
                            onClick={remove}
                            style={{
                                border: 'none',
                                background: 'none',
                                padding: 0,
                                cursor: 'pointer',
                                fontSize: 11.5,
                                color: C.muted,
                            }}
                        >
                            Hapus
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * Compose in a modal rather than a permanent block at the top of the feed: the
 * wall is mostly for reading, and the inline form pushed every post down while
 * sitting empty. The image goes through a real drop zone with a preview — the
 * bare "Choose File" control gave no way to tell what was attached.
 */
function ComposeModal({
    categories,
    form,
    onClose,
    onSubmit,
}: {
    categories: CategoryRow[];
    form: ComposeForm;
    onClose: () => void;
    onSubmit: (event: FormEvent) => void;
}) {
    const [preview, setPreview] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    // Object URLs are revoked on replacement/unmount; leaking one per pick would
    // hold the whole image in memory for the life of the page.
    const attach = (file: File | null) => {
        setPreview((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return file ? URL.createObjectURL(file) : null;
        });
        form.setData('image', file);
    };

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const image = form.data.image;
    const overLimit = form.data.body.length > 500;

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <form
                onSubmit={onSubmit}
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 560,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '18px 22px',
                        borderBottom: `1px solid ${C.line}`,
                    }}
                >
                    <div
                        style={{ fontSize: 17, fontWeight: 600, color: C.navy }}
                    >
                        Tulis sesuatu
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup"
                        style={{
                            border: 'none',
                            background: 'none',
                            cursor: 'pointer',
                            padding: 4,
                            lineHeight: 0,
                        }}
                    >
                        <AIcon name="x" size={18} color={C.muted} />
                    </button>
                </div>

                <div
                    style={{
                        padding: 22,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    <div>
                        <textarea
                            name="body"
                            aria-label="Isi postingan"
                            autoFocus
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            placeholder="Bagikan ide, cerita, atau apresiasi…"
                            rows={5}
                            style={{
                                width: '100%',
                                padding: '12px 14px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 10,
                                fontSize: 14,
                                color: C.text,
                                resize: 'vertical',
                                outline: 'none',
                                fontFamily: 'inherit',
                                lineHeight: 1.6,
                            }}
                        />
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                marginTop: 6,
                                fontSize: 12,
                            }}
                        >
                            <span style={{ color: C.red }}>
                                {form.errors.body ?? ''}
                            </span>
                            <span
                                style={{ color: overLimit ? C.red : C.faint }}
                            >
                                {form.data.body.length}/500
                            </span>
                        </div>
                    </div>

                    <div>
                        <div
                            style={{
                                fontSize: 12.5,
                                fontWeight: 600,
                                color: C.muted,
                                marginBottom: 8,
                            }}
                        >
                            Kategori
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            {[
                                { id: '', name: 'Tanpa kategori', color: null },
                                ...categories,
                            ].map((category) => {
                                const value = String(category.id);
                                const selected =
                                    form.data.social_category_id === value;
                                const accent = category.color ?? C.muted;

                                return (
                                    <button
                                        key={value || 'none'}
                                        type="button"
                                        onClick={() =>
                                            form.setData(
                                                'social_category_id',
                                                value,
                                            )
                                        }
                                        style={{
                                            height: 32,
                                            padding: '0 14px',
                                            borderRadius: 999,
                                            border: 'none',
                                            background: selected
                                                ? accent
                                                : C.surface,
                                            color: selected ? '#fff' : C.muted,
                                            fontSize: 12.5,
                                            fontWeight: 600,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        {category.name}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div>
                        <div
                            style={{
                                fontSize: 12.5,
                                fontWeight: 600,
                                color: C.muted,
                                marginBottom: 8,
                            }}
                        >
                            Foto (opsional)
                        </div>

                        {image && preview ? (
                            <div
                                style={{
                                    position: 'relative',
                                    borderRadius: 12,
                                    overflow: 'hidden',
                                }}
                            >
                                <img
                                    src={preview}
                                    alt={image.name}
                                    style={{
                                        width: '100%',
                                        maxHeight: 260,
                                        objectFit: 'cover',
                                        display: 'block',
                                    }}
                                />
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        padding: '10px 12px',
                                        background: C.surface,
                                        fontSize: 12.5,
                                        color: C.muted,
                                    }}
                                >
                                    <AIcon
                                        name="image"
                                        size={15}
                                        color={C.muted}
                                    />
                                    <span
                                        style={{
                                            flex: 1,
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {image.name} · {fileSize(image.size)}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => attach(null)}
                                        style={{
                                            border: 'none',
                                            background: 'none',
                                            cursor: 'pointer',
                                            color: C.red,
                                            fontSize: 12.5,
                                            fontWeight: 600,
                                            padding: 0,
                                        }}
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div
                                onClick={() => fileInput.current?.click()}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    setDragging(false);
                                    attach(
                                        event.dataTransfer.files?.[0] ?? null,
                                    );
                                }}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: 6,
                                    padding: '26px 16px',
                                    borderRadius: 12,
                                    border: `1.5px dashed ${dragging ? C.primary : C.border}`,
                                    background: dragging
                                        ? `${C.primary}0A`
                                        : C.surface,
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon
                                    name="image-plus"
                                    size={22}
                                    color={dragging ? C.primary : C.faint}
                                />
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: C.text,
                                        fontWeight: 600,
                                    }}
                                >
                                    Seret foto ke sini, atau klik untuk memilih
                                </div>
                                <div style={{ fontSize: 11.5, color: C.faint }}>
                                    JPG, PNG, atau WEBP · maksimal 5 MB
                                </div>
                            </div>
                        )}

                        <input
                            ref={fileInput}
                            type="file"
                            name="image"
                            aria-label="Foto"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(event) =>
                                attach(event.target.files?.[0] ?? null)
                            }
                            style={{ display: 'none' }}
                        />
                        {form.errors.image && (
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.red,
                                    marginTop: 6,
                                }}
                            >
                                {form.errors.image}
                            </div>
                        )}
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 10,
                        justifyContent: 'flex-end',
                        padding: '16px 22px',
                        borderTop: `1px solid ${C.line}`,
                    }}
                >
                    <button
                        type="button"
                        onClick={onClose}
                        style={{
                            height: 42,
                            padding: '0 20px',
                            borderRadius: 10,
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            color: C.muted,
                            fontSize: 13.5,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={
                            form.processing || form.data.body.trim() === ''
                        }
                        style={{
                            height: 42,
                            padding: '0 24px',
                            borderRadius: 10,
                            border: 'none',
                            background: C.primary,
                            color: '#fff',
                            fontSize: 13.5,
                            fontWeight: 600,
                            cursor:
                                form.processing || form.data.body.trim() === ''
                                    ? 'default'
                                    : 'pointer',
                            opacity:
                                form.processing || form.data.body.trim() === ''
                                    ? 0.55
                                    : 1,
                        }}
                    >
                        {form.processing ? 'Mengirim…' : 'Posting'}
                    </button>
                </div>
            </form>
        </div>
    );
}

export default function SayaSosmed({
    posts,
    categories,
    leaderboard,
    weights,
    me,
    filters,
}: SayaSosmedProps) {
    const { flash } = usePage<FlashProps>().props;

    const compose = useForm<ComposeData>({
        body: '',
        social_category_id: '',
        image: null,
    });
    const [composeOpen, setComposeOpen] = useState(false);

    const [openThread, setOpenThread] = useState<number | null>(null);
    const [draft, setDraft] = useState('');
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const [comments, setComments] = useState<Record<number, CommentRow[]>>({});
    const activeCategory = filters.category;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submitPost = (event: FormEvent) => {
        event.preventDefault();
        compose.post('/avana/saya/sosmed', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                compose.reset();
                setComposeOpen(false);
            },
        });
    };

    const filterBy = (categoryId: number | null) => {
        router.get(
            '/avana/saya/sosmed',
            categoryId ? { category: categoryId } : {},
            { preserveScroll: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            '/avana/saya/sosmed',
            { ...(activeCategory ? { category: activeCategory } : {}), page },
            { preserveScroll: true },
        );
    };

    const loadComments = async (postId: number) => {
        const response = await fetch(`/avana/saya/sosmed/${postId}/komentar`, {
            headers: { Accept: 'application/json' },
        });
        const payload = (await response.json()) as { data: CommentRow[] };

        setComments((current) => ({ ...current, [postId]: payload.data }));
    };

    const sendComment = (postId: number) => {
        const body = draft.trim();

        if (!body) {
            return;
        }

        router.post(
            `/avana/saya/sosmed/${postId}/komentar`,
            { body, parent_id: replyTo },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDraft('');
                    setReplyTo(null);
                    void loadComments(postId);
                },
            },
        );
    };

    const deletePost = (postId: number) => {
        if (!window.confirm('Hapus postingan ini? Tindakan ini permanen.')) {
            return;
        }

        router.delete(`/avana/saya/sosmed/${postId}`, { preserveScroll: true });
    };

    const reportPost = (postId: number) => {
        const reason = window.prompt(
            'Laporkan postingan ini ke HR. Alasan (opsional):',
        );

        if (reason === null) {
            return;
        }

        router.post(
            `/avana/saya/sosmed/${postId}/lapor`,
            { reason },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Ruang Kita" />
            {composeOpen && (
                <ComposeModal
                    categories={categories}
                    form={compose}
                    onClose={() => setComposeOpen(false)}
                    onSubmit={submitPost}
                />
            )}
            <PageShell>
                <PageHeader
                    title="Ruang Kita"
                    subtitle="Tempat berbagi ide, cerita, dan apresiasi antar rekan. Terhubung langsung dengan yang ada di aplikasi."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1.7fr) minmax(0, 1fr)',
                        gap: 16,
                        alignItems: 'start',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <Panel>
                            <button
                                type="button"
                                onClick={() => setComposeOpen(true)}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                    width: '100%',
                                    border: 'none',
                                    background: 'none',
                                    padding: 0,
                                    cursor: 'pointer',
                                    textAlign: 'left',
                                }}
                            >
                                <Avatar name={me.name} photo={me.photo} />
                                <span
                                    style={{
                                        flex: 1,
                                        height: 42,
                                        display: 'flex',
                                        alignItems: 'center',
                                        padding: '0 14px',
                                        borderRadius: 999,
                                        background: C.surface,
                                        color: C.faint,
                                        fontSize: 13.5,
                                    }}
                                >
                                    Bagikan ide, cerita, atau apresiasi…
                                </span>
                                <span
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 7,
                                        height: 42,
                                        padding: '0 18px',
                                        borderRadius: 10,
                                        background: C.primary,
                                        color: '#fff',
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                    }}
                                >
                                    <AIcon
                                        name="pencil"
                                        size={15}
                                        color="#fff"
                                    />
                                    Tulis
                                </span>
                            </button>
                        </Panel>

                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            {[{ id: null, name: 'Semua' }, ...categories].map(
                                (category) => {
                                    const selected =
                                        activeCategory ===
                                        (category.id as number | null);

                                    return (
                                        <button
                                            key={category.id ?? 'all'}
                                            type="button"
                                            onClick={() =>
                                                filterBy(
                                                    category.id as
                                                        number | null,
                                                )
                                            }
                                            style={{
                                                height: 32,
                                                padding: '0 14px',
                                                borderRadius: 999,
                                                border: 'none',
                                                background: selected
                                                    ? C.primary
                                                    : C.surface,
                                                color: selected
                                                    ? '#fff'
                                                    : C.muted,
                                                fontSize: 12.5,
                                                fontWeight: 600,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            {category.name}
                                        </button>
                                    );
                                },
                            )}
                        </div>

                        {posts.data.length === 0 ? (
                            <Panel padded={false}>
                                <EmptyState
                                    icon="message-circle"
                                    message="Belum ada postingan. Jadilah yang pertama!"
                                />
                            </Panel>
                        ) : (
                            posts.data.map((post) => (
                                <Panel key={post.id}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 11,
                                            alignItems: 'flex-start',
                                        }}
                                    >
                                        <Avatar
                                            name={post.author}
                                            photo={post.author_photo}
                                        />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 8,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {post.author}
                                                </span>
                                                {post.category && (
                                                    <span
                                                        style={{
                                                            fontSize: 11,
                                                            fontWeight: 600,
                                                            padding: '2px 8px',
                                                            borderRadius: 999,
                                                            color:
                                                                post.category_color ??
                                                                C.primary,
                                                            background: `${post.category_color ?? C.primary}14`,
                                                        }}
                                                    >
                                                        {post.category}
                                                    </span>
                                                )}
                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {timeAgo(post.created_at)}
                                                    {post.edited && ' · diedit'}
                                                </span>
                                            </div>

                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    color: C.text,
                                                    lineHeight: 1.6,
                                                    marginTop: 6,
                                                    whiteSpace: 'pre-wrap',
                                                }}
                                            >
                                                {post.body}
                                            </div>

                                            {post.image_url && (
                                                <img
                                                    src={post.image_url}
                                                    alt=""
                                                    style={{
                                                        marginTop: 10,
                                                        maxWidth: '100%',
                                                        borderRadius: 10,
                                                        display: 'block',
                                                    }}
                                                />
                                            )}

                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 16,
                                                    alignItems: 'center',
                                                    marginTop: 12,
                                                }}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            `/avana/saya/sosmed/${post.id}/suka`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 6,
                                                        border: 'none',
                                                        background: 'none',
                                                        padding: 0,
                                                        cursor: 'pointer',
                                                        fontSize: 12.5,
                                                        color: post.liked
                                                            ? C.red
                                                            : C.muted,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="heart"
                                                        size={16}
                                                        color={
                                                            post.liked
                                                                ? C.red
                                                                : C.muted
                                                        }
                                                    />
                                                    {post.likes_count}
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        const next =
                                                            openThread ===
                                                            post.id
                                                                ? null
                                                                : post.id;

                                                        setOpenThread(next);
                                                        setDraft('');
                                                        setReplyTo(null);

                                                        if (next !== null) {
                                                            void loadComments(
                                                                post.id,
                                                            );
                                                        }
                                                    }}
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 6,
                                                        border: 'none',
                                                        background: 'none',
                                                        padding: 0,
                                                        cursor: 'pointer',
                                                        fontSize: 12.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="message-circle"
                                                        size={16}
                                                        color={C.muted}
                                                    />
                                                    {post.comments_count}
                                                </button>

                                                <div style={{ flex: 1 }} />

                                                {post.is_mine ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            deletePost(post.id)
                                                        }
                                                        style={{
                                                            border: 'none',
                                                            background: 'none',
                                                            padding: 0,
                                                            cursor: 'pointer',
                                                            fontSize: 12.5,
                                                            color: C.muted,
                                                        }}
                                                    >
                                                        Hapus
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            reportPost(post.id)
                                                        }
                                                        style={{
                                                            border: 'none',
                                                            background: 'none',
                                                            padding: 0,
                                                            cursor: 'pointer',
                                                            fontSize: 12.5,
                                                            color: C.muted,
                                                        }}
                                                    >
                                                        Laporkan
                                                    </button>
                                                )}
                                            </div>

                                            {openThread === post.id && (
                                                <div
                                                    style={{
                                                        marginTop: 12,
                                                        paddingTop: 12,
                                                        borderTop: `1px solid ${C.line}`,
                                                        display: 'flex',
                                                        flexDirection: 'column',
                                                        gap: 12,
                                                    }}
                                                >
                                                    {(
                                                        comments[post.id] ?? []
                                                    ).map((comment) => (
                                                        <div
                                                            key={comment.id}
                                                            style={{
                                                                display: 'flex',
                                                                flexDirection:
                                                                    'column',
                                                                gap: 10,
                                                            }}
                                                        >
                                                            <CommentLine
                                                                entry={comment}
                                                                onReply={() =>
                                                                    setReplyTo(
                                                                        comment.id,
                                                                    )
                                                                }
                                                                onRemoved={() =>
                                                                    void loadComments(
                                                                        post.id,
                                                                    )
                                                                }
                                                            />
                                                            {comment.replies
                                                                .length > 0 && (
                                                                <div
                                                                    style={{
                                                                        marginLeft: 35,
                                                                        display:
                                                                            'flex',
                                                                        flexDirection:
                                                                            'column',
                                                                        gap: 10,
                                                                    }}
                                                                >
                                                                    {comment.replies.map(
                                                                        (
                                                                            reply,
                                                                        ) => (
                                                                            <CommentLine
                                                                                key={
                                                                                    reply.id
                                                                                }
                                                                                entry={
                                                                                    reply
                                                                                }
                                                                                onRemoved={() =>
                                                                                    void loadComments(
                                                                                        post.id,
                                                                                    )
                                                                                }
                                                                            />
                                                                        ),
                                                                    )}
                                                                </div>
                                                            )}
                                                        </div>
                                                    ))}

                                                    {replyTo !== null && (
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.muted,
                                                            }}
                                                        >
                                                            Membalas komentar ·{' '}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setReplyTo(
                                                                        null,
                                                                    )
                                                                }
                                                                style={{
                                                                    border: 'none',
                                                                    background:
                                                                        'none',
                                                                    padding: 0,
                                                                    cursor: 'pointer',
                                                                    fontSize: 11.5,
                                                                    color: C.primary,
                                                                }}
                                                            >
                                                                batal
                                                            </button>
                                                        </div>
                                                    )}

                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            gap: 8,
                                                        }}
                                                    >
                                                        <input
                                                            name="komentar"
                                                            aria-label="Tulis komentar"
                                                            value={draft}
                                                            maxLength={500}
                                                            onChange={(event) =>
                                                                setDraft(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            onKeyDown={(
                                                                event,
                                                            ) => {
                                                                if (
                                                                    event.key ===
                                                                    'Enter'
                                                                ) {
                                                                    event.preventDefault();
                                                                    sendComment(
                                                                        post.id,
                                                                    );
                                                                }
                                                            }}
                                                            placeholder={
                                                                replyTo !== null
                                                                    ? 'Tulis balasan…'
                                                                    : 'Tulis komentar…'
                                                            }
                                                            style={{
                                                                flex: 1,
                                                                height: 36,
                                                                padding:
                                                                    '0 12px',
                                                                border: `1px solid ${C.border}`,
                                                                borderRadius: 8,
                                                                fontSize: 13,
                                                                color: C.text,
                                                                outline: 'none',
                                                            }}
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                sendComment(
                                                                    post.id,
                                                                )
                                                            }
                                                            style={{
                                                                height: 36,
                                                                padding:
                                                                    '0 14px',
                                                                border: 'none',
                                                                borderRadius: 8,
                                                                background:
                                                                    C.primary,
                                                                color: '#fff',
                                                                fontSize: 13,
                                                                fontWeight: 600,
                                                                cursor: 'pointer',
                                                            }}
                                                        >
                                                            Kirim
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </Panel>
                            ))
                        )}

                        {posts.last_page > 1 && (
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 12,
                                    fontSize: 13,
                                    color: C.muted,
                                }}
                            >
                                <button
                                    type="button"
                                    disabled={posts.current_page <= 1}
                                    onClick={() =>
                                        goToPage(posts.current_page - 1)
                                    }
                                    style={{
                                        height: 34,
                                        padding: '0 14px',
                                        borderRadius: 8,
                                        border: 'none',
                                        background: C.surface,
                                        color: C.text,
                                        cursor:
                                            posts.current_page <= 1
                                                ? 'default'
                                                : 'pointer',
                                        opacity:
                                            posts.current_page <= 1 ? 0.5 : 1,
                                    }}
                                >
                                    Sebelumnya
                                </button>
                                <span>
                                    Halaman {posts.current_page} dari{' '}
                                    {posts.last_page}
                                </span>
                                <button
                                    type="button"
                                    disabled={
                                        posts.current_page >= posts.last_page
                                    }
                                    onClick={() =>
                                        goToPage(posts.current_page + 1)
                                    }
                                    style={{
                                        height: 34,
                                        padding: '0 14px',
                                        borderRadius: 8,
                                        border: 'none',
                                        background: C.surface,
                                        color: C.text,
                                        cursor:
                                            posts.current_page >=
                                            posts.last_page
                                                ? 'default'
                                                : 'pointer',
                                        opacity:
                                            posts.current_page >=
                                            posts.last_page
                                                ? 0.5
                                                : 1,
                                    }}
                                >
                                    Berikutnya
                                </button>
                            </div>
                        )}
                    </div>

                    <Panel
                        title="Leaderboard"
                        subtitle={`Poin: ${weights.post ?? 0}/postingan · ${weights.like ?? 0}/suka · ${weights.comment ?? 0}/komentar`}
                    >
                        {leaderboard.length === 0 ? (
                            <EmptyState
                                icon="crown"
                                message="Belum ada kontributor."
                            />
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                }}
                            >
                                {leaderboard.map((leader) => (
                                    <div
                                        key={leader.employee_id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                            padding: leader.is_me
                                                ? '8px 10px'
                                                : undefined,
                                            borderRadius: 8,
                                            background: leader.is_me
                                                ? `${C.primary}0F`
                                                : undefined,
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 18,
                                                fontSize: 12.5,
                                                fontWeight: 700,
                                                color: C.faint,
                                            }}
                                        >
                                            {leader.rank}
                                        </span>
                                        <Avatar
                                            name={leader.name}
                                            photo={leader.photo}
                                            size={30}
                                        />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 13,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {leader.name}
                                                {leader.is_me && ' (Anda)'}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.muted,
                                                }}
                                            >
                                                {leader.posts} ide ·{' '}
                                                {leader.likes} suka
                                            </div>
                                        </div>
                                        <span
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 700,
                                                color: C.primary,
                                            }}
                                        >
                                            {leader.points}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>
                </div>
            </PageShell>
        </>
    );
}
