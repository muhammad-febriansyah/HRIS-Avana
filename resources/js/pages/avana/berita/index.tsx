import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import NewsController from '@/actions/App/Http/Controllers/Avana/NewsController';
import { RichEditor } from '@/components/avana-ui/rich-editor';
import { AIcon, ActionBtn, btnOut, btnP, btnSave, C, card } from '@/lib/avana';
import {
    Field,
    ImageUpload,
    inputStyle,
    withError,
} from '../website-settings/components';

type Item = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    category: string | null;
    status: 'draft' | 'published';
    is_featured: boolean;
    published_at: string | null;
    image_url: string | null;
};
type Data = {
    title: string;
    slug: string;
    excerpt: string;
    body: string;
    category: string;
    status: 'draft' | 'published';
    is_featured: boolean;
    image: File | null;
    remove_image: boolean;
};
type Flash = { flash?: { success?: string } };
type NewsPage = {
    data: Item[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
const empty = (): Data => ({
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    category: '',
    status: 'draft',
    is_featured: false,
    image: null,
    remove_image: false,
});
const label: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

function toSlug(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function Berita({
    news,
    filters,
}: {
    news: NewsPage;
    filters: { q: string };
}) {
    const { flash } = usePage<Flash>().props;
    const [openForm, setOpenForm] = useState(false);
    const [editing, setEditing] = useState<Item | null>(null);
    const [confirm, setConfirm] = useState<Item | null>(null);
    const [manualSlug, setManualSlug] = useState(false);
    const [search, setSearch] = useState(filters.q);
    const form = useForm<Data>(empty());
    useEffect(() => {
        if (flash?.success) toast.success(flash.success, { id: flash.success });
    }, [flash?.success]);

    const open = (item?: Item) => {
        setEditing(item ?? null);
        setOpenForm(true);
        setManualSlug(!!item);
        form.clearErrors();
        form.setData(
            item
                ? {
                      title: item.title,
                      slug: item.slug,
                      excerpt: item.excerpt ?? '',
                      body: item.body,
                      category: item.category ?? '',
                      status: item.status,
                      is_featured: item.is_featured,
                      image: null,
                      remove_image: false,
                  }
                : empty(),
        );
    };
    const close = () => {
        setOpenForm(false);
        setEditing(null);
        setManualSlug(false);
        form.reset();
        form.clearErrors();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(
            editing
                ? NewsController.update(editing.id).url
                : NewsController.store().url,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: close,
                onError: (errors) => {
                    toast.error(
                        Object.values(errors)[0] ??
                            'Periksa kembali data berita.',
                    );
                },
            },
        );
    };
    const remove = () => {
        if (confirm)
            router.delete(NewsController.destroy(confirm.id).url, {
                preserveScroll: true,
                onSuccess: () => setConfirm(null),
            });
    };
    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(NewsController.index().url, search ? { q: search } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Berita" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-start',
                        gap: 16,
                        flexWrap: 'wrap',
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            Platform / Berita
                        </div>
                        <h1
                            style={{
                                margin: 0,
                                color: C.navy,
                                fontSize: 24,
                                fontWeight: 600,
                            }}
                        >
                            Berita
                        </h1>
                        <div
                            style={{
                                color: C.muted,
                                fontSize: 14,
                                marginTop: 4,
                            }}
                        >
                            Kelola artikel yang tampil di website platform.
                        </div>
                    </div>
                    <button onClick={() => open()} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Berita
                    </button>
                </div>
                <form
                    onSubmit={submitSearch}
                    style={{ display: 'flex', gap: 10, marginBottom: 18 }}
                >
                    <div style={{ position: 'relative', flex: '1 1 280px' }}>
                        <AIcon
                            name="search"
                            size={17}
                            color={C.faint}
                            style={{ position: 'absolute', left: 13, top: 13 }}
                        />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari judul, kategori, atau ringkasan..."
                            style={{ ...inputStyle, paddingLeft: 40 }}
                        />
                    </div>
                    <button type="submit" style={btnP}>
                        Cari
                    </button>
                </form>
                {news.data.length === 0 ? (
                    <div
                        style={{
                            ...card,
                            padding: 48,
                            textAlign: 'center',
                            color: C.muted,
                        }}
                    >
                        Belum ada berita. Tambahkan artikel pertama untuk mulai
                        mengisi halaman berita.
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(270px, 1fr))',
                            gap: 18,
                        }}
                    >
                        {news.data.map((item) => (
                            <div
                                key={item.id}
                                style={{
                                    ...card,
                                    padding: 16,
                                    overflow: 'hidden',
                                }}
                            >
                                <div
                                    style={{
                                        width: '100%',
                                        height: 170,
                                        borderRadius: 10,
                                        overflow: 'hidden',
                                        background: C.surface,
                                    }}
                                >
                                    <Link
                                        href={NewsController.show(item.id).url}
                                        style={{
                                            display: 'block',
                                            height: '100%',
                                        }}
                                    >
                                        {item.image_url ? (
                                            <img
                                                src={item.image_url}
                                                alt=""
                                                style={{
                                                    width: '100%',
                                                    height: '100%',
                                                    objectFit: 'cover',
                                                }}
                                            />
                                        ) : (
                                            <div
                                                style={{
                                                    height: '100%',
                                                    display: 'grid',
                                                    placeItems: 'center',
                                                }}
                                            >
                                                <AIcon
                                                    name="image"
                                                    color={C.faint}
                                                />
                                            </div>
                                        )}
                                    </Link>
                                </div>
                                <div style={{ padding: '14px 16px 16px' }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 8,
                                            alignItems: 'center',
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        <Link
                                            href={
                                                NewsController.show(item.id).url
                                            }
                                            style={{
                                                color: C.navy,
                                                fontSize: 16,
                                                fontWeight: 600,
                                                textDecoration: 'none',
                                            }}
                                        >
                                            {item.title}
                                        </Link>
                                        <span
                                            style={{
                                                color:
                                                    item.status === 'published'
                                                        ? C.green
                                                        : C.muted,
                                                background:
                                                    item.status === 'published'
                                                        ? 'rgba(22,163,74,.1)'
                                                        : 'rgba(107,114,128,.12)',
                                                borderRadius: 99,
                                                padding: '3px 9px',
                                                fontSize: 11,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {item.status === 'published'
                                                ? 'Terbit'
                                                : 'Draft'}
                                        </span>
                                        {item.is_featured && (
                                            <span
                                                style={{
                                                    color: C.amber,
                                                    fontSize: 11,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                Unggulan
                                            </span>
                                        )}
                                    </div>
                                    <div
                                        style={{
                                            marginTop: 5,
                                            color: C.muted,
                                            fontSize: 13,
                                        }}
                                    >
                                        {item.category ?? 'Tanpa kategori'} · /
                                        {item.slug}
                                    </div>
                                    <div
                                        style={{
                                            marginTop: 6,
                                            color: C.muted,
                                            fontSize: 13,
                                            lineHeight: 1.45,
                                        }}
                                    >
                                        {item.excerpt ??
                                            item.body.slice(0, 140)}
                                    </div>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 6,
                                        padding: '0 16px 16px',
                                    }}
                                >
                                    <ActionBtn
                                        icon="pencil"
                                        label="Ubah"
                                        variant="success"
                                        onClick={() => open(item)}
                                    />
                                    <ActionBtn
                                        icon="trash-2"
                                        label="Hapus"
                                        variant="danger"
                                        onClick={() => setConfirm(item)}
                                    />
                                </div>
                                <Link
                                    href={NewsController.show(item.id).url}
                                    style={{
                                        color: C.primary,
                                        fontSize: 13,
                                        fontWeight: 600,
                                        textDecoration: 'none',
                                    }}
                                >
                                    Baca detail →
                                </Link>
                            </div>
                        ))}
                    </div>
                )}
                {news.last_page > 1 && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: 12,
                            flexWrap: 'wrap',
                            marginTop: 22,
                        }}
                    >
                        <span style={{ color: C.muted, fontSize: 13 }}>
                            Menampilkan {news.from ?? 0}-{news.to ?? 0} dari{' '}
                            {news.total} berita
                        </span>
                        <div
                            style={{
                                display: 'flex',
                                gap: 5,
                                flexWrap: 'wrap',
                            }}
                        >
                            {news.links.map((link, index) => (
                                <button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() =>
                                        link.url &&
                                        router.visit(link.url, {
                                            preserveState: true,
                                            preserveScroll: true,
                                        })
                                    }
                                    style={{
                                        minWidth: 34,
                                        height: 34,
                                        padding: '0 9px',
                                        border: `1px solid ${link.active ? C.primary : C.border}`,
                                        borderRadius: 8,
                                        background: link.active
                                            ? C.primary
                                            : '#fff',
                                        color: link.active ? '#fff' : C.text,
                                        opacity: link.url ? 1 : 0.45,
                                        cursor: link.url
                                            ? 'pointer'
                                            : 'default',
                                    }}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
            {openForm && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 80,
                        display: 'grid',
                        placeItems: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={close}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submit}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 680,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                        }}
                    >
                        <h2 style={{ margin: 0, color: C.navy, fontSize: 19 }}>
                            {editing ? 'Ubah Berita' : 'Tambah Berita'}
                        </h2>
                        <p
                            style={{
                                color: C.muted,
                                fontSize: 13,
                                margin: '5px 0 20px',
                            }}
                        >
                            Isi artikel dan gunakan gambar rasio 16:9 agar
                            tampil rapi.
                        </p>
                        <div style={{ display: 'grid', gap: 15 }}>
                            <Field label="Judul" error={form.errors.title}>
                                <input
                                    value={form.data.title}
                                    onChange={(e) => {
                                        const title = e.target.value;
                                        form.setData('title', title);
                                        if (!manualSlug) {
                                            form.setData('slug', toSlug(title));
                                        }
                                    }}
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.title,
                                    )}
                                    placeholder="Judul berita"
                                />
                            </Field>
                            <Field
                                label="Slug"
                                hint="Kosongkan untuk dibuat otomatis dari judul."
                                error={form.errors.slug}
                            >
                                <input
                                    value={form.data.slug}
                                    onChange={(e) => {
                                        const slug = e.target.value;
                                        setManualSlug(slug !== '');
                                        form.setData('slug', toSlug(slug));
                                    }}
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.slug,
                                    )}
                                    placeholder="judul-berita"
                                />
                            </Field>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 15,
                                }}
                            >
                                <Field
                                    label="Kategori"
                                    error={form.errors.category}
                                >
                                    <input
                                        value={form.data.category}
                                        onChange={(e) =>
                                            form.setData(
                                                'category',
                                                e.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.category,
                                        )}
                                        placeholder="Perusahaan"
                                    />
                                </Field>
                                <Field
                                    label="Status"
                                    error={form.errors.status}
                                >
                                    <select
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target
                                                    .value as Data['status'],
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.status,
                                        )}
                                    >
                                        <option value="draft">Draft</option>
                                        <option value="published">
                                            Terbit
                                        </option>
                                    </select>
                                </Field>
                            </div>
                            <Field
                                label="Ringkasan"
                                error={form.errors.excerpt}
                            >
                                <textarea
                                    value={form.data.excerpt}
                                    onChange={(e) =>
                                        form.setData('excerpt', e.target.value)
                                    }
                                    rows={2}
                                    style={{
                                        ...withError(
                                            inputStyle,
                                            !!form.errors.excerpt,
                                        ),
                                        resize: 'vertical',
                                    }}
                                    placeholder="Ringkasan singkat untuk kartu berita"
                                />
                            </Field>
                            <Field label="Isi berita" error={form.errors.body}>
                                <RichEditor
                                    value={form.data.body}
                                    onChange={(value) =>
                                        form.setData('body', value)
                                    }
                                    placeholder="Tulis isi berita..."
                                    hasError={!!form.errors.body}
                                    minHeight={300}
                                />
                            </Field>
                            <ImageUpload
                                label="Gambar utama"
                                hint="JPG, PNG atau WEBP · maksimal 5 MB"
                                accept="image/png,image/jpeg,image/webp"
                                file={form.data.image}
                                currentUrl={editing?.image_url ?? null}
                                error={form.errors.image}
                                onPick={(file) => {
                                    form.setData('image', file);
                                    form.setData('remove_image', false);
                                }}
                                onClear={() => {
                                    form.setData('image', null);
                                    form.setData(
                                        'remove_image',
                                        !!editing?.image_url,
                                    );
                                }}
                            />
                            <label
                                style={{
                                    display: 'flex',
                                    gap: 9,
                                    alignItems: 'center',
                                    color: C.text,
                                    fontSize: 13.5,
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.is_featured}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_featured',
                                            e.target.checked,
                                        )
                                    }
                                />{' '}
                                Jadikan berita unggulan
                            </label>
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={close}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    justifyContent: 'center',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnSave,
                                    flex: 1,
                                    justifyContent: 'center',
                                    opacity: form.processing ? 0.7 : 1,
                                }}
                            >
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Berita'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
            {confirm && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 90,
                        display: 'grid',
                        placeItems: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={() => setConfirm(null)}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <div
                        style={{
                            position: 'relative',
                            background: '#fff',
                            borderRadius: 14,
                            padding: 26,
                            width: '100%',
                            maxWidth: 400,
                        }}
                    >
                        <h2 style={{ margin: 0, color: C.navy, fontSize: 18 }}>
                            Hapus berita?
                        </h2>
                        <p
                            style={{
                                color: C.muted,
                                fontSize: 13.5,
                                lineHeight: 1.5,
                            }}
                        >
                            Berita <strong>{confirm.title}</strong> akan dihapus
                            permanen.
                        </p>
                        <div style={{ display: 'flex', gap: 10 }}>
                            <button
                                onClick={() => setConfirm(null)}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    justifyContent: 'center',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                onClick={remove}
                                style={{
                                    flex: 1,
                                    border: 0,
                                    borderRadius: 9,
                                    background: C.red,
                                    color: '#fff',
                                    fontWeight: 600,
                                }}
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
