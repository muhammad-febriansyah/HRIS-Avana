import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import AnnouncementController from '@/actions/App/Http/Controllers/Avana/AnnouncementController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import { type Announcement, makeColumns } from './columns';
import { DataTable } from './data-table';

/* ============================================================
 * Announcement list — a sortable/searchable/paginated data table.
 * ============================================================ */

type AnnouncementCard = Announcement;

interface AnnouncementKpis {
    total: number;
    published: number;
    draft: number;
}

interface PengumumanIndexProps {
    announcements: AnnouncementCard[];
    kpis: AnnouncementKpis;
}

type FlashProps = { flash?: { success?: string } };

interface AnnouncementFormData {
    title: string;
    category: string;
    body: string;
    pinned: boolean;
}

const emptyForm: AnnouncementFormData = {
    title: '',
    category: '',
    body: '',
    pinned: false,
};

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    resize: 'vertical',
    minHeight: 120,
};

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

export default function PengumumanIndex({
    announcements,
    kpis,
}: PengumumanIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<AnnouncementCard | null>(null);
    const [confirm, setConfirm] = useState<AnnouncementCard | null>(null);

    const form = useForm<AnnouncementFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const openEdit = (item: AnnouncementCard) => {
        setEditing(item);
        form.clearErrors();
        form.setData({
            title: item.title,
            category: item.category ?? '',
            body: item.body,
            pinned: item.pinned,
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing) {
            form.submit(AnnouncementController.update(editing.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            form.submit(AnnouncementController.store(), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const publish = (item: AnnouncementCard) => {
        router.post(
            AnnouncementController.publish(item.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const remove = () => {
        if (!confirm) {
            return;
        }

        router.delete(AnnouncementController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const columns = useMemo(
        () => makeColumns({ onEdit: openEdit, onPublish: publish, onDelete: setConfirm }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const kpiItems = [
        {
            label: 'Total Pengumuman',
            value: kpis.total,
            icon: 'megaphone',
            color: C.primary,
        },
        {
            label: 'Terbit',
            value: kpis.published,
            icon: 'send',
            color: C.green,
        },
        { label: 'Draft', value: kpis.draft, icon: 'file-pen', color: C.amber },
    ];

    return (
        <>
            <Head title="Pengumuman" />
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Pengumuman</span>
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
                            Pengumuman
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Sebarkan informasi penting ke seluruh karyawan.
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Buat Pengumuman
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

                <DataTable
                    columns={columns}
                    data={announcements}
                    searchPlaceholder="Cari judul, kategori…"
                />
            </div>

            {modalOpen && (
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
                        onClick={closeModal}
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
                            maxWidth: 520,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            {editing ? 'Ubah Pengumuman' : 'Buat Pengumuman'}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            {editing
                                ? 'Perbarui isi pengumuman.'
                                : 'Pengumuman dibuat sebagai draft, lalu dapat diterbitkan.'}
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={labelStyle}>
                                    Judul{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    placeholder="Judul pengumuman"
                                    style={inputStyle}
                                />
                                {form.errors.title && (
                                    <FieldError message={form.errors.title} />
                                )}
                            </div>
                            <div>
                                <label style={labelStyle}>Kategori</label>
                                <input
                                    type="text"
                                    value={form.data.category}
                                    onChange={(e) =>
                                        form.setData('category', e.target.value)
                                    }
                                    placeholder="Umum, Libur, Penting, ..."
                                    style={inputStyle}
                                />
                                {form.errors.category && (
                                    <FieldError
                                        message={form.errors.category}
                                    />
                                )}
                            </div>
                            <div>
                                <label style={labelStyle}>
                                    Isi <span style={{ color: C.red }}>*</span>
                                </label>
                                <textarea
                                    value={form.data.body}
                                    onChange={(e) =>
                                        form.setData('body', e.target.value)
                                    }
                                    placeholder="Tulis isi pengumuman..."
                                    style={textareaStyle}
                                />
                                {form.errors.body && (
                                    <FieldError message={form.errors.body} />
                                )}
                            </div>
                            <label
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    cursor: 'pointer',
                                    fontSize: 13.5,
                                    color: C.text,
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.pinned}
                                    onChange={(e) =>
                                        form.setData('pinned', e.target.checked)
                                    }
                                    style={{
                                        width: 16,
                                        height: 16,
                                        cursor: 'pointer',
                                    }}
                                />
                                Sematkan pengumuman ini di bagian atas
                            </label>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeModal}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name="x" size={16} />
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: form.processing ? 0.7 : 1,
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {confirm && (
                <ConfirmModal
                    title="Hapus pengumuman?"
                    body={
                        <>
                            Pengumuman{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.title}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={remove}
                />
            )}
        </>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div
            style={{
                fontSize: 12,
                color: C.red,
                marginTop: 6,
                display: 'flex',
                alignItems: 'center',
                gap: 5,
            }}
        >
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

function ConfirmModal({
    title,
    body,
    onCancel,
    onConfirm,
}: {
    title: string;
    body: React.ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
}) {
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
                onClick={onCancel}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 400,
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                }}
            >
                <div
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 12,
                        background: 'rgba(220,38,38,.1)',
                        color: C.red,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.muted,
                        marginTop: 8,
                        lineHeight: 1.55,
                    }}
                >
                    {body}
                </div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        onClick={onCancel}
                        style={{
                            ...btnOut,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="x" size={16} />
                        Batal
                    </button>
                    <button
                        onClick={onConfirm}
                        style={{
                            flex: 1,
                            height: 44,
                            background: C.red,
                            color: '#fff',
                            border: 'none',
                            borderRadius: 9,
                            fontSize: 14,
                            fontWeight: 600,
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                        }}
                    >
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}
