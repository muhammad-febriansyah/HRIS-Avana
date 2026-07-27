import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import SocialController from '@/actions/App/Http/Controllers/Avana/SocialController';
import { useIsMobile } from '@/hooks/use-mobile';
import { ActionBtn, AIcon, btnP, C, card, thCell } from '@/lib/avana';
import {
    CategoryChip,
    ColorPicker,
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    IconPicker,
    inputStyle,
    ModalShell,
    selectStyle,
    StatusPill,
    textareaStyle,
    withError,
} from './components';
import { EotmTab } from './eotm-tab';
import { emptyCategoryForm, STATUS_OPTIONS } from './types';
import type {
    CategoryFormData,
    FlashProps,
    LeaderboardRow,
    SocialCategoryRow,
    SocialPostRow,
    SosmedIndexProps,
} from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 170px',
};

type Tab = 'feed' | 'kategori' | 'leaderboard' | 'eotm';

export default function SosmedIndex({
    posts,
    categories,
    leaderboard,
    weights,
    eotm,
    kpis,
    filters,
}: SosmedIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [tab, setTab] = useState<Tab>('feed');
    const [categoryModal, setCategoryModal] = useState<
        SocialCategoryRow | 'new' | null
    >(null);
    const [confirmCategory, setConfirmCategory] =
        useState<SocialCategoryRow | null>(null);
    const [confirmPost, setConfirmPost] = useState<SocialPostRow | null>(null);

    const form = useForm<CategoryFormData>({ ...emptyCategoryForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const openCategory = (category: SocialCategoryRow | 'new') => {
        form.clearErrors();

        if (category === 'new') {
            form.setData({ ...emptyCategoryForm });
        } else {
            form.setData({
                name: category.name,
                icon: category.icon,
                color: category.color,
                description: category.description ?? '',
                status: category.status,
                sort_order: String(category.sort_order),
            });
        }

        setCategoryModal(category);
    };

    const closeCategory = () => {
        setCategoryModal(null);
        form.reset();
        form.clearErrors();
    };

    const submitCategory = () => {
        if (categoryModal === null) {
            return;
        }

        const action =
            categoryModal === 'new'
                ? SocialController.storeCategory()
                : SocialController.updateCategory(categoryModal.id);

        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => closeCategory(),
        });
    };

    const deleteCategory = () => {
        if (!confirmCategory) {
            return;
        }

        router.delete(
            SocialController.destroyCategory(confirmCategory.id).url,
            {
                preserveScroll: true,
                onSuccess: () => setConfirmCategory(null),
            },
        );
    };

    const deletePost = () => {
        if (!confirmPost) {
            return;
        }

        router.delete(SocialController.destroyPost(confirmPost.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirmPost(null),
        });
    };

    const toggleVisibility = (post: SocialPostRow) => {
        router.put(
            SocialController.toggleVisibility(post.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const applyFilter = (
        key: 'category' | 'status' | 'reported',
        value: string,
    ) => {
        router.get(
            SocialController.index().url,
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const kpiItems = [
        {
            label: 'Post Tayang',
            value: kpis.posts,
            icon: 'message-square',
            color: C.primary,
        },
        {
            label: 'Bulan Ini',
            value: kpis.this_month,
            icon: 'calendar-days',
            color: C.sky,
        },
        {
            label: 'Kontributor',
            value: kpis.contributors,
            icon: 'users-round',
            color: C.green,
        },
        {
            label: 'Kategori',
            value: kpis.categories,
            icon: 'layers',
            color: C.navy,
        },
        {
            label: 'Disembunyikan',
            value: kpis.hidden,
            icon: 'eye-off',
            color: C.amber,
        },
        {
            label: 'Dilaporkan',
            value: kpis.reported,
            icon: 'flag',
            color: C.red,
        },
    ];

    return (
        <>
            <Head title="Ruang Kita" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            <span>Layanan</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Ruang Kita</span>
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Ruang Kita
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Karyawan memposting dari aplikasi. Post langsung
                            tayang — di sini kamu mengelola kategori dan
                            menurunkan konten yang tidak layak.
                        </div>
                    </div>
                    <button onClick={() => openCategory('new')} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Kategori Baru
                    </button>
                </div>

                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 14,
                        marginBottom: 22,
                    }}
                >
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    marginBottom: 10,
                                }}
                            >
                                <div
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 9,
                                        background: `${item.color}1a`,
                                        display: 'grid',
                                        placeItems: 'center',
                                    }}
                                >
                                    <AIcon
                                        name={item.icon}
                                        size={17}
                                        color={item.color}
                                    />
                                </div>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        fontWeight: 500,
                                    }}
                                >
                                    {item.label}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 26,
                                    fontWeight: 700,
                                    color: C.navy,
                                    letterSpacing: '-.02em',
                                }}
                            >
                                {item.value}
                            </div>
                        </div>
                    ))}
                </div>

                <div
                    role="tablist"
                    style={{
                        display: 'flex',
                        gap: 6,
                        borderBottom: `1px solid ${C.line}`,
                        marginBottom: 16,
                    }}
                >
                    {(
                        [
                            ['feed', 'Feed', posts.total],
                            ['kategori', 'Kategori', categories.length],
                            ['leaderboard', 'Leaderboard', leaderboard.length],
                            [
                                'eotm',
                                'Employee of the Month',
                                eotm.standings.length,
                            ],
                        ] as [Tab, string, number][]
                    ).map(([key, label, count]) => (
                        <button
                            key={key}
                            role="tab"
                            aria-label={label}
                            aria-selected={tab === key}
                            onClick={() => setTab(key)}
                            style={{
                                border: 'none',
                                background: 'transparent',
                                cursor: 'pointer',
                                padding: '10px 14px',
                                fontSize: 13.5,
                                fontWeight: 600,
                                color: tab === key ? C.primary : C.muted,
                                borderBottom: `2px solid ${
                                    tab === key ? C.primary : 'transparent'
                                }`,
                                marginBottom: -1,
                            }}
                        >
                            {label} ({count})
                        </button>
                    ))}
                </div>

                {tab === 'feed' && (
                    <FeedTab
                        posts={posts}
                        categories={categories}
                        filters={filters}
                        onFilter={applyFilter}
                        onToggle={toggleVisibility}
                        onDelete={setConfirmPost}
                    />
                )}

                {tab === 'kategori' && (
                    <CategoryTab
                        categories={categories}
                        onEdit={openCategory}
                        onDelete={setConfirmCategory}
                    />
                )}

                {tab === 'leaderboard' && (
                    <LeaderboardTab rows={leaderboard} weights={weights} />
                )}

                {tab === 'eotm' && <EotmTab eotm={eotm} />}
            </div>

            {categoryModal !== null && (
                <ModalShell
                    title={
                        categoryModal === 'new'
                            ? 'Kategori Baru'
                            : 'Ubah Kategori'
                    }
                    subtitle="Kategori jadi chip pilihan saat karyawan memposting."
                    onClose={closeCategory}
                    onSubmit={submitCategory}
                    processing={form.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Nama Kategori{' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="contoh: Ide Perbaikan"
                            style={withError(inputStyle, !!form.errors.name)}
                        />
                        <FieldError message={form.errors.name} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Ikon</label>
                        <IconPicker
                            value={form.data.icon}
                            onChange={(icon) => form.setData('icon', icon)}
                            accent={form.data.color}
                        />
                        <FieldError message={form.errors.icon} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Warna</label>
                        <ColorPicker
                            value={form.data.color}
                            onChange={(color) => form.setData('color', color)}
                        />
                        <FieldError message={form.errors.color} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Pratinjau</label>
                        <CategoryChip
                            name={form.data.name || 'Nama Kategori'}
                            icon={form.data.icon}
                            color={form.data.color}
                        />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Keterangan</label>
                        <textarea
                            name="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder="Dipakai sebagai penjelasan singkat di aplikasi."
                            style={withError(
                                textareaStyle,
                                !!form.errors.description,
                            )}
                        />
                        <FieldError message={form.errors.description} />
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 14,
                        }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>
                                Status <span style={{ color: C.red }}>*</span>
                            </label>
                            <select
                                name="status"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                                style={withError(
                                    selectStyle,
                                    !!form.errors.status,
                                )}
                            >
                                {STATUS_OPTIONS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={form.errors.status} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Urutan</label>
                            <input
                                type="number"
                                name="sort_order"
                                min={0}
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    inputStyle,
                                    !!form.errors.sort_order,
                                )}
                            />
                            <FieldError message={form.errors.sort_order} />
                        </div>
                    </div>
                </ModalShell>
            )}

            {confirmCategory && (
                <ConfirmModal
                    title="Hapus kategori?"
                    body={
                        <>
                            Kategori{' '}
                            <strong style={{ color: C.text }}>
                                {confirmCategory.name}
                            </strong>{' '}
                            akan dihapus. {confirmCategory.posts_count} post
                            yang memakainya tetap tayang, tetapi menjadi tanpa
                            kategori.
                        </>
                    }
                    onCancel={() => setConfirmCategory(null)}
                    onConfirm={deleteCategory}
                />
            )}

            {confirmPost && (
                <ConfirmModal
                    title="Hapus post?"
                    body={
                        <>
                            Post dari{' '}
                            <strong style={{ color: C.text }}>
                                {confirmPost.author}
                            </strong>{' '}
                            beserta semua like dan komentarnya akan dihapus
                            permanen. Untuk menurunkan sementara, pakai
                            Sembunyikan.
                        </>
                    }
                    onCancel={() => setConfirmPost(null)}
                    onConfirm={deletePost}
                />
            )}
        </>
    );
}

function FeedTab({
    posts,
    categories,
    filters,
    onFilter,
    onToggle,
    onDelete,
}: {
    posts: SosmedIndexProps['posts'];
    categories: SocialCategoryRow[];
    filters: SosmedIndexProps['filters'];
    onFilter: (key: 'category' | 'status' | 'reported', value: string) => void;
    onToggle: (post: SocialPostRow) => void;
    onDelete: (post: SocialPostRow) => void;
}) {
    return (
        <>
            <div
                style={{
                    display: 'flex',
                    gap: 10,
                    flexWrap: 'wrap',
                    marginBottom: 12,
                }}
            >
                <select
                    name="filter_kategori"
                    aria-label="Filter kategori"
                    value={filters.category ?? ''}
                    onChange={(event) =>
                        onFilter('category', event.target.value)
                    }
                    style={{ ...selectStyle, width: 200 }}
                >
                    <option value="">Semua kategori</option>
                    {categories.map((category) => (
                        <option key={category.id} value={String(category.id)}>
                            {category.name}
                        </option>
                    ))}
                </select>
                <select
                    name="filter_status"
                    aria-label="Filter status post"
                    value={filters.status ?? ''}
                    onChange={(event) => onFilter('status', event.target.value)}
                    style={{ ...selectStyle, width: 180 }}
                >
                    <option value="">Semua status</option>
                    <option value="published">Tayang</option>
                    <option value="hidden">Disembunyikan</option>
                </select>
                <button
                    onClick={() =>
                        onFilter('reported', filters.reported ? '' : '1')
                    }
                    style={{
                        ...selectStyle,
                        width: 'auto',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 7,
                        padding: '0 14px',
                        fontWeight: 600,
                        color: filters.reported ? '#fff' : C.muted,
                        background: filters.reported ? C.red : '#fff',
                    }}
                >
                    <AIcon
                        name="flag"
                        size={14}
                        color={filters.reported ? '#fff' : C.muted}
                    />
                    Dilaporkan
                </button>
            </div>

            {posts.data.length === 0 ? (
                <div
                    style={{
                        ...card,
                        padding: '48px 18px',
                        textAlign: 'center',
                        color: C.muted,
                        fontSize: 13.5,
                    }}
                >
                    Belum ada postingan.
                </div>
            ) : (
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(330px, 1fr))',
                        gap: 14,
                    }}
                >
                    {posts.data.map((post) => (
                        <div
                            key={post.id}
                            style={{
                                ...card,
                                padding: 16,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 10,
                                opacity: post.status === 'hidden' ? 0.6 : 1,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                }}
                            >
                                <div
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 99,
                                        background: 'rgba(47,84,201,.1)',
                                        display: 'grid',
                                        placeItems: 'center',
                                        fontSize: 12,
                                        fontWeight: 700,
                                        color: C.primary,
                                    }}
                                >
                                    {post.author.slice(0, 2).toUpperCase()}
                                </div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {post.author}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: C.faint,
                                        }}
                                    >
                                        {post.created_for_humans}
                                    </div>
                                </div>
                                <CategoryChip
                                    name={post.category}
                                    icon={post.category_icon}
                                    color={post.category_color}
                                />
                            </div>

                            <div
                                style={{
                                    fontSize: 13.5,
                                    color: C.text,
                                    lineHeight: 1.55,
                                }}
                            >
                                {post.body}
                            </div>

                            {post.image_url ? (
                                <img
                                    src={post.image_url}
                                    alt=""
                                    style={{
                                        width: '100%',
                                        maxHeight: 200,
                                        objectFit: 'cover',
                                        borderRadius: 10,
                                    }}
                                />
                            ) : null}

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 14,
                                    fontSize: 12,
                                    color: C.muted,
                                    marginTop: 'auto',
                                    paddingTop: 4,
                                }}
                            >
                                <span
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 5,
                                    }}
                                >
                                    <AIcon
                                        name="heart"
                                        size={13}
                                        color={C.red}
                                    />
                                    {post.likes_count}
                                </span>
                                <span
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 5,
                                    }}
                                >
                                    <AIcon
                                        name="message-square"
                                        size={13}
                                        color={C.muted}
                                    />
                                    {post.comments_count}
                                </span>
                                {post.reports_count > 0 ? (
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 4,
                                            fontSize: 11,
                                            fontWeight: 700,
                                            color: C.red,
                                        }}
                                    >
                                        <AIcon
                                            name="flag"
                                            size={12}
                                            color={C.red}
                                        />
                                        {post.reports_count} laporan
                                    </span>
                                ) : null}
                                {post.status === 'hidden' ? (
                                    <span
                                        style={{
                                            marginLeft: 'auto',
                                            fontSize: 11,
                                            fontWeight: 600,
                                            color: C.amber,
                                        }}
                                    >
                                        Disembunyikan
                                    </span>
                                ) : null}
                            </div>

                            <div style={{ display: 'flex', gap: 6 }}>
                                <ActionBtn
                                    icon={
                                        post.status === 'hidden'
                                            ? 'eye'
                                            : 'eye-off'
                                    }
                                    label={
                                        post.status === 'hidden'
                                            ? 'Tayangkan'
                                            : 'Sembunyikan'
                                    }
                                    variant="neutral"
                                    title={
                                        post.status === 'hidden'
                                            ? 'Tayangkan kembali'
                                            : 'Sembunyikan dari wall'
                                    }
                                    onClick={() => onToggle(post)}
                                />
                                <ActionBtn
                                    icon="trash-2"
                                    label="Hapus"
                                    variant="danger"
                                    title="Hapus permanen"
                                    onClick={() => onDelete(post)}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}

function CategoryTab({
    categories,
    onEdit,
    onDelete,
}: {
    categories: SocialCategoryRow[];
    onEdit: (category: SocialCategoryRow) => void;
    onDelete: (category: SocialCategoryRow) => void;
}) {
    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 760,
                    }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={thCell}>Kategori</th>
                            <th style={thCell}>Keterangan</th>
                            <th style={thCell}>Post</th>
                            <th style={thCell}>Urutan</th>
                            <th style={thCell}>Status</th>
                            <th
                                style={{
                                    ...thCell,
                                    textAlign: 'right',
                                    padding: '12px 18px',
                                }}
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {categories.length === 0 && (
                            <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                <td
                                    colSpan={6}
                                    style={{
                                        padding: '48px 18px',
                                        textAlign: 'center',
                                        fontSize: 13.5,
                                        color: C.muted,
                                    }}
                                >
                                    Belum ada kategori.
                                </td>
                            </tr>
                        )}
                        {categories.map((category) => (
                            <tr
                                key={category.id}
                                style={{ borderTop: `1px solid ${C.line}` }}
                            >
                                <td style={{ padding: '13px 16px' }}>
                                    <CategoryChip
                                        name={category.name}
                                        icon={category.icon}
                                        color={category.color}
                                    />
                                </td>
                                <td
                                    style={{
                                        padding: '13px 16px',
                                        fontSize: 13,
                                        color: C.muted,
                                    }}
                                >
                                    {category.description ?? '—'}
                                </td>
                                <td
                                    style={{
                                        padding: '13px 16px',
                                        fontSize: 13,
                                        color: C.text,
                                    }}
                                >
                                    {category.posts_count}
                                </td>
                                <td
                                    style={{
                                        padding: '13px 16px',
                                        fontSize: 13,
                                        color: C.text,
                                    }}
                                >
                                    {category.sort_order}
                                </td>
                                <td style={{ padding: '13px 16px' }}>
                                    <StatusPill status={category.status} />
                                </td>
                                <td
                                    style={{
                                        padding: '13px 18px',
                                        textAlign: 'right',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'inline-flex',
                                            gap: 6,
                                        }}
                                    >
                                        <ActionBtn
                                            icon="pencil"
                                            label="Ubah"
                                            variant="primary"
                                            title="Ubah"
                                            onClick={() => onEdit(category)}
                                        />
                                        <ActionBtn
                                            icon="trash-2"
                                            label="Hapus"
                                            variant="danger"
                                            title="Hapus"
                                            onClick={() => onDelete(category)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** First letters of a name, for an employee with no photo on file. */
function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

/**
 * Medal colours for the top three. Rank is the whole point of a leaderboard, so
 * the first three places are read at a glance rather than counted down a table.
 */
const MEDAL: Record<number, string> = {
    1: '#D97706',
    2: '#94A3B8',
    3: '#B45309',
};

function LeaderAvatar({
    name,
    photo,
    size,
    ring,
}: {
    name: string;
    photo: string | null;
    size: number;
    ring?: string;
}) {
    const shared: CSSProperties = {
        width: size,
        height: size,
        borderRadius: '50%',
        objectFit: 'cover',
        flexShrink: 0,
        outline: ring ? `2px solid ${ring}` : undefined,
        outlineOffset: 2,
    };

    if (photo) {
        return <img src={photo} alt={name} style={shared} />;
    }

    return (
        <div
            style={{
                ...shared,
                display: 'grid',
                placeItems: 'center',
                background: C.surface,
                color: C.muted,
                fontSize: size * 0.36,
                fontWeight: 700,
            }}
        >
            {initials(name)}
        </div>
    );
}

/** One of the three podium cards. Rank 1 sits in the middle and reads larger. */
function PodiumCard({
    row,
    featured,
}: {
    row: LeaderboardRow;
    featured: boolean;
}) {
    const medal = MEDAL[row.rank];

    return (
        <div
            style={{
                ...card,
                flex: featured ? '1 1 220px' : '1 1 190px',
                maxWidth: featured ? 260 : 230,
                padding: featured ? '22px 18px 20px' : '18px 16px 16px',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                textAlign: 'center',
                alignSelf: featured ? 'flex-start' : 'flex-end',
            }}
        >
            <div style={{ position: 'relative', marginBottom: 10 }}>
                <LeaderAvatar
                    name={row.name}
                    photo={row.photo}
                    size={featured ? 72 : 56}
                    ring={medal}
                />
                <span
                    style={{
                        position: 'absolute',
                        bottom: -4,
                        left: '50%',
                        transform: 'translateX(-50%)',
                        width: 22,
                        height: 22,
                        borderRadius: '50%',
                        background: medal,
                        color: '#fff',
                        fontSize: 11.5,
                        fontWeight: 700,
                        display: 'grid',
                        placeItems: 'center',
                    }}
                >
                    {row.rank}
                </span>
            </div>

            <div
                style={{
                    fontSize: featured ? 15 : 13.5,
                    fontWeight: 700,
                    color: C.navy,
                    maxWidth: '100%',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    whiteSpace: 'nowrap',
                }}
            >
                {row.name}
            </div>

            <div
                style={{
                    marginTop: 6,
                    fontSize: featured ? 26 : 21,
                    fontWeight: 700,
                    color: C.primary,
                    lineHeight: 1.1,
                }}
            >
                {row.points.toLocaleString('id-ID')}
                <span
                    style={{
                        fontSize: 11.5,
                        fontWeight: 600,
                        color: C.faint,
                        marginLeft: 4,
                    }}
                >
                    poin
                </span>
            </div>

            <div
                style={{
                    marginTop: 10,
                    display: 'flex',
                    gap: 12,
                    fontSize: 11.5,
                    color: C.muted,
                }}
            >
                <span>{row.posts} post</span>
                <span>{row.likes} like</span>
                <span>{row.comments} komentar</span>
            </div>
        </div>
    );
}

/** A metric under a name in the ranked list. */
function Metric({ value, label }: { value: number; label: string }) {
    return (
        <div style={{ textAlign: 'right', minWidth: 54 }}>
            <div style={{ fontSize: 13, fontWeight: 600, color: C.text }}>
                {value}
            </div>
            <div style={{ fontSize: 10.5, color: C.faint }}>{label}</div>
        </div>
    );
}

function LeaderboardTab({
    rows,
    weights,
}: {
    rows: LeaderboardRow[];
    weights: SosmedIndexProps['weights'];
}) {
    const isMobile = useIsMobile();

    if (rows.length === 0) {
        return (
            <div
                style={{
                    ...card,
                    padding: '48px 18px',
                    textAlign: 'center',
                }}
            >
                <AIcon name="trophy" size={26} color={C.faint} />
                <div
                    style={{
                        marginTop: 10,
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    Belum ada peringkat
                </div>
                <div style={{ marginTop: 4, fontSize: 12.5, color: C.muted }}>
                    Peringkat muncul begitu karyawan mulai memposting di Ruang
                    Kita.
                </div>
            </div>
        );
    }

    // Second place on the left, first in the middle, third on the right — the
    // shape everyone already reads as a podium. Stacked on a phone that reads
    // as "second is above first", so the champion goes back on top there.
    const podium = (
        isMobile ? [rows[0], rows[1], rows[2]] : [rows[1], rows[0], rows[2]]
    ).filter(Boolean) as LeaderboardRow[];
    const rest = rows.slice(3);

    return (
        <>
            <div
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: 14,
                    justifyContent: 'center',
                    alignItems: 'flex-end',
                    marginBottom: 18,
                }}
            >
                {podium.map((row) => (
                    <PodiumCard
                        key={row.employee_id}
                        row={row}
                        featured={row.rank === 1}
                    />
                ))}
            </div>

            {rest.length > 0 && (
                <div style={{ ...card, overflow: 'hidden' }}>
                    {rest.map((row, index) => (
                        <div
                            key={row.employee_id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                                padding: '12px 16px',
                                borderTop:
                                    index === 0
                                        ? undefined
                                        : `1px solid ${C.line}`,
                            }}
                        >
                            <div
                                style={{
                                    width: 26,
                                    fontSize: 13,
                                    fontWeight: 700,
                                    color: C.faint,
                                    flexShrink: 0,
                                }}
                            >
                                {row.rank}
                            </div>

                            <LeaderAvatar
                                name={row.name}
                                photo={row.photo}
                                size={34}
                            />

                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        color: C.navy,
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {row.name}
                                </div>
                                {/* Three fixed metric columns squeeze the name
                                    to nothing on a phone, so there they move
                                    under it as one line. */}
                                {isMobile && (
                                    <div
                                        style={{
                                            marginTop: 2,
                                            fontSize: 11,
                                            color: C.faint,
                                        }}
                                    >
                                        {row.posts} post · {row.likes} like ·{' '}
                                        {row.comments} komentar
                                    </div>
                                )}
                            </div>

                            {!isMobile && (
                                <>
                                    <Metric value={row.posts} label="post" />
                                    <Metric value={row.likes} label="like" />
                                    <Metric
                                        value={row.comments}
                                        label="komentar"
                                    />
                                </>
                            )}

                            <div
                                style={{
                                    minWidth: 68,
                                    textAlign: 'right',
                                    fontSize: 14,
                                    fontWeight: 700,
                                    color: C.primary,
                                }}
                            >
                                {row.points.toLocaleString('id-ID')}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div
                style={{
                    marginTop: 12,
                    padding: '12px 14px',
                    background: C.surface,
                    borderRadius: 10,
                    fontSize: 12,
                    color: C.muted,
                }}
            >
                Poin = {weights.post} per post + {weights.like} per like
                diterima + {weights.comment} per komentar diterima.
            </div>
        </>
    );
}
