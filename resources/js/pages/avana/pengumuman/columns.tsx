import type { Column, ColumnDef, RowData } from '@tanstack/react-table';
import type { CSSProperties } from 'react';
import { formatFileSize } from '@/components/avana/file-dropzone';
import { AIcon, ActionBtn, C } from '@/lib/avana';

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        /** Human label shown in the column show/hide menu. */
        label?: string;
    }
}

export interface AnnouncementAttachment {
    url: string;
    name: string | null;
    mime: string | null;
    size: number | null;
    is_image: boolean;
}

export interface Announcement {
    id: number;
    title: string;
    body: string;
    category: string | null;
    status: string;
    pinned: boolean;
    published_at: string | null;
    created_at: string | null;
    attachment: AnnouncementAttachment | null;
}

interface Handlers {
    onEdit: (a: Announcement) => void;
    onPublish: (a: Announcement) => void;
    onDelete: (a: Announcement) => void;
}

const pillBase: CSSProperties = {
    display: 'inline-block',
    padding: '3px 10px',
    borderRadius: 100,
    fontSize: 11.5,
    fontWeight: 600,
};

function Pill({
    text,
    color,
    bg,
}: {
    text: string;
    color: string;
    bg: string;
}) {
    return <span style={{ ...pillBase, color, background: bg }}>{text}</span>;
}

/** A sortable column header rendered in the Avana palette. */
function SortHeader<T>({
    column,
    label,
}: {
    column: Column<T, unknown>;
    label: string;
}) {
    const sorted = column.getIsSorted();

    return (
        <button
            type="button"
            onClick={() => column.toggleSorting(sorted === 'asc')}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                background: C.surface,
                border: `1px solid ${C.border}`,
                borderRadius: 6,
                cursor: 'pointer',
                padding: '4px 8px',
                fontSize: 11.5,
                fontWeight: 600,
                letterSpacing: '.03em',
                textTransform: 'uppercase',
                color: C.muted,
            }}
        >
            {label}
            <AIcon
                name={
                    sorted === 'asc'
                        ? 'arrow-up'
                        : sorted === 'desc'
                          ? 'arrow-down'
                          : 'chevrons-up-down'
                }
                size={13}
                color={sorted ? C.primary : C.faint}
            />
        </button>
    );
}

export function makeColumns({
    onEdit,
    onPublish,
    onDelete,
}: Handlers): ColumnDef<Announcement>[] {
    return [
        {
            accessorKey: 'title',
            meta: { label: 'Judul' },
            header: ({ column }) => (
                <SortHeader column={column} label="Judul" />
            ),
            cell: ({ row }) => {
                const a = row.original;

                return (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            minWidth: 0,
                        }}
                    >
                        {a.pinned && (
                            <AIcon name="pin" size={14} color={C.primary} />
                        )}
                        <div style={{ minWidth: 0 }}>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                    whiteSpace: 'nowrap',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    maxWidth: 340,
                                }}
                            >
                                {a.title}
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.faint,
                                    whiteSpace: 'nowrap',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    maxWidth: 340,
                                }}
                            >
                                {a.body}
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'category',
            meta: { label: 'Kategori' },
            header: ({ column }) => (
                <SortHeader column={column} label="Kategori" />
            ),
            cell: ({ row }) =>
                row.original.category ? (
                    <Pill
                        text={row.original.category}
                        color={C.sky}
                        bg="rgba(110,155,230,.15)"
                    />
                ) : (
                    <span style={{ color: C.faint }}>—</span>
                ),
        },
        {
            id: 'lampiran',
            meta: { label: 'Lampiran' },
            enableSorting: false,
            accessorFn: (a) => a.attachment?.name ?? '',
            header: () => (
                <span
                    style={{
                        fontSize: 11.5,
                        fontWeight: 600,
                        letterSpacing: '.03em',
                        textTransform: 'uppercase',
                        color: C.muted,
                    }}
                >
                    Lampiran
                </span>
            ),
            cell: ({ row }) => {
                const file = row.original.attachment;

                if (!file) {
                    return <span style={{ color: C.faint }}>—</span>;
                }

                return (
                    <ActionBtn
                        icon={file.is_image ? 'image' : 'file-text'}
                        label={
                            file.size !== null
                                ? formatFileSize(file.size)
                                : file.is_image
                                  ? 'Gambar'
                                  : 'PDF'
                        }
                        title={file.name ?? undefined}
                        variant={file.is_image ? 'success' : 'primary'}
                        href={file.url}
                    />
                );
            },
        },
        {
            accessorKey: 'status',
            meta: { label: 'Status' },
            header: ({ column }) => (
                <SortHeader column={column} label="Status" />
            ),
            cell: ({ row }) =>
                row.original.status === 'published' ? (
                    <Pill
                        text="Terbit"
                        color={C.green}
                        bg="rgba(22,163,74,.1)"
                    />
                ) : (
                    <Pill
                        text="Draft"
                        color={C.muted}
                        bg="rgba(107,114,128,.12)"
                    />
                ),
        },
        {
            id: 'tanggal',
            meta: { label: 'Tanggal' },
            accessorFn: (a) => a.published_at ?? a.created_at ?? '',
            header: ({ column }) => (
                <SortHeader column={column} label="Tanggal" />
            ),
            cell: ({ row }) => {
                const a = row.original;
                const label =
                    a.status === 'published' && a.published_at
                        ? `Terbit ${a.published_at}`
                        : `Dibuat ${a.created_at ?? ''}`;

                return (
                    <span
                        style={{
                            fontSize: 12.5,
                            color: C.muted,
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {label}
                    </span>
                );
            },
        },
        {
            id: 'aksi',
            meta: { label: 'Aksi' },
            enableHiding: false,
            enableSorting: false,
            header: () => (
                <span
                    style={{
                        fontSize: 11.5,
                        fontWeight: 600,
                        letterSpacing: '.03em',
                        textTransform: 'uppercase',
                        color: C.muted,
                    }}
                >
                    Aksi
                </span>
            ),
            cell: ({ row }) => {
                const a = row.original;

                return (
                    <div
                        style={{
                            display: 'inline-flex',
                            gap: 6,
                            flexWrap: 'wrap',
                        }}
                    >
                        {a.status !== 'published' && (
                            <ActionBtn
                                icon="send"
                                label="Terbitkan"
                                variant="primary"
                                onClick={() => onPublish(a)}
                            />
                        )}
                        <ActionBtn
                            icon="pencil"
                            label="Ubah"
                            variant="success"
                            onClick={() => onEdit(a)}
                        />
                        <ActionBtn
                            icon="trash-2"
                            label="Hapus"
                            variant="danger"
                            onClick={() => onDelete(a)}
                        />
                    </div>
                );
            },
        },
    ];
}
