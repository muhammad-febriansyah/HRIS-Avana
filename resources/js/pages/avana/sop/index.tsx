import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import SopController from '@/actions/App/Http/Controllers/Avana/SopController';
import { DatePicker } from '@/components/avana/date-picker';
import { FileDropzone } from '@/components/avana/file-dropzone';
import { ActionBtn, AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    IndexedBadge,
    inputStyle,
    ModalShell,
    selectStyle,
    StatusPill,
    textareaStyle,
    VisibilityBadge,
    withError,
} from './components';
import {
    emptySopCategoryForm,
    emptySopForm,
    STATUS_OPTIONS,
    VISIBILITY_OPTIONS,
} from './types';
import type {
    FlashProps,
    SopCategoryFormData,
    SopCategoryRow,
    SopFormData,
    SopIndexProps,
    SopRow,
    SopVisibility,
} from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 170px',
};

type Tab = 'dokumen' | 'jenis';

export default function SopIndex({ sops, categories, kpis }: SopIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [tab, setTab] = useState<Tab>('dokumen');
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [visibilityFilter, setVisibilityFilter] = useState('');

    const [sopModal, setSopModal] = useState<SopRow | 'new' | null>(null);
    const [categoryModal, setCategoryModal] = useState<
        SopCategoryRow | 'new' | null
    >(null);
    const [confirmSop, setConfirmSop] = useState<SopRow | null>(null);
    const [confirmCategory, setConfirmCategory] =
        useState<SopCategoryRow | null>(null);

    const sopForm = useForm<SopFormData>({ ...emptySopForm });
    const categoryForm = useForm<SopCategoryFormData>({
        ...emptySopCategoryForm,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    // The save worked but the PDF's text did not — long enough to read, so it
    // stays up until dismissed rather than disappearing with the others.
    useEffect(() => {
        if (flash?.warning) {
            toast.warning(flash.warning, {
                id: flash.warning,
                duration: 12000,
            });
        }
    }, [flash?.warning]);

    const visibleSops = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        return sops.filter((sop) => {
            if (
                categoryFilter &&
                String(sop.sop_category_id ?? '') !== categoryFilter
            ) {
                return false;
            }

            if (visibilityFilter && sop.visibility !== visibilityFilter) {
                return false;
            }

            if (!keyword) {
                return true;
            }

            return [sop.title, sop.code, sop.category, sop.summary]
                .filter(Boolean)
                .some((field) => String(field).toLowerCase().includes(keyword));
        });
    }, [sops, search, categoryFilter, visibilityFilter]);

    const openSopModal = (sop: SopRow | 'new') => {
        sopForm.clearErrors();

        if (sop === 'new') {
            sopForm.setData({ ...emptySopForm });
        } else {
            sopForm.setData({
                sop_category_id: sop.sop_category_id
                    ? String(sop.sop_category_id)
                    : '',
                code: sop.code ?? '',
                title: sop.title,
                summary: sop.summary ?? '',
                content: sop.content ?? '',
                visibility: sop.visibility,
                status: sop.status,
                version: sop.version ?? '',
                effective_date: sop.effective_date ?? '',
                file: null,
            });
        }

        setSopModal(sop);
    };

    const closeSopModal = () => {
        setSopModal(null);
        sopForm.reset();
        sopForm.clearErrors();
    };

    const submitSop = () => {
        if (sopModal === null) {
            return;
        }

        const action =
            sopModal === 'new'
                ? SopController.store()
                : SopController.update(sopModal.id);

        sopForm.submit(action, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeSopModal(),
        });
    };

    const openCategoryModal = (category: SopCategoryRow | 'new') => {
        categoryForm.clearErrors();

        if (category === 'new') {
            categoryForm.setData({ ...emptySopCategoryForm });
        } else {
            categoryForm.setData({
                name: category.name,
                code: category.code ?? '',
                description: category.description ?? '',
                status: category.status,
            });
        }

        setCategoryModal(category);
    };

    const closeCategoryModal = () => {
        setCategoryModal(null);
        categoryForm.reset();
        categoryForm.clearErrors();
    };

    const submitCategory = () => {
        if (categoryModal === null) {
            return;
        }

        const action =
            categoryModal === 'new'
                ? SopController.storeCategory()
                : SopController.updateCategory(categoryModal.id);

        categoryForm.submit(action, {
            preserveScroll: true,
            onSuccess: () => closeCategoryModal(),
        });
    };

    const deleteSop = () => {
        if (!confirmSop) {
            return;
        }

        router.delete(SopController.destroy(confirmSop.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirmSop(null),
        });
    };

    const deleteCategory = () => {
        if (!confirmCategory) {
            return;
        }

        router.delete(SopController.destroyCategory(confirmCategory.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirmCategory(null),
        });
    };

    const kpiItems = [
        {
            label: 'Total SOP',
            value: kpis.total,
            icon: 'book-open',
            color: C.primary,
        },
        { label: 'Public', value: kpis.public, icon: 'globe', color: C.green },
        { label: 'Private', value: kpis.private, icon: 'lock', color: C.amber },
        {
            label: 'Jenis SOP',
            value: kpis.categories,
            icon: 'layers',
            color: C.sky,
        },
        {
            label: 'Terindeks AI',
            value: kpis.indexed,
            icon: 'sparkles',
            color: C.navy,
        },
    ];

    return (
        <>
            <Head title="SOP" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>SOP</span>
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
                            SOP &amp; Prosedur
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola dokumen SOP (PDF) dan jenisnya. SOP bertipe{' '}
                            <strong>public</strong> dapat dijawab AI Assistant
                            untuk semua karyawan.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button
                            onClick={() => openCategoryModal('new')}
                            style={btnOut}
                        >
                            <AIcon name="layers" size={16} />
                            Jenis SOP
                        </button>
                        <button
                            onClick={() => openSopModal('new')}
                            style={{ ...btnP, background: C.violet }}
                        >
                            <AIcon name="upload" size={16} color="#fff" />
                            Unggah SOP
                        </button>
                    </div>
                </div>

                {/* KPI cards */}
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
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
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

                {/* Tabs */}
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
                            ['dokumen', 'Dokumen SOP', sops.length],
                            ['jenis', 'Jenis SOP', categories.length],
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
                                background:
                                    tab === key
                                        ? 'rgba(47,84,201,.07)'
                                        : C.surface,
                                borderRadius: '8px 8px 0 0',
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

                {tab === 'dokumen' ? (
                    <>
                        {/* Filters */}
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                flexWrap: 'wrap',
                                marginBottom: 12,
                            }}
                        >
                            <input
                                name="cari_sop"
                                aria-label="Cari SOP"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari judul, kode, atau ringkasan…"
                                style={{ ...inputStyle, width: 300 }}
                            />
                            <select
                                name="filter_jenis"
                                aria-label="Filter jenis SOP"
                                value={categoryFilter}
                                onChange={(event) =>
                                    setCategoryFilter(event.target.value)
                                }
                                style={{ ...selectStyle, width: 200 }}
                            >
                                <option value="">Semua jenis</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="filter_tipe"
                                aria-label="Filter tipe SOP"
                                value={visibilityFilter}
                                onChange={(event) =>
                                    setVisibilityFilter(event.target.value)
                                }
                                style={{ ...selectStyle, width: 170 }}
                            >
                                <option value="">Semua tipe</option>
                                <option value="public">Public</option>
                                <option value="private">Private</option>
                            </select>
                        </div>

                        {/* SOP table */}
                        <div style={{ ...card, overflow: 'hidden' }}>
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 980,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={thCell}>SOP</th>
                                            <th style={thCell}>Jenis</th>
                                            <th style={thCell}>Tipe</th>
                                            <th style={thCell}>Versi</th>
                                            <th style={thCell}>Berlaku</th>
                                            <th style={thCell}>Berkas</th>
                                            <th style={thCell}>AI</th>
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
                                        {visibleSops.length === 0 && (
                                            <tr
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    colSpan={9}
                                                    style={{
                                                        padding: '48px 18px',
                                                        textAlign: 'center',
                                                        fontSize: 13.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexDirection:
                                                                'column',
                                                            alignItems:
                                                                'center',
                                                            gap: 10,
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="book-open"
                                                            size={28}
                                                            color={C.faint}
                                                        />
                                                        <div>
                                                            Belum ada dokumen
                                                            SOP.
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                        {visibleSops.map((sop) => (
                                            <tr
                                                key={sop.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 13,
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {sop.title}
                                                    </div>
                                                    {sop.code ? (
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                                marginTop: 2,
                                                            }}
                                                        >
                                                            {sop.code}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                        fontSize: 13,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {sop.category ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                    }}
                                                >
                                                    <VisibilityBadge
                                                        visibility={
                                                            sop.visibility
                                                        }
                                                    />
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                        fontSize: 13,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {sop.version ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                        fontSize: 13,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {sop.effective_date ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                        fontSize: 12.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {sop.file_size_label ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                    }}
                                                >
                                                    <IndexedBadge
                                                        indexed={
                                                            sop.has_content
                                                        }
                                                    />
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                    }}
                                                >
                                                    <StatusPill
                                                        status={sop.status}
                                                    />
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 18px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display:
                                                                'inline-flex',
                                                            gap: 6,
                                                        }}
                                                    >
                                                        <ActionBtn
                                                            icon="download"
                                                            label="Unduh"
                                                            variant="neutral"
                                                            title="Unduh PDF"
                                                            href={
                                                                SopController.download(
                                                                    sop.id,
                                                                ).url
                                                            }
                                                        />
                                                        <ActionBtn
                                                            icon="pencil"
                                                            label="Ubah"
                                                            variant="primary"
                                                            title="Ubah"
                                                            onClick={() =>
                                                                openSopModal(
                                                                    sop,
                                                                )
                                                            }
                                                        />
                                                        <ActionBtn
                                                            icon="trash-2"
                                                            label="Hapus"
                                                            variant="danger"
                                                            title="Hapus"
                                                            onClick={() =>
                                                                setConfirmSop(
                                                                    sop,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </>
                ) : (
                    /* Category table */
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 720,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={thCell}>Jenis SOP</th>
                                        <th style={thCell}>Kode</th>
                                        <th style={thCell}>Keterangan</th>
                                        <th style={thCell}>Jumlah SOP</th>
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
                                        <tr
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                colSpan={6}
                                                style={{
                                                    padding: '48px 18px',
                                                    textAlign: 'center',
                                                    fontSize: 13.5,
                                                    color: C.muted,
                                                }}
                                            >
                                                Belum ada jenis SOP.
                                            </td>
                                        </tr>
                                    )}
                                    {categories.map((category) => (
                                        <tr
                                            key={category.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {category.name}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.text,
                                                }}
                                            >
                                                {category.code ?? '—'}
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
                                                {category.sop_count}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <StatusPill
                                                    status={category.status}
                                                />
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
                                                        onClick={() =>
                                                            openCategoryModal(
                                                                category,
                                                            )
                                                        }
                                                    />
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        title="Hapus"
                                                        onClick={() =>
                                                            setConfirmCategory(
                                                                category,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* SOP create/edit modal */}
            {sopModal !== null && (
                <ModalShell
                    title={sopModal === 'new' ? 'Unggah SOP' : 'Ubah SOP'}
                    subtitle="Berkas SOP harus berformat PDF. Isinya dibaca otomatis agar AI Assistant dapat menjawab pertanyaan karyawan."
                    width={620}
                    onClose={closeSopModal}
                    onSubmit={submitSop}
                    processing={sopForm.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Judul SOP <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            value={sopForm.data.title}
                            onChange={(event) =>
                                sopForm.setData('title', event.target.value)
                            }
                            placeholder="contoh: SOP Pengajuan Cuti Karyawan"
                            style={withError(
                                inputStyle,
                                !!sopForm.errors.title,
                            )}
                        />
                        <FieldError message={sopForm.errors.title} />
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 14,
                        }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>Jenis SOP</label>
                            <select
                                name="sop_category_id"
                                value={sopForm.data.sop_category_id}
                                onChange={(event) =>
                                    sopForm.setData(
                                        'sop_category_id',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    selectStyle,
                                    !!sopForm.errors.sop_category_id,
                                )}
                            >
                                <option value="">Tanpa jenis</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            <FieldError
                                message={sopForm.errors.sop_category_id}
                            />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Kode SOP</label>
                            <input
                                type="text"
                                name="code"
                                value={sopForm.data.code}
                                onChange={(event) =>
                                    sopForm.setData('code', event.target.value)
                                }
                                placeholder="contoh: SOP-HR-001"
                                style={withError(
                                    inputStyle,
                                    !!sopForm.errors.code,
                                )}
                            />
                            <FieldError message={sopForm.errors.code} />
                        </div>
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Tipe SOP <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            name="visibility"
                            value={sopForm.data.visibility}
                            onChange={(event) =>
                                sopForm.setData(
                                    'visibility',
                                    event.target.value as SopVisibility,
                                )
                            }
                            style={withError(
                                selectStyle,
                                !!sopForm.errors.visibility,
                            )}
                        >
                            {VISIBILITY_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={sopForm.errors.visibility} />
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr 1fr',
                            gap: 14,
                        }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>Versi</label>
                            <input
                                type="text"
                                name="version"
                                value={sopForm.data.version}
                                onChange={(event) =>
                                    sopForm.setData(
                                        'version',
                                        event.target.value,
                                    )
                                }
                                placeholder="1.0"
                                style={withError(
                                    inputStyle,
                                    !!sopForm.errors.version,
                                )}
                            />
                            <FieldError message={sopForm.errors.version} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>
                                Tanggal Berlaku
                            </label>
                            <DatePicker
                                value={sopForm.data.effective_date}
                                onChange={(nextValue) =>
                                    sopForm.setData('effective_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                hasError={!!sopForm.errors.effective_date}
                                width="100%"
                            />
                            <FieldError
                                message={sopForm.errors.effective_date}
                            />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>
                                Status <span style={{ color: C.red }}>*</span>
                            </label>
                            <select
                                name="status"
                                value={sopForm.data.status}
                                onChange={(event) =>
                                    sopForm.setData(
                                        'status',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    selectStyle,
                                    !!sopForm.errors.status,
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
                            <FieldError message={sopForm.errors.status} />
                        </div>
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Ringkasan</label>
                        <textarea
                            name="summary"
                            value={sopForm.data.summary}
                            onChange={(event) =>
                                sopForm.setData('summary', event.target.value)
                            }
                            placeholder="Ringkasan singkat isi SOP — dipakai AI Assistant sebagai pengantar jawaban."
                            style={withError(
                                textareaStyle,
                                !!sopForm.errors.summary,
                            )}
                        />
                        <FieldError message={sopForm.errors.summary} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Berkas PDF{' '}
                            {sopModal === 'new' ? (
                                <span style={{ color: C.red }}>*</span>
                            ) : null}
                        </label>
                        <FileDropzone
                            name="file"
                            value={sopForm.data.file}
                            onChange={(file) => sopForm.setData('file', file)}
                            accept="application/pdf,.pdf"
                            hasError={!!sopForm.errors.file}
                            hint="Hanya PDF, maksimal 10 MB. Isinya dibaca otomatis untuk AI Assistant."
                            existing={
                                sopModal !== 'new' && sopModal.file_name
                                    ? {
                                          name: sopModal.file_name,
                                          sizeLabel: sopModal.file_size_label,
                                      }
                                    : null
                            }
                        />
                        <FieldError message={sopForm.errors.file} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Isi SOP (untuk AI Assistant)
                        </label>
                        <textarea
                            name="content"
                            value={sopForm.data.content}
                            onChange={(event) =>
                                sopForm.setData('content', event.target.value)
                            }
                            placeholder="Kosongkan agar diisi otomatis dari teks PDF. Isi manual bila PDF berupa hasil scan."
                            style={{
                                ...withError(
                                    textareaStyle,
                                    !!sopForm.errors.content,
                                ),
                                minHeight: 130,
                            }}
                        />
                        <FieldError message={sopForm.errors.content} />
                    </div>
                </ModalShell>
            )}

            {/* Jenis SOP create/edit modal */}
            {categoryModal !== null && (
                <ModalShell
                    title={
                        categoryModal === 'new'
                            ? 'Tambah Jenis SOP'
                            : 'Ubah Jenis SOP'
                    }
                    subtitle="Jenis SOP mengelompokkan dokumen, misalnya Kepegawaian, Keuangan, atau K3."
                    width={460}
                    onClose={closeCategoryModal}
                    onSubmit={submitCategory}
                    processing={categoryForm.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Nama Jenis <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            value={categoryForm.data.name}
                            onChange={(event) =>
                                categoryForm.setData('name', event.target.value)
                            }
                            placeholder="contoh: Kepegawaian"
                            style={withError(
                                inputStyle,
                                !!categoryForm.errors.name,
                            )}
                        />
                        <FieldError message={categoryForm.errors.name} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Kode</label>
                        <input
                            type="text"
                            name="code"
                            value={categoryForm.data.code}
                            onChange={(event) =>
                                categoryForm.setData('code', event.target.value)
                            }
                            placeholder="contoh: HR"
                            style={withError(
                                inputStyle,
                                !!categoryForm.errors.code,
                            )}
                        />
                        <FieldError message={categoryForm.errors.code} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Keterangan</label>
                        <textarea
                            name="description"
                            placeholder="cth. Prosedur baku untuk tim operasional cabang"
                            value={categoryForm.data.description}
                            onChange={(event) =>
                                categoryForm.setData(
                                    'description',
                                    event.target.value,
                                )
                            }
                            style={withError(
                                textareaStyle,
                                !!categoryForm.errors.description,
                            )}
                        />
                        <FieldError message={categoryForm.errors.description} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Status <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            name="status"
                            value={categoryForm.data.status}
                            onChange={(event) =>
                                categoryForm.setData(
                                    'status',
                                    event.target.value,
                                )
                            }
                            style={withError(
                                selectStyle,
                                !!categoryForm.errors.status,
                            )}
                        >
                            {STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={categoryForm.errors.status} />
                    </div>
                </ModalShell>
            )}

            {/* Confirm delete modals */}
            {confirmSop && (
                <ConfirmModal
                    title="Hapus SOP?"
                    body={
                        <>
                            SOP{' '}
                            <strong style={{ color: C.text }}>
                                {confirmSop.title}
                            </strong>{' '}
                            beserta berkas PDF-nya akan dihapus dan tidak lagi
                            dipakai AI Assistant.
                        </>
                    }
                    onCancel={() => setConfirmSop(null)}
                    onConfirm={deleteSop}
                />
            )}

            {confirmCategory && (
                <ConfirmModal
                    title="Hapus jenis SOP?"
                    body={
                        <>
                            Jenis{' '}
                            <strong style={{ color: C.text }}>
                                {confirmCategory.name}
                            </strong>{' '}
                            akan dihapus. {confirmCategory.sop_count} dokumen
                            SOP yang memakainya tetap ada, tetapi menjadi tanpa
                            jenis.
                        </>
                    }
                    onCancel={() => setConfirmCategory(null)}
                    onConfirm={deleteCategory}
                />
            )}
        </>
    );
}
