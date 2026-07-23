import {
    type ColumnDef,
    type SortingState,
    type VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { AIcon, C, card } from '@/lib/avana';

const ctrlBtn: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    height: 38,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    background: '#fff',
    color: C.text,
    fontSize: 13,
    fontWeight: 500,
    cursor: 'pointer',
};

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    searchPlaceholder?: string;
    pageSize?: number;
}

/** Column label used in the show/hide menu, from the column's meta. */
function columnLabel<TData, TValue>(column: {
    id: string;
    columnDef: ColumnDef<TData, TValue>;
}): string {
    const meta = column.columnDef.meta as { label?: string } | undefined;

    return meta?.label ?? column.id;
}

export function DataTable<TData, TValue>({
    columns,
    data,
    searchPlaceholder = 'Cari…',
    pageSize = 10,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [globalFilter, setGlobalFilter] = useState('');
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        onColumnVisibilityChange: setColumnVisibility,
        globalFilterFn: 'includesString',
        state: { sorting, globalFilter, columnVisibility },
        initialState: { pagination: { pageSize } },
    });

    return (
        <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12, flexWrap: 'wrap' }}>
                <div style={{ position: 'relative' }}>
                    <span style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none', display: 'flex' }}>
                        <AIcon name="search" size={15} color={C.faint} />
                    </span>
                    <input
                        value={globalFilter}
                        onChange={(e) => setGlobalFilter(e.target.value)}
                        placeholder={searchPlaceholder}
                        style={{
                            height: 38,
                            padding: '0 12px 0 34px',
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            fontSize: 13.5,
                            color: C.text,
                            background: '#fff',
                            outline: 'none',
                            width: 280,
                            maxWidth: '100%',
                        }}
                    />
                </div>
                <div style={{ flex: 1 }} />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button type="button" style={ctrlBtn}>
                            <AIcon name="sliders-horizontal" size={15} color={C.muted} />
                            Kolom
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {table
                            .getAllColumns()
                            .filter((column) => column.getCanHide())
                            .map((column) => (
                                <DropdownMenuCheckboxItem
                                    key={column.id}
                                    checked={column.getIsVisible()}
                                    onCheckedChange={(value) => column.toggleVisibility(!!value)}
                                >
                                    {columnLabel(column)}
                                </DropdownMenuCheckboxItem>
                            ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} style={{ background: C.surface }}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id} style={{ padding: '12px 16px', color: C.muted }}>
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} style={{ padding: '12px 16px' }}>
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    style={{ padding: 40, textAlign: 'center', color: C.faint, fontSize: 13.5 }}
                                >
                                    Tidak ada data.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginTop: 12, flexWrap: 'wrap', gap: 10 }}>
                <span style={{ fontSize: 12.5, color: C.muted }}>
                    {table.getFilteredRowModel().rows.length} baris · halaman{' '}
                    {table.getState().pagination.pageIndex + 1} dari {table.getPageCount() || 1}
                </span>
                <div style={{ display: 'flex', gap: 8 }}>
                    <button
                        type="button"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                        style={{ ...ctrlBtn, opacity: table.getCanPreviousPage() ? 1 : 0.5, cursor: table.getCanPreviousPage() ? 'pointer' : 'default' }}
                    >
                        <AIcon name="chevron-left" size={15} color={C.muted} />
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                        style={{ ...ctrlBtn, opacity: table.getCanNextPage() ? 1 : 0.5, cursor: table.getCanNextPage() ? 'pointer' : 'default' }}
                    >
                        Berikutnya
                        <AIcon name="chevron-right" size={15} color={C.muted} />
                    </button>
                </div>
            </div>
        </div>
    );
}
