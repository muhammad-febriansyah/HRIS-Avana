import { router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ChevronUp,
    ChevronDown,
    Search,
} from 'lucide-react';
import * as React from 'react';
import type { ReactNode } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { EmptyState } from './empty-state';

export interface DataTableColumn<T> {
    key: string;
    header: string;
    className?: string;
    align?: 'left' | 'right' | 'center';
    sortable?: boolean;
    render?: (row: T) => ReactNode;
}

export interface DataTableMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface DataTableProps<T> {
    columns: DataTableColumn<T>[];
    rows: T[];
    meta: DataTableMeta;
    filters: Record<string, string | undefined>;
    searchPlaceholder?: string;
    toolbarExtra?: ReactNode;
    emptyState?: ReactNode;
    onRowClick?: (row: T) => void;
    rowKey?: (row: T, index: number) => string | number;
    className?: string;
}

type QueryValue = string | number | undefined;

function alignClass(align?: 'left' | 'right' | 'center'): string {
    if (align === 'right') {
        return 'text-right';
    }

    if (align === 'center') {
        return 'text-center';
    }

    return 'text-left';
}

/** Builds the list of page numbers to render, inserting ellipsis gaps. */
function buildPageRange(
    current: number,
    last: number,
): (number | 'ellipsis')[] {
    if (last <= 1) {
        return [1];
    }

    const candidates = new Set<number>([
        1,
        last,
        current,
        current - 1,
        current + 1,
    ]);
    const sorted = [...candidates]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    const result: (number | 'ellipsis')[] = [];
    let previous = 0;

    for (const page of sorted) {
        if (page - previous > 1) {
            result.push('ellipsis');
        }

        result.push(page);
        previous = page;
    }

    return result;
}

/**
 * Server-side DataTable driven by Inertia. Search, sorting and pagination all
 * round-trip through `router.get` so the backend (a Laravel paginator) is the
 * single source of truth. Generic over the row shape `T`.
 */
export function DataTable<T extends Record<string, any>>({
    columns,
    rows,
    meta,
    filters,
    searchPlaceholder = 'Cari…',
    toolbarExtra,
    emptyState,
    onRowClick,
    rowKey,
    className,
}: DataTableProps<T>) {
    const [search, setSearch] = React.useState<string>(filters.search ?? '');
    const [loading, setLoading] = React.useState<boolean>(false);

    const currentSort = filters.sort;
    const currentDirection = filters.direction === 'desc' ? 'desc' : 'asc';

    const navigate = React.useCallback(
        (params: Record<string, QueryValue>) => {
            const query: Record<string, string | number> = {};

            for (const [key, value] of Object.entries(filters)) {
                if (value !== undefined && value !== '') {
                    query[key] = value;
                }
            }

            for (const [key, value] of Object.entries(params)) {
                if (value === undefined || value === '') {
                    delete query[key];
                } else {
                    query[key] = value;
                }
            }

            router.get(window.location.pathname, query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
        [filters],
    );

    // Debounced search; skips firing when the term already matches the URL.
    React.useEffect(() => {
        if ((filters.search ?? '') === search) {
            return;
        }

        const handler = setTimeout(() => {
            navigate({ search: search || undefined, page: 1 });
        }, 300);

        return () => clearTimeout(handler);
    }, [search, filters.search, navigate]);

    const handleSort = (key: string) => {
        let direction: 'asc' | 'desc' = 'asc';

        if (currentSort === key && currentDirection === 'asc') {
            direction = 'desc';
        }

        navigate({ sort: key, direction, page: 1 });
    };

    const goToPage = (page: number) => {
        if (page < 1 || page > meta.last_page || page === meta.current_page) {
            return;
        }

        navigate({ page });
    };

    const isEmpty = rows.length === 0;
    const pages = buildPageRange(meta.current_page, meta.last_page);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-slate-200/70 bg-white shadow-sm',
                className,
            )}
        >
            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-2.5 border-b border-slate-200/70 px-4 py-3">
                <div className="relative min-w-[200px] flex-1 sm:max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={searchPlaceholder}
                        className="h-9 w-full rounded-md border border-transparent bg-slate-50 pr-3 pl-9 text-sm text-slate-700 transition-[color,box-shadow] outline-none placeholder:text-slate-400 focus-visible:border-[#2F54C9]/40 focus-visible:bg-white focus-visible:ring-[3px] focus-visible:ring-[#2F54C9]/15"
                    />
                </div>
                {toolbarExtra && (
                    <div className="flex flex-wrap items-center gap-2.5">
                        {toolbarExtra}
                    </div>
                )}
            </div>

            {/* Table / empty state */}
            <div className="relative overflow-x-auto">
                {isEmpty ? (
                    <div className="p-6">
                        {emptyState ?? (
                            <EmptyState
                                title="Belum ada data"
                                description="Data yang Anda cari tidak ditemukan."
                            />
                        )}
                    </div>
                ) : (
                    <table
                        className={cn(
                            'w-full border-collapse text-sm transition-opacity',
                            loading && 'opacity-60',
                        )}
                    >
                        <thead>
                            <tr className="bg-[#FAFBFD]">
                                {columns.map((column) => {
                                    const sortable = column.sortable !== false;
                                    const active = currentSort === column.key;

                                    return (
                                        <th
                                            key={column.key}
                                            className={cn(
                                                'px-4 py-3 text-[11.5px] font-semibold tracking-wide text-slate-400 uppercase',
                                                alignClass(column.align),
                                                column.className,
                                            )}
                                        >
                                            {sortable ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleSort(column.key)
                                                    }
                                                    className={cn(
                                                        'inline-flex items-center gap-1 uppercase transition-colors hover:text-slate-600',
                                                        active &&
                                                            'text-[#2F54C9]',
                                                        column.align ===
                                                            'right' &&
                                                            'flex-row-reverse',
                                                        column.align ===
                                                            'center' &&
                                                            'mx-auto',
                                                    )}
                                                >
                                                    {column.header}
                                                    {active ? (
                                                        currentDirection ===
                                                        'asc' ? (
                                                            <ChevronUp className="size-3.5" />
                                                        ) : (
                                                            <ChevronDown className="size-3.5" />
                                                        )
                                                    ) : (
                                                        <ChevronsUpDown className="size-3.5 text-slate-300" />
                                                    )}
                                                </button>
                                            ) : (
                                                <span>{column.header}</span>
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={rowKey ? rowKey(row, index) : index}
                                    onClick={
                                        onRowClick
                                            ? () => onRowClick(row)
                                            : undefined
                                    }
                                    className={cn(
                                        'border-t border-slate-100 transition-colors',
                                        onRowClick &&
                                            'cursor-pointer hover:bg-slate-50/70',
                                    )}
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={cn(
                                                'px-4 py-3 text-slate-600',
                                                alignClass(column.align),
                                                column.className,
                                            )}
                                        >
                                            {column.render
                                                ? column.render(row)
                                                : (row[
                                                      column.key
                                                  ] as ReactNode)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/50 backdrop-blur-[1px]">
                        <Spinner className="size-6 text-[#2F54C9]" />
                    </div>
                )}
            </div>

            {/* Footer / pagination */}
            {meta.total > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/70 px-4 py-3">
                    <p className="text-sm text-slate-500">
                        Menampilkan{' '}
                        <span className="font-medium text-slate-700">
                            {meta.from}–{meta.to}
                        </span>{' '}
                        dari{' '}
                        <span className="font-medium text-slate-700">
                            {meta.total}
                        </span>
                    </p>
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => goToPage(meta.current_page - 1)}
                            disabled={meta.current_page <= 1 || loading}
                            className="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-slate-200 bg-white px-2 text-sm text-slate-500 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronLeft className="size-4" />
                        </button>
                        {pages.map((page, index) =>
                            page === 'ellipsis' ? (
                                <span
                                    key={`ellipsis-${index}`}
                                    className="px-1 text-slate-400"
                                >
                                    …
                                </span>
                            ) : (
                                <button
                                    key={page}
                                    type="button"
                                    onClick={() => goToPage(page)}
                                    disabled={loading}
                                    aria-current={
                                        page === meta.current_page
                                            ? 'page'
                                            : undefined
                                    }
                                    className={cn(
                                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition-colors',
                                        page === meta.current_page
                                            ? 'bg-[#2F54C9] font-semibold text-white'
                                            : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                    )}
                                >
                                    {page}
                                </button>
                            ),
                        )}
                        <button
                            type="button"
                            onClick={() => goToPage(meta.current_page + 1)}
                            disabled={
                                meta.current_page >= meta.last_page || loading
                            }
                            className="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-slate-200 bg-white px-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronRight className="size-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default DataTable;
