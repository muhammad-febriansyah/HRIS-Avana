import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LeaveController from '@/actions/App/Http/Controllers/Avana/LeaveController';
import LeaveTypeController from '@/actions/App/Http/Controllers/Avana/LeaveTypeController';
import OvertimeController from '@/actions/App/Http/Controllers/Avana/OvertimeController';
import PermissionRequestController from '@/actions/App/Http/Controllers/Avana/PermissionRequestController';
import WfhController from '@/actions/App/Http/Controllers/Avana/WfhController';
import { AIcon, btnOut, C, card, statusBadge } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** A single leave request as serialized by `LeaveRequestResource`. */
interface LeaveRequest {
    id: number;
    employee: {
        id: number;
        name: string;
        employee_number: string;
        initials: string;
        avatar_color: string;
    } | null;
    leave_type: { id: number; name: string } | null;
    start_date: string;
    end_date: string;
    start_date_raw: string | null;
    end_date_raw: string | null;
    total_days: number;
    durasi: string;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected';
    status_label: 'Menunggu' | 'Disetujui' | 'Ditolak';
}

interface LeaveTypeOption {
    id: number;
    name: string;
    default_quota: number;
}

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

interface LeaveBalance {
    id: number;
    jenis: string;
    total: number;
    sisa: number;
    pct: string;
}

interface CutiFilters {
    search?: string;
    status?: 'pending' | 'approved' | 'rejected';
    leave_type_id?: string;
    sort?: string;
    direction?: string;
    per_page?: string;
}

/** Shared shape of a request row's employee summary across the new submodules. */
interface RequestEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

type RequestStatus = 'pending' | 'approved' | 'rejected';
type RequestStatusLabel = 'Menunggu' | 'Disetujui' | 'Ditolak';

interface OvertimeRow {
    id: number;
    employee: RequestEmployee | null;
    date: string | null;
    hours: number;
    reason: string | null;
    status: RequestStatus;
    status_label: RequestStatusLabel;
}

interface PermissionRow {
    id: number;
    employee: RequestEmployee | null;
    date: string | null;
    type: string;
    start_time: string | null;
    end_time: string | null;
    reason: string | null;
    status: RequestStatus;
    status_label: RequestStatusLabel;
}

interface WfhRow {
    id: number;
    employee: RequestEmployee | null;
    start_date: string | null;
    end_date: string | null;
    reason: string | null;
    status: RequestStatus;
    status_label: RequestStatusLabel;
}

type TabKey = 'cuti' | 'lembur' | 'izin' | 'wfh';

interface CutiProps {
    requests: {
        data: LeaveRequest[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: CutiFilters;
    leaveTypes: LeaveTypeOption[];
    employees: EmployeeOption[];
    balances: LeaveBalance[];
    overtimeRequests: OvertimeRow[];
    permissionRequests: PermissionRow[];
    wfhRequests: WfhRow[];
}

/** Form payload backing the "Ajukan Cuti" form. */
interface LeaveFormData {
    employee_id: string;
    leave_type_id: string;
    start_date: string;
    end_date: string;
    reason: string;
}

/** Color cycle for saldo cards (balances carry no color). */
const balanceColors = ['#2F54C9', '#16A34A', '#D97706'];

/** Icon cycle for saldo cards (balances carry no icon). */
const balanceIcons = ['palmtree', 'thermometer', 'circle-alert'];

const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
};

const selectStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.muted,
    background: '#fff',
    outline: 'none',
    cursor: 'pointer',
};

const dateInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 11px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 12.5,
    color: C.muted,
    outline: 'none',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

/** Inline error message rendered under a field, prototype error style. */
function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={errorTextStyle}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Apply the red error border to a base input/select style when invalid. */
function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

const textInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
};

const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    outline: 'none',
    resize: 'vertical',
};

const tabBarStyle: CSSProperties = {
    display: 'inline-flex',
    gap: 4,
    background: C.surface,
    padding: 4,
    borderRadius: 10,
    marginBottom: 18,
};

const headThStyle: CSSProperties = {
    padding: '11px 18px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

const tabs: { key: TabKey; label: string; icon: string }[] = [
    { key: 'cuti', label: 'Cuti', icon: 'palmtree' },
    { key: 'lembur', label: 'Lembur', icon: 'clock' },
    { key: 'izin', label: 'Izin', icon: 'door-open' },
    { key: 'wfh', label: 'WFH', icon: 'house' },
];

/** A single labelled field wrapper matching the prototype form style. */
function Field({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label style={fieldLabelStyle}>
                {label} {required && <span style={{ color: C.red }}>*</span>}
            </label>
            {children}
            <FieldError message={error} />
        </div>
    );
}

/** Reusable employee dropdown shared by the Lembur / Izin / WFH forms. */
function EmployeeSelect({
    value,
    onChange,
    error,
    employees,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
    employees: EmployeeOption[];
}) {
    return (
        <Field label="Karyawan" required error={error}>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                style={withError(selectStyle, !!error)}
            >
                <option value="">Pilih karyawan</option>
                {employees.map((employee) => (
                    <option key={employee.id} value={String(employee.id)}>
                        {employee.name} ({employee.employee_number})
                    </option>
                ))}
            </select>
        </Field>
    );
}

/** Card wrapper for the compact "Ajukan" forms, mirroring the Cuti form card. */
function RequestFormCard({
    title,
    subtitle,
    onSubmit,
    processing,
    children,
}: {
    title: string;
    subtitle: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    processing: boolean;
    children: ReactNode;
}) {
    return (
        <div style={card}>
            <div
                style={{
                    padding: '18px 22px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3 }}>
                    {subtitle}
                </div>
            </div>
            <form
                onSubmit={onSubmit}
                style={{
                    padding: '20px 22px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                {children}
                <button
                    type="submit"
                    disabled={processing}
                    style={{
                        width: '100%',
                        height: 44,
                        background: C.primary,
                        color: '#fff',
                        border: 'none',
                        borderRadius: 8,
                        fontSize: 14,
                        fontWeight: 600,
                        cursor: processing ? 'not-allowed' : 'pointer',
                        opacity: processing ? 0.7 : 1,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 8,
                        transition: '.15s',
                    }}
                >
                    <AIcon name="send" size={16} color="#fff" />
                    Kirim Pengajuan
                </button>
            </form>
        </div>
    );
}

/** A precomputed row for the generic approval table. */
interface ApprovalItem {
    id: number;
    employee: RequestEmployee | null;
    subtitle?: string;
    status: RequestStatus;
    status_label: RequestStatusLabel;
    cells: ReactNode[];
}

/** Generic list + approval table shared by the Lembur / Izin / WFH tabs. */
function ApprovalTable({
    title,
    headers,
    items,
    onApprove,
    onReject,
    emptyIcon,
    emptyText,
}: {
    title: string;
    headers: string[];
    items: ApprovalItem[];
    onApprove: (id: number) => void;
    onReject: (id: number) => void;
    emptyIcon: string;
    emptyText: string;
}) {
    const colSpan = headers.length + 3;

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                }}
            >
                {title}
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 640,
                    }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={headThStyle}>Karyawan</th>
                            {headers.map((header) => (
                                <th key={header} style={headThStyle}>
                                    {header}
                                </th>
                            ))}
                            <th style={headThStyle}>Status</th>
                            <th style={{ ...headThStyle, textAlign: 'right' }}>
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                <td
                                    colSpan={colSpan}
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
                                            flexDirection: 'column',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <AIcon
                                            name={emptyIcon}
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>{emptyText}</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {items.map((item) => {
                            const badge = statusBadge(item.status_label);

                            return (
                                <tr
                                    key={item.id}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td style={{ padding: '12px 18px' }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    width: 32,
                                                    height: 32,
                                                    borderRadius: '50%',
                                                    flex: 'none',
                                                    background:
                                                        item.employee
                                                            ?.avatar_color ??
                                                        C.faint,
                                                    color: '#fff',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {item.employee?.initials ?? '?'}
                                            </div>
                                            <div>
                                                <div
                                                    style={{
                                                        fontSize: 13,
                                                        fontWeight: 500,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {item.employee?.name ?? '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {item.subtitle ??
                                                        item.employee
                                                            ?.employee_number ??
                                                        ''}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    {item.cells.map((cell, index) => (
                                        <td
                                            key={index}
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.text,
                                            }}
                                        >
                                            {cell}
                                        </td>
                                    ))}
                                    <td style={{ padding: '12px 16px' }}>
                                        <span
                                            style={{
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: badge.color,
                                                background: badge.bg,
                                            }}
                                        >
                                            {badge.label}
                                        </span>
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        {item.status === 'pending' ? (
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                }}
                                            >
                                                <button
                                                    onClick={() =>
                                                        onApprove(item.id)
                                                    }
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 5,
                                                        height: 30,
                                                        padding: '0 11px',
                                                        border: 'none',
                                                        borderRadius: 7,
                                                        background:
                                                            'rgba(22,163,74,.1)',
                                                        color: C.green,
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        cursor: 'pointer',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="check"
                                                        size={14}
                                                        color={C.green}
                                                    />
                                                    Setujui
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        onReject(item.id)
                                                    }
                                                    style={{
                                                        width: 30,
                                                        height: 30,
                                                        border: 'none',
                                                        borderRadius: 7,
                                                        background:
                                                            'rgba(220,38,38,.08)',
                                                        color: C.red,
                                                        cursor: 'pointer',
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="x"
                                                        size={14}
                                                        color={C.red}
                                                    />
                                                </button>
                                            </div>
                                        ) : (
                                            <span
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.faint,
                                                }}
                                            >
                                                —
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function AvanaCuti({
    requests,
    filters,
    leaveTypes,
    employees,
    balances,
    overtimeRequests,
    permissionRequests,
    wfhRequests,
}: CutiProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = requests.meta;
    const pendingActive = filters.status === 'pending';
    const [tab, setTab] = useState<TabKey>('cuti');

    const form = useForm<LeaveFormData>({
        employee_id: '',
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
    });

    const overtimeForm = useForm({
        employee_id: '',
        date: '',
        hours: '',
        reason: '',
    });

    const izinForm = useForm({
        employee_id: '',
        date: '',
        type: 'izin_jam',
        start_time: '',
        end_time: '',
        reason: '',
    });

    const wfhForm = useForm({
        employee_id: '',
        start_date: '',
        end_date: '',
        reason: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const submitLeave = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LeaveController.store());
    };

    const submitOvertime = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        overtimeForm.submit(OvertimeController.store(), {
            preserveScroll: true,
            onSuccess: () => overtimeForm.reset(),
        });
    };

    const submitIzin = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        izinForm.submit(PermissionRequestController.store(), {
            preserveScroll: true,
            onSuccess: () => izinForm.reset(),
        });
    };

    const submitWfh = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        wfhForm.submit(WfhController.store(), {
            preserveScroll: true,
            onSuccess: () => wfhForm.reset(),
        });
    };

    const approveOvertime = (id: number) =>
        router.post(
            OvertimeController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    const rejectOvertime = (id: number) =>
        router.post(
            OvertimeController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    const approveIzin = (id: number) =>
        router.post(
            PermissionRequestController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    const rejectIzin = (id: number) =>
        router.post(
            PermissionRequestController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    const approveWfh = (id: number) =>
        router.post(
            WfhController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    const rejectWfh = (id: number) =>
        router.post(
            WfhController.reject(id).url,
            {},
            { preserveScroll: true },
        );

    const setStatusFilter = (status: 'pending' | undefined) => {
        router.get(
            window.location.pathname,
            { ...filters, status, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const approveRequest = (id: number) => {
        router.post(
            LeaveController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    };

    const rejectRequest = (id: number) => {
        router.post(
            LeaveController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Cuti & Lembur" />
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
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Cuti &amp; Lembur
                            </span>
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
                            Cuti &amp; Lembur
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Ajukan cuti dan kelola persetujuan tim Anda
                        </div>
                    </div>
                    <Link
                        href={LeaveTypeController.index().url}
                        style={{ ...btnOut, textDecoration: 'none' }}
                    >
                        <AIcon name="settings-2" size={16} color={C.muted} />
                        Kelola Jenis Cuti
                    </Link>
                </div>

                {/* Tab bar */}
                <div style={tabBarStyle}>
                    {tabs.map((item) => {
                        const active = tab === item.key;

                        return (
                            <button
                                key={item.key}
                                onClick={() => setTab(item.key)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    height: 36,
                                    padding: '0 16px',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: active ? '#fff' : C.muted,
                                    background: active ? C.primary : 'transparent',
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon
                                    name={item.icon}
                                    size={15}
                                    color={active ? '#fff' : C.muted}
                                />
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {tab === 'cuti' && (
                    <>
                {/* Saldo */}
                <div
                    className="avn-3col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3,1fr)',
                        gap: 16,
                        marginBottom: 18,
                    }}
                >
                    {balances.map((b, i) => {
                        const color = balanceColors[i % balanceColors.length];
                        const icon = balanceIcons[i % balanceIcons.length];

                        return (
                            <div
                                key={b.id}
                                style={{ ...card, padding: '18px 20px' }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
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
                                                width: 38,
                                                height: 38,
                                                borderRadius: 10,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background: color + '1a',
                                                color,
                                            }}
                                        >
                                            <AIcon
                                                name={icon}
                                                size={19}
                                                color={color}
                                            />
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {b.jenis}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    style={{
                                        marginTop: 14,
                                        display: 'flex',
                                        alignItems: 'baseline',
                                        gap: 6,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 28,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {b.sisa}
                                    </span>
                                    <span
                                        style={{ fontSize: 13, color: C.faint }}
                                    >
                                        / {b.total} hari tersisa
                                    </span>
                                </div>
                                <div
                                    style={{
                                        height: 7,
                                        background: C.line,
                                        borderRadius: 4,
                                        marginTop: 10,
                                        overflow: 'hidden',
                                    }}
                                >
                                    <div
                                        style={{
                                            height: '100%',
                                            width: b.pct,
                                            background: color,
                                            borderRadius: 4,
                                        }}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div
                    className="avn-abs"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '380px 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Form pengajuan */}
                    <div style={card}>
                        <div
                            style={{
                                padding: '18px 22px',
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Ajukan Cuti
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 3,
                                }}
                            >
                                Lengkapi formulir di bawah ini.
                            </div>
                        </div>
                        <form
                            onSubmit={submitLeave}
                            style={{
                                padding: '20px 22px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Karyawan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.employee_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'employee_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.employee_id,
                                    )}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name} (
                                            {employee.employee_number})
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.employee_id} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Jenis Cuti{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.leave_type_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'leave_type_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.leave_type_id,
                                    )}
                                >
                                    <option value="">Pilih jenis cuti</option>
                                    {leaveTypes.map((leaveType) => (
                                        <option
                                            key={leaveType.id}
                                            value={String(leaveType.id)}
                                        >
                                            {leaveType.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.leave_type_id}
                                />
                            </div>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Mulai{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'start_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            dateInputStyle,
                                            !!form.errors.start_date,
                                        )}
                                    />
                                    <FieldError
                                        message={form.errors.start_date}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Selesai{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'end_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            dateInputStyle,
                                            !!form.errors.end_date,
                                        )}
                                    />
                                    <FieldError
                                        message={form.errors.end_date}
                                    />
                                </div>
                            </div>
                            <div
                                style={{
                                    background: C.surface,
                                    borderRadius: 8,
                                    padding: '11px 13px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    fontSize: 12.5,
                                    color: C.muted,
                                }}
                            >
                                <AIcon
                                    name="info"
                                    size={15}
                                    color={C.primary}
                                />
                                Total&nbsp;
                                <span
                                    style={{ color: C.text, fontWeight: 600 }}
                                >
                                    3 hari kerja
                                </span>
                                &nbsp;· sisa saldo 8 hari
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Alasan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <textarea
                                    placeholder="Tuliskan alasan pengajuan cuti"
                                    rows={3}
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        {
                                            width: '100%',
                                            padding: '11px 13px',
                                            border: `1px solid ${C.border}`,
                                            borderRadius: 8,
                                            fontSize: 13.5,
                                            outline: 'none',
                                            resize: 'vertical',
                                        },
                                        !!form.errors.reason,
                                    )}
                                />
                                <FieldError message={form.errors.reason} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>Lampiran</label>
                                <div
                                    style={{
                                        border: '1.5px dashed #D5DCEA',
                                        borderRadius: 9,
                                        padding: 18,
                                        textAlign: 'center',
                                        cursor: 'pointer',
                                        transition: '.15s',
                                    }}
                                >
                                    <AIcon
                                        name="upload-cloud"
                                        size={22}
                                        color={C.faint}
                                    />
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginTop: 6,
                                        }}
                                    >
                                        Klik untuk unggah{' '}
                                        <span
                                            style={{
                                                color: C.primary,
                                                fontWeight: 500,
                                            }}
                                        >
                                            surat keterangan
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: C.faint,
                                            marginTop: 2,
                                        }}
                                    >
                                        PDF / JPG · maks 5 MB
                                    </div>
                                </div>
                            </div>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    width: '100%',
                                    height: 44,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: form.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                    opacity: form.processing ? 0.7 : 1,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="send" size={16} color="#fff" />
                                Kirim Pengajuan
                            </button>
                        </form>
                    </div>

                    {/* List + approval */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div
                            style={{
                                padding: '16px 18px',
                                borderBottom: `1px solid ${C.border}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Pengajuan Tim
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 4,
                                    background: C.surface,
                                    padding: 3,
                                    borderRadius: 8,
                                }}
                            >
                                <span
                                    onClick={() => setStatusFilter('pending')}
                                    style={{
                                        fontSize: 12.5,
                                        fontWeight: 600,
                                        color: pendingActive ? '#fff' : C.muted,
                                        background: pendingActive
                                            ? C.primary
                                            : 'transparent',
                                        padding: '5px 12px',
                                        borderRadius: 6,
                                        cursor: 'pointer',
                                    }}
                                >
                                    Menunggu
                                </span>
                                <span
                                    onClick={() => setStatusFilter(undefined)}
                                    style={{
                                        fontSize: 12.5,
                                        fontWeight: pendingActive ? 500 : 600,
                                        color: pendingActive ? C.muted : '#fff',
                                        background: pendingActive
                                            ? 'transparent'
                                            : C.primary,
                                        padding: '5px 12px',
                                        borderRadius: 6,
                                        cursor: 'pointer',
                                    }}
                                >
                                    Semua
                                </span>
                            </div>
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 640,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th
                                            style={{
                                                padding: '11px 18px',
                                                textAlign: 'left',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Karyawan
                                        </th>
                                        <th
                                            style={{
                                                padding: '11px 16px',
                                                textAlign: 'left',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Jenis
                                        </th>
                                        <th
                                            style={{
                                                padding: '11px 16px',
                                                textAlign: 'left',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Tanggal
                                        </th>
                                        <th
                                            style={{
                                                padding: '11px 16px',
                                                textAlign: 'left',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Status
                                        </th>
                                        <th
                                            style={{
                                                padding: '11px 18px',
                                                textAlign: 'right',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.data.length === 0 && (
                                        <tr
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                colSpan={5}
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
                                                        flexDirection: 'column',
                                                        alignItems: 'center',
                                                        gap: 10,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="palmtree"
                                                        size={28}
                                                        color={C.faint}
                                                    />
                                                    <div>
                                                        Tidak ada pengajuan
                                                        cuti.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {requests.data.map((row) => {
                                        const badge = statusBadge(
                                            row.status_label,
                                        );

                                        return (
                                            <tr
                                                key={row.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        padding: '12px 18px',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            alignItems:
                                                                'center',
                                                            gap: 10,
                                                        }}
                                                    >
                                                        <div
                                                            style={{
                                                                width: 32,
                                                                height: 32,
                                                                borderRadius:
                                                                    '50%',
                                                                flex: 'none',
                                                                background:
                                                                    row.employee
                                                                        ?.avatar_color ??
                                                                    C.faint,
                                                                color: '#fff',
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                justifyContent:
                                                                    'center',
                                                                fontSize: 11.5,
                                                                fontWeight: 600,
                                                            }}
                                                        >
                                                            {row.employee
                                                                ?.initials ??
                                                                '?'}
                                                        </div>
                                                        <div>
                                                            <div
                                                                style={{
                                                                    fontSize: 13,
                                                                    fontWeight: 500,
                                                                    color: C.text,
                                                                }}
                                                            >
                                                                {row.employee
                                                                    ?.name ??
                                                                    '—'}
                                                            </div>
                                                            <div
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    color: C.faint,
                                                                }}
                                                            >
                                                                {row.durasi}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        fontSize: 13,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {row.leave_type?.name ??
                                                        '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        fontSize: 12.5,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {row.start_date} –{' '}
                                                    {row.end_date}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            padding: '3px 10px',
                                                            borderRadius: 100,
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                            color: badge.color,
                                                            background:
                                                                badge.bg,
                                                        }}
                                                    >
                                                        {badge.label}
                                                    </span>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '12px 18px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    {row.status ===
                                                    'pending' ? (
                                                        <div
                                                            style={{
                                                                display:
                                                                    'inline-flex',
                                                                gap: 6,
                                                            }}
                                                        >
                                                            <button
                                                                onClick={() =>
                                                                    approveRequest(
                                                                        row.id,
                                                                    )
                                                                }
                                                                style={{
                                                                    display:
                                                                        'inline-flex',
                                                                    alignItems:
                                                                        'center',
                                                                    gap: 5,
                                                                    height: 30,
                                                                    padding:
                                                                        '0 11px',
                                                                    border: 'none',
                                                                    borderRadius: 7,
                                                                    background:
                                                                        'rgba(22,163,74,.1)',
                                                                    color: C.green,
                                                                    fontSize: 12,
                                                                    fontWeight: 600,
                                                                    cursor: 'pointer',
                                                                    transition:
                                                                        '.15s',
                                                                }}
                                                            >
                                                                <AIcon
                                                                    name="check"
                                                                    size={14}
                                                                    color={
                                                                        C.green
                                                                    }
                                                                />
                                                                Setujui
                                                            </button>
                                                            <button
                                                                onClick={() =>
                                                                    rejectRequest(
                                                                        row.id,
                                                                    )
                                                                }
                                                                style={{
                                                                    width: 30,
                                                                    height: 30,
                                                                    border: 'none',
                                                                    borderRadius: 7,
                                                                    background:
                                                                        'rgba(220,38,38,.08)',
                                                                    color: C.red,
                                                                    cursor: 'pointer',
                                                                    display:
                                                                        'inline-flex',
                                                                    alignItems:
                                                                        'center',
                                                                    justifyContent:
                                                                        'center',
                                                                    transition:
                                                                        '.15s',
                                                                }}
                                                            >
                                                                <AIcon
                                                                    name="x"
                                                                    size={14}
                                                                    color={
                                                                        C.red
                                                                    }
                                                                />
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span
                                                            style={{
                                                                fontSize: 12.5,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination footer */}
                        <div
                            style={{
                                padding: '14px 18px',
                                borderTop: `1px solid ${C.border}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                flexWrap: 'wrap',
                                gap: 12,
                            }}
                        >
                            <div style={{ fontSize: 13, color: C.muted }}>
                                Menampilkan{' '}
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {meta.from ?? 0}–{meta.to ?? 0}
                                </span>{' '}
                                dari{' '}
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {meta.total.toLocaleString('id-ID')}
                                </span>
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    alignItems: 'center',
                                }}
                            >
                                <button
                                    disabled={meta.current_page <= 1}
                                    onClick={() =>
                                        goToPage(meta.current_page - 1)
                                    }
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color:
                                            meta.current_page <= 1
                                                ? C.faint
                                                : C.text,
                                        cursor:
                                            meta.current_page <= 1
                                                ? 'not-allowed'
                                                : 'pointer',
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 5,
                                    }}
                                >
                                    <AIcon name="chevron-left" size={15} />
                                </button>
                                <span
                                    style={{
                                        fontSize: 13,
                                        color: C.muted,
                                        padding: '0 4px',
                                    }}
                                >
                                    {meta.current_page} / {meta.last_page}
                                </span>
                                <button
                                    disabled={
                                        meta.current_page >= meta.last_page
                                    }
                                    onClick={() =>
                                        goToPage(meta.current_page + 1)
                                    }
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color:
                                            meta.current_page >= meta.last_page
                                                ? C.faint
                                                : C.text,
                                        cursor:
                                            meta.current_page >= meta.last_page
                                                ? 'not-allowed'
                                                : 'pointer',
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                    }}
                                >
                                    <AIcon name="chevron-right" size={15} />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                    </>
                )}

                {tab === 'lembur' && (
                    <div
                        className="avn-abs"
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '380px 1fr',
                            gap: 18,
                            alignItems: 'start',
                        }}
                    >
                        <RequestFormCard
                            title="Ajukan Lembur"
                            subtitle="Catat jam lembur karyawan."
                            onSubmit={submitOvertime}
                            processing={overtimeForm.processing}
                        >
                            <EmployeeSelect
                                value={overtimeForm.data.employee_id}
                                onChange={(value) =>
                                    overtimeForm.setData('employee_id', value)
                                }
                                error={overtimeForm.errors.employee_id}
                                employees={employees}
                            />
                            <Field
                                label="Tanggal"
                                required
                                error={overtimeForm.errors.date}
                            >
                                <input
                                    type="date"
                                    value={overtimeForm.data.date}
                                    onChange={(event) =>
                                        overtimeForm.setData(
                                            'date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        dateInputStyle,
                                        !!overtimeForm.errors.date,
                                    )}
                                />
                            </Field>
                            <Field
                                label="Durasi (jam)"
                                required
                                error={overtimeForm.errors.hours}
                            >
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    placeholder="2"
                                    value={overtimeForm.data.hours}
                                    onChange={(event) =>
                                        overtimeForm.setData(
                                            'hours',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textInputStyle,
                                        !!overtimeForm.errors.hours,
                                    )}
                                />
                            </Field>
                            <Field
                                label="Alasan"
                                error={overtimeForm.errors.reason}
                            >
                                <textarea
                                    rows={3}
                                    placeholder="Tuliskan alasan lembur"
                                    value={overtimeForm.data.reason}
                                    onChange={(event) =>
                                        overtimeForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textareaStyle,
                                        !!overtimeForm.errors.reason,
                                    )}
                                />
                            </Field>
                        </RequestFormCard>

                        <ApprovalTable
                            title="Pengajuan Lembur"
                            headers={['Tanggal', 'Durasi', 'Alasan']}
                            emptyIcon="clock"
                            emptyText="Tidak ada pengajuan lembur."
                            onApprove={approveOvertime}
                            onReject={rejectOvertime}
                            items={overtimeRequests.map((row) => ({
                                id: row.id,
                                employee: row.employee,
                                status: row.status,
                                status_label: row.status_label,
                                cells: [
                                    row.date ?? '—',
                                    `${row.hours} jam`,
                                    row.reason ?? '—',
                                ],
                            }))}
                        />
                    </div>
                )}

                {tab === 'izin' && (
                    <div
                        className="avn-abs"
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '380px 1fr',
                            gap: 18,
                            alignItems: 'start',
                        }}
                    >
                        <RequestFormCard
                            title="Ajukan Izin"
                            subtitle="Izin jam atau keluar kantor."
                            onSubmit={submitIzin}
                            processing={izinForm.processing}
                        >
                            <EmployeeSelect
                                value={izinForm.data.employee_id}
                                onChange={(value) =>
                                    izinForm.setData('employee_id', value)
                                }
                                error={izinForm.errors.employee_id}
                                employees={employees}
                            />
                            <Field
                                label="Tipe Izin"
                                required
                                error={izinForm.errors.type}
                            >
                                <select
                                    value={izinForm.data.type}
                                    onChange={(event) =>
                                        izinForm.setData(
                                            'type',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!izinForm.errors.type,
                                    )}
                                >
                                    <option value="izin_jam">Izin Jam</option>
                                    <option value="keluar_kantor">
                                        Keluar Kantor
                                    </option>
                                </select>
                            </Field>
                            <Field
                                label="Tanggal"
                                required
                                error={izinForm.errors.date}
                            >
                                <input
                                    type="date"
                                    value={izinForm.data.date}
                                    onChange={(event) =>
                                        izinForm.setData(
                                            'date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        dateInputStyle,
                                        !!izinForm.errors.date,
                                    )}
                                />
                            </Field>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <Field
                                    label="Jam Mulai"
                                    error={izinForm.errors.start_time}
                                >
                                    <input
                                        type="time"
                                        value={izinForm.data.start_time}
                                        onChange={(event) =>
                                            izinForm.setData(
                                                'start_time',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            dateInputStyle,
                                            !!izinForm.errors.start_time,
                                        )}
                                    />
                                </Field>
                                <Field
                                    label="Jam Selesai"
                                    error={izinForm.errors.end_time}
                                >
                                    <input
                                        type="time"
                                        value={izinForm.data.end_time}
                                        onChange={(event) =>
                                            izinForm.setData(
                                                'end_time',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            dateInputStyle,
                                            !!izinForm.errors.end_time,
                                        )}
                                    />
                                </Field>
                            </div>
                            <Field
                                label="Alasan"
                                error={izinForm.errors.reason}
                            >
                                <textarea
                                    rows={3}
                                    placeholder="Tuliskan alasan izin"
                                    value={izinForm.data.reason}
                                    onChange={(event) =>
                                        izinForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textareaStyle,
                                        !!izinForm.errors.reason,
                                    )}
                                />
                            </Field>
                        </RequestFormCard>

                        <ApprovalTable
                            title="Pengajuan Izin"
                            headers={['Tanggal', 'Tipe', 'Jam', 'Alasan']}
                            emptyIcon="door-open"
                            emptyText="Tidak ada pengajuan izin."
                            onApprove={approveIzin}
                            onReject={rejectIzin}
                            items={permissionRequests.map((row) => ({
                                id: row.id,
                                employee: row.employee,
                                status: row.status,
                                status_label: row.status_label,
                                cells: [
                                    row.date ?? '—',
                                    row.type === 'keluar_kantor'
                                        ? 'Keluar Kantor'
                                        : 'Izin Jam',
                                    row.start_time && row.end_time
                                        ? `${row.start_time}–${row.end_time}`
                                        : '—',
                                    row.reason ?? '—',
                                ],
                            }))}
                        />
                    </div>
                )}

                {tab === 'wfh' && (
                    <div
                        className="avn-abs"
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '380px 1fr',
                            gap: 18,
                            alignItems: 'start',
                        }}
                    >
                        <RequestFormCard
                            title="Ajukan WFH"
                            subtitle="Pengajuan bekerja dari rumah."
                            onSubmit={submitWfh}
                            processing={wfhForm.processing}
                        >
                            <EmployeeSelect
                                value={wfhForm.data.employee_id}
                                onChange={(value) =>
                                    wfhForm.setData('employee_id', value)
                                }
                                error={wfhForm.errors.employee_id}
                                employees={employees}
                            />
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <Field
                                    label="Mulai"
                                    required
                                    error={wfhForm.errors.start_date}
                                >
                                    <input
                                        type="date"
                                        value={wfhForm.data.start_date}
                                        onChange={(event) =>
                                            wfhForm.setData(
                                                'start_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            dateInputStyle,
                                            !!wfhForm.errors.start_date,
                                        )}
                                    />
                                </Field>
                                <Field
                                    label="Selesai"
                                    required
                                    error={wfhForm.errors.end_date}
                                >
                                    <input
                                        type="date"
                                        value={wfhForm.data.end_date}
                                        onChange={(event) =>
                                            wfhForm.setData(
                                                'end_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            dateInputStyle,
                                            !!wfhForm.errors.end_date,
                                        )}
                                    />
                                </Field>
                            </div>
                            <Field
                                label="Alasan"
                                error={wfhForm.errors.reason}
                            >
                                <textarea
                                    rows={3}
                                    placeholder="Tuliskan alasan WFH"
                                    value={wfhForm.data.reason}
                                    onChange={(event) =>
                                        wfhForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textareaStyle,
                                        !!wfhForm.errors.reason,
                                    )}
                                />
                            </Field>
                        </RequestFormCard>

                        <ApprovalTable
                            title="Pengajuan WFH"
                            headers={['Mulai', 'Selesai', 'Alasan']}
                            emptyIcon="house"
                            emptyText="Tidak ada pengajuan WFH."
                            onApprove={approveWfh}
                            onReject={rejectWfh}
                            items={wfhRequests.map((row) => ({
                                id: row.id,
                                employee: row.employee,
                                status: row.status,
                                status_label: row.status_label,
                                cells: [
                                    row.start_date ?? '—',
                                    row.end_date ?? '—',
                                    row.reason ?? '—',
                                ],
                            }))}
                        />
                    </div>
                )}
            </div>
        </>
    );
}
