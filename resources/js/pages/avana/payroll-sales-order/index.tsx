import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import SalesOrderController from '@/actions/App/Http/Controllers/Avana/SalesOrderController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, card } from '@/lib/avana';

interface Order {
    id: number;
    code: string;
    client_name: string;
    position_name: string;
    headcount: number;
    contract_start: string | null;
    contract_end: string | null;
    status: string;
    salary_master: string | null;
    shift: string | null;
    leave_type: string | null;
    salary_master_id: number | null;
    shift_id: number | null;
    leave_type_id: number | null;
}

interface Option {
    id: number;
    name: string;
}

interface Props {
    orders: Order[];
    filters: { search?: string; status?: string };
    masterOptions: Option[];
    shiftOptions: Option[];
    leaveOptions: Option[];
}

const input: React.CSSProperties = {
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    background: '#fff',
};
const th: React.CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const primaryBtn: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    padding: '9px 16px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};

export default function PayrollSalesOrder({
    orders,
    filters,
    masterOptions,
    shiftOptions,
    leaveOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [mapping, setMapping] = useState<Order | null>(null);

    const applyFilter = (next: { search?: string; status?: string }) =>
        router.get(
            SalesOrderController.index().url,
            { search, status, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Sales Order" />
            <div style={{ padding: '28px 32px' }}>
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
                    <span>Payroll</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Sales Order</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Sales Order
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Mapping benefit payroll ke Sales Order dari Marketing —
                    tempel Master Gaji, shift kerja & jenis cuti, lalu
                    diteruskan ke Rekrutmen.
                </div>

                {/* Filter */}
                <div
                    style={{
                        display: 'flex',
                        gap: 12,
                        marginBottom: 16,
                        flexWrap: 'wrap',
                    }}
                >
                    <input
                        style={{ ...input, flex: 1, minWidth: 220 }}
                        placeholder="Cari klien / posisi / kode…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                applyFilter({ search });
                            }
                        }}
                    />
                    <select
                        style={{ ...input, minWidth: 160, cursor: 'pointer' }}
                        value={status}
                        onChange={(e) => {
                            setStatus(e.target.value);
                            applyFilter({ status: e.target.value });
                        }}
                    >
                        <option value="">Semua status</option>
                        <option value="new">Baru (belum di-mapping)</option>
                        <option value="mapped">Sudah di-mapping</option>
                    </select>
                </div>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                            }}
                        >
                            <thead>
                                <tr>
                                    {[
                                        'Kode',
                                        'Klien',
                                        'Posisi',
                                        'Qty',
                                        'Kontrak',
                                        'Benefit (Master / Shift / Cuti)',
                                        'Status',
                                        '',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {orders.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada Sales Order.
                                        </td>
                                    </tr>
                                ) : (
                                    orders.map((o) => (
                                        <tr key={o.id}>
                                            <td style={td}>{o.code}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {o.client_name}
                                            </td>
                                            <td style={td}>
                                                {o.position_name}
                                            </td>
                                            <td style={td}>{o.headcount}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                    fontSize: 12.5,
                                                }}
                                            >
                                                {o.contract_start
                                                    ? `${o.contract_start} – ${o.contract_end ?? '…'}`
                                                    : '—'}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                    fontSize: 12.5,
                                                }}
                                            >
                                                {o.status === 'mapped'
                                                    ? `${o.salary_master ?? '—'} · ${o.shift ?? '—'} · ${o.leave_type ?? '—'}`
                                                    : '—'}
                                            </td>
                                            <td style={td}>
                                                <span
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 6,
                                                        fontSize: 11.5,
                                                        fontWeight: 700,
                                                        padding: '3px 9px',
                                                        borderRadius: 6,
                                                        color:
                                                            o.status ===
                                                            'mapped'
                                                                ? '#15803D'
                                                                : '#64748B',
                                                        background:
                                                            o.status ===
                                                            'mapped'
                                                                ? '#DCFCE7'
                                                                : '#F1F5F9',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            width: 7,
                                                            height: 7,
                                                            borderRadius: 999,
                                                            background:
                                                                o.status ===
                                                                'mapped'
                                                                    ? '#16A34A'
                                                                    : '#94A3B8',
                                                        }}
                                                    />
                                                    {o.status === 'mapped'
                                                        ? 'Mapped'
                                                        : 'Baru'}
                                                </span>
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                <button
                                                    onClick={() =>
                                                        setMapping(o)
                                                    }
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 6,
                                                        padding: '6px 12px',
                                                        borderRadius: 7,
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        cursor: 'pointer',
                                                        border:
                                                            o.status ===
                                                            'mapped'
                                                                ? `1px solid ${C.border}`
                                                                : 'none',
                                                        background:
                                                            o.status ===
                                                            'mapped'
                                                                ? '#fff'
                                                                : C.primary,
                                                        color:
                                                            o.status ===
                                                            'mapped'
                                                                ? C.text
                                                                : '#fff',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="link"
                                                        size={13}
                                                        color={
                                                            o.status ===
                                                            'mapped'
                                                                ? C.muted
                                                                : '#fff'
                                                        }
                                                    />
                                                    {o.status === 'mapped'
                                                        ? 'Ubah'
                                                        : 'Mapping'}
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {mapping && (
                <MappingModal
                    order={mapping}
                    masterOptions={masterOptions}
                    shiftOptions={shiftOptions}
                    leaveOptions={leaveOptions}
                    onClose={() => setMapping(null)}
                />
            )}
        </>
    );
}

function MappingModal({
    order,
    masterOptions,
    shiftOptions,
    leaveOptions,
    onClose,
}: {
    order: Order;
    masterOptions: Option[];
    shiftOptions: Option[];
    leaveOptions: Option[];
    onClose: () => void;
}) {
    const form = useForm({
        salary_master_id: order.salary_master_id
            ? String(order.salary_master_id)
            : '',
        shift_id: order.shift_id ? String(order.shift_id) : '',
        leave_type_id: order.leave_type_id ? String(order.leave_type_id) : '',
    });

    const submit = () =>
        form.post(SalesOrderController.map(order.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Sales Order di-mapping');
                onClose();
            },
            onError: () => toast.error('Pilih minimal Master Gaji'),
        });

    const field = (label: string, node: React.ReactNode) => (
        <div style={{ marginBottom: 14 }}>
            <label
                style={{
                    display: 'block',
                    fontSize: 13,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 6,
                }}
            >
                {label}
            </label>
            {node}
        </div>
    );

    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(15,23,42,.45)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 80,
                padding: 20,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    width: 460,
                    maxWidth: '100%',
                    background: '#fff',
                    borderRadius: 14,
                    padding: 24,
                    boxShadow: '0 24px 60px rgba(15,23,42,.24)',
                }}
            >
                <div
                    style={{
                        fontSize: 16,
                        fontWeight: 700,
                        color: C.navy,
                        marginBottom: 2,
                    }}
                >
                    Sinkronisasi Sales Order
                </div>
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        marginBottom: 16,
                    }}
                >
                    {order.code} · {order.client_name} · {order.position_name} ·{' '}
                    {order.headcount} orang
                </div>

                {field(
                    'Master Gaji *',
                    <SearchableSelect
                        value={form.data.salary_master_id}
                        onChange={(v) => form.setData('salary_master_id', v)}
                        placeholder="Pilih Master Gaji…"
                        searchPlaceholder="Cari master gaji…"
                        options={masterOptions.map((m) => ({
                            value: String(m.id),
                            label: m.name,
                        }))}
                    />,
                )}
                {field(
                    'Shift Kerja',
                    <SearchableSelect
                        value={form.data.shift_id}
                        onChange={(v) => form.setData('shift_id', v)}
                        placeholder="Pilih shift (opsional)…"
                        allowClear
                        options={shiftOptions.map((s) => ({
                            value: String(s.id),
                            label: s.name,
                        }))}
                    />,
                )}
                {field(
                    'Jenis Cuti',
                    <SearchableSelect
                        value={form.data.leave_type_id}
                        onChange={(v) => form.setData('leave_type_id', v)}
                        placeholder="Pilih jenis cuti (opsional)…"
                        allowClear
                        options={leaveOptions.map((l) => ({
                            value: String(l.id),
                            label: l.name,
                        }))}
                    />,
                )}

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                        marginTop: 20,
                    }}
                >
                    <button
                        onClick={onClose}
                        disabled={form.processing}
                        style={{
                            padding: '9px 16px',
                            borderRadius: 8,
                            border: `1px solid ${C.line}`,
                            background: '#fff',
                            color: C.muted,
                            fontSize: 13,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        Batal
                    </button>
                    <button
                        onClick={submit}
                        disabled={
                            form.processing || !form.data.salary_master_id
                        }
                        style={{
                            ...primaryBtn,
                            opacity:
                                form.processing || !form.data.salary_master_id
                                    ? 0.6
                                    : 1,
                        }}
                    >
                        <AIcon name="save" size={15} color="#fff" />
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    );
}
