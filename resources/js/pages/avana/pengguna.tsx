import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import UserController from '@/actions/App/Http/Controllers/Avana/UserController';
import { AIcon, btnP, C, thCell } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** A role assignable to a user, as serialized by the controller. */
interface RoleOption {
    id: number;
    name: string;
    code: string;
}

/** A tenant branch the user can be granted access to. */
interface BranchOption {
    id: number;
    name: string;
    code: string;
}

/** A single user row as serialized by `UserController@index`. */
interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    roles: RoleOption[];
    initials: string;
    avatar_color: string;
    data_scope: string;
    branch_ids: number[];
}

interface PenggunaFilters {
    search?: string;
    status?: string;
    sort?: string;
    direction?: string;
    per_page?: string;
}

interface PenggunaProps {
    users: {
        data: UserRow[];
        meta: PaginationMeta;
    };
    roles: RoleOption[];
    branches: BranchOption[];
    filters: PenggunaFilters;
}

/** Flat form payload backing both the create and edit user forms. */
interface UserFormData {
    name: string;
    email: string;
    phone: string;
    password: string;
    status: string;
    role_ids: number[];
    data_scope: string;
    branch_ids: number[];
}

const emptyForm: UserFormData = {
    name: '',
    email: '',
    phone: '',
    password: '',
    status: 'active',
    role_ids: [],
    data_scope: 'company',
    branch_ids: [],
};

/** Scope options surfaced in the create/edit modal. */
const scopeOptions: { value: string; label: string }[] = [
    { value: 'company', label: 'Semua Cabang (Company)' },
    { value: 'branch', label: 'Cabang Tertentu (Branch)' },
    { value: 'team', label: 'Tim (Team)' },
    { value: 'own', label: 'Data Sendiri (Own)' },
];

/** Short label for a scope value, shown as a table chip. */
function scopeShortLabel(scope: string): string {
    switch (scope) {
        case 'branch':
            return 'Cabang';
        case 'team':
            return 'Tim';
        case 'own':
            return 'Pribadi';
        default:
            return 'Semua cabang';
    }
}

const filterSelectStyle: CSSProperties = {
    height: 38,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.muted,
    background: '#fff',
    cursor: 'pointer',
    outline: 'none',
};

const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
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

const selectStyle: CSSProperties = {
    ...inputStyle,
    color: C.muted,
    cursor: 'pointer',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

/** Inline error message rendered under a field. */
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

/** Status pill colors for active/inactive accounts. */
function statusPill(status: string) {
    return status === 'active'
        ? { label: 'Aktif', color: C.green, bg: 'rgba(22,163,74,.1)' }
        : { label: 'Nonaktif', color: C.muted, bg: 'rgba(107,114,128,.12)' };
}

/** Build a windowed list of page numbers with ellipsis markers. */
function pageItems(current: number, last: number): (number | 'gap')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const items: (number | 'gap')[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) {
        items.push('gap');
    }

    for (let page = start; page <= end; page++) {
        items.push(page);
    }

    if (end < last - 1) {
        items.push('gap');
    }

    items.push(last);

    return items;
}

export default function AvanaPengguna({
    users,
    roles,
    branches = [],
    filters,
}: PenggunaProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = users.meta;
    const branchNameById = new Map(
        branches.map((branch) => [branch.id, branch.name]),
    );

    /** Render the scope summary for a user row. */
    const scopeSummary = (user: UserRow): string => {
        if (user.data_scope === 'branch') {
            const names = user.branch_ids
                .map((id) => branchNameById.get(id))
                .filter((name): name is string => Boolean(name));

            return names.length > 0
                ? `Cabang: ${names.join(', ')}`
                : 'Cabang: —';
        }

        return scopeShortLabel(user.data_scope);
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [confirm, setConfirm] = useState<UserRow | null>(null);
    const isFirstSearch = useRef(true);

    const form = useForm<UserFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const applyFilter = (key: string, value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined, page: 1 },
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

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const openEdit = (user: UserRow) => {
        setEditing(user);
        form.clearErrors();
        form.setData({
            name: user.name,
            email: user.email,
            phone: user.phone ?? '',
            password: '',
            status: user.status,
            role_ids: user.roles.map((role) => role.id),
            data_scope: user.data_scope ?? 'company',
            branch_ids: [...user.branch_ids],
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const toggleRole = (id: number) => {
        const next = form.data.role_ids.includes(id)
            ? form.data.role_ids.filter((roleId) => roleId !== id)
            : [...form.data.role_ids, id];

        form.setData('role_ids', next);
    };

    const toggleBranch = (id: number) => {
        const next = form.data.branch_ids.includes(id)
            ? form.data.branch_ids.filter((branchId) => branchId !== id)
            : [...form.data.branch_ids, id];

        form.setData('branch_ids', next);
    };

    const submitForm = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = { onSuccess: () => closeModal() };

        if (editing) {
            form.submit(UserController.update(editing.id), options);
        } else {
            form.submit(UserController.store(), options);
        }
    };

    const toggleStatus = (user: UserRow) => {
        router.post(
            UserController.toggleStatus(user.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const deleteUser = () => {
        if (!confirm) {
            return;
        }

        router.delete(UserController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Pengguna" />
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
                            <span>Pengaturan</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Pengguna</span>
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
                            Pengguna
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola akun pengguna &amp; peran akses tim Anda
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} />
                        Tambah Pengguna
                    </button>
                </div>

                {/* Table card */}
                <div
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                        overflow: 'hidden',
                    }}
                >
                    {/* Filter bar */}
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            gap: 10,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                        }}
                    >
                        <div
                            style={{
                                position: 'relative',
                                flex: 1,
                                minWidth: 220,
                                maxWidth: 320,
                            }}
                        >
                            <AIcon
                                name="search"
                                size={16}
                                color={C.faint}
                                style={{
                                    position: 'absolute',
                                    left: 12,
                                    top: '50%',
                                    transform: 'translateY(-50%)',
                                }}
                            />
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama atau email…"
                                style={{
                                    width: '100%',
                                    height: 38,
                                    padding: '0 12px 0 36px',
                                    background: C.surface,
                                    border: '1px solid transparent',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    outline: 'none',
                                    transition: '.15s',
                                }}
                            />
                        </div>
                        <select
                            aria-label="Status"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                applyFilter('status', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <div style={{ flex: 1 }} />
                    </div>

                    {/* Table */}
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
                                    <th style={thCell}>Pengguna</th>
                                    <th style={thCell}>Telepon</th>
                                    <th style={thCell}>Peran</th>
                                    <th style={thCell}>Status</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            padding: '12px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.length === 0 && (
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
                                                    name="users"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Tidak ada pengguna yang
                                                    ditemukan.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {users.data.map((user) => {
                                    const pill = statusPill(user.status);

                                    return (
                                        <tr
                                            key={user.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                                transition: 'background .15s',
                                            }}
                                        >
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 11,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            width: 36,
                                                            height: 36,
                                                            borderRadius: '50%',
                                                            flex: 'none',
                                                            background:
                                                                user.avatar_color,
                                                            color: '#fff',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent:
                                                                'center',
                                                            fontSize: 12.5,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {user.initials}
                                                    </div>
                                                    <div style={{ minWidth: 0 }}>
                                                        <div
                                                            style={{
                                                                fontSize: 13.5,
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {user.name}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 12,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {user.email}
                                                        </div>
                                                        <div
                                                            style={{
                                                                display:
                                                                    'inline-flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 4,
                                                                marginTop: 4,
                                                                padding:
                                                                    '2px 8px',
                                                                borderRadius: 100,
                                                                fontSize: 11,
                                                                fontWeight: 500,
                                                                color: C.muted,
                                                                background:
                                                                    C.surface,
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="building-2"
                                                                size={11}
                                                                color={C.faint}
                                                            />
                                                            {scopeSummary(user)}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                }}
                                            >
                                                {user.phone ?? '—'}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        flexWrap: 'wrap',
                                                        gap: 6,
                                                    }}
                                                >
                                                    {user.roles.length === 0 && (
                                                        <span
                                                            style={{
                                                                fontSize: 13,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            —
                                                        </span>
                                                    )}
                                                    {user.roles.map((role) => (
                                                        <span
                                                            key={role.id}
                                                            style={{
                                                                display:
                                                                    'inline-block',
                                                                padding:
                                                                    '3px 10px',
                                                                borderRadius: 100,
                                                                fontSize: 11.5,
                                                                fontWeight: 600,
                                                                color: C.primary,
                                                                background:
                                                                    'rgba(47,84,201,.1)',
                                                            }}
                                                        >
                                                            {role.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <span
                                                    style={{
                                                        display: 'inline-block',
                                                        padding: '3px 10px',
                                                        borderRadius: 100,
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                        color: pill.color,
                                                        background: pill.bg,
                                                    }}
                                                >
                                                    {pill.label}
                                                </span>
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
                                                    <button
                                                        title="Ubah"
                                                        onClick={() =>
                                                            openEdit(user)
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="pencil"
                                                            size={15}
                                                            color={C.muted}
                                                        />
                                                    </button>
                                                    <button
                                                        title={
                                                            user.status ===
                                                            'active'
                                                                ? 'Nonaktifkan'
                                                                : 'Aktifkan'
                                                        }
                                                        onClick={() =>
                                                            toggleStatus(user)
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name={
                                                                user.status ===
                                                                'active'
                                                                    ? 'toggle-right'
                                                                    : 'toggle-left'
                                                            }
                                                            size={16}
                                                            color={
                                                                user.status ===
                                                                'active'
                                                                    ? C.green
                                                                    : C.faint
                                                            }
                                                        />
                                                    </button>
                                                    <button
                                                        title="Hapus"
                                                        onClick={() =>
                                                            setConfirm(user)
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="trash-2"
                                                            size={15}
                                                            color={C.red}
                                                        />
                                                    </button>
                                                </div>
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
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.from ?? 0}–{meta.to ?? 0}
                            </span>{' '}
                            dari{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.total.toLocaleString('id-ID')}
                            </span>{' '}
                            pengguna
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
                                onClick={() => goToPage(meta.current_page - 1)}
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
                            {pageItems(meta.current_page, meta.last_page).map(
                                (item, index) =>
                                    item === 'gap' ? (
                                        <span
                                            key={`gap-${index}`}
                                            style={{
                                                color: C.faint,
                                                padding: '0 4px',
                                            }}
                                        >
                                            …
                                        </span>
                                    ) : (
                                        <button
                                            key={item}
                                            onClick={() => goToPage(item)}
                                            style={{
                                                height: 34,
                                                minWidth: 34,
                                                border:
                                                    item === meta.current_page
                                                        ? 'none'
                                                        : `1px solid ${C.border}`,
                                                background:
                                                    item === meta.current_page
                                                        ? C.primary
                                                        : '#fff',
                                                borderRadius: 8,
                                                fontSize: 13,
                                                color:
                                                    item === meta.current_page
                                                        ? '#fff'
                                                        : C.text,
                                                fontWeight:
                                                    item === meta.current_page
                                                        ? 600
                                                        : 400,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            {item}
                                        </button>
                                    ),
                            )}
                            <button
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => goToPage(meta.current_page + 1)}
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

            {/* Create / edit modal */}
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
                        onSubmit={submitForm}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 460,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
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
                            {editing ? 'Ubah Pengguna' : 'Tambah Pengguna'}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Lengkapi data akun dan tetapkan peran aksesnya.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Nama{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder="Nama lengkap"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.name,
                                    )}
                                />
                                <FieldError message={form.errors.name} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Email{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="email@perusahaan.com"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.email,
                                    )}
                                />
                                <FieldError message={form.errors.email} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Telepon</label>
                                <input
                                    type="text"
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="08xxxxxxxxxx"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.phone,
                                    )}
                                />
                                <FieldError message={form.errors.phone} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Kata Sandi{' '}
                                    {!editing && (
                                        <span style={{ color: C.red }}>*</span>
                                    )}
                                </label>
                                <input
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={
                                        editing
                                            ? 'Kosongkan jika tidak diubah'
                                            : 'Minimal 8 karakter'
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.password,
                                    )}
                                />
                                <FieldError message={form.errors.password} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Status{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.status,
                                    )}
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                                <FieldError message={form.errors.status} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Peran</label>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 8,
                                    }}
                                >
                                    {roles.map((role) => {
                                        const checked =
                                            form.data.role_ids.includes(
                                                role.id,
                                            );

                                        return (
                                            <label
                                                key={role.id}
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                    padding: '10px 12px',
                                                    border: `1px solid ${
                                                        checked
                                                            ? C.primary
                                                            : C.border
                                                    }`,
                                                    borderRadius: 9,
                                                    background: checked
                                                        ? 'rgba(47,84,201,.05)'
                                                        : '#fff',
                                                    cursor: 'pointer',
                                                    transition: '.12s',
                                                }}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() =>
                                                        toggleRole(role.id)
                                                    }
                                                    style={{
                                                        width: 16,
                                                        height: 16,
                                                        accentColor: C.primary,
                                                    }}
                                                />
                                                <div>
                                                    <div
                                                        style={{
                                                            fontSize: 13.5,
                                                            fontWeight: 500,
                                                            color: C.text,
                                                        }}
                                                    >
                                                        {role.name}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {role.code}
                                                    </div>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                                <FieldError message={form.errors.role_ids} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Scope Data</label>
                                <select
                                    value={form.data.data_scope}
                                    onChange={(event) =>
                                        form.setData(
                                            'data_scope',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.data_scope,
                                    )}
                                >
                                    {scopeOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.data_scope} />
                            </div>

                            {form.data.data_scope === 'branch' && (
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Akses Cabang
                                    </label>
                                    {branches.length === 0 ? (
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada cabang.
                                        </div>
                                    ) : (
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 8,
                                            }}
                                        >
                                            {branches.map((branch) => {
                                                const checked =
                                                    form.data.branch_ids.includes(
                                                        branch.id,
                                                    );

                                                return (
                                                    <label
                                                        key={branch.id}
                                                        style={{
                                                            display: 'flex',
                                                            alignItems:
                                                                'center',
                                                            gap: 10,
                                                            padding: '10px 12px',
                                                            border: `1px solid ${
                                                                checked
                                                                    ? C.primary
                                                                    : C.border
                                                            }`,
                                                            borderRadius: 9,
                                                            background: checked
                                                                ? 'rgba(47,84,201,.05)'
                                                                : '#fff',
                                                            cursor: 'pointer',
                                                            transition: '.12s',
                                                        }}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            onChange={() =>
                                                                toggleBranch(
                                                                    branch.id,
                                                                )
                                                            }
                                                            style={{
                                                                width: 16,
                                                                height: 16,
                                                                accentColor:
                                                                    C.primary,
                                                            }}
                                                        />
                                                        <div>
                                                            <div
                                                                style={{
                                                                    fontSize: 13.5,
                                                                    fontWeight: 500,
                                                                    color: C.text,
                                                                }}
                                                            >
                                                                {branch.name}
                                                            </div>
                                                            <div
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    color: C.faint,
                                                                }}
                                                            >
                                                                {branch.code}
                                                            </div>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}
                                    <FieldError
                                        message={form.errors.branch_ids}
                                    />
                                </div>
                            )}
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeModal}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
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
                                <AIcon
                                    name={editing ? 'check' : 'plus'}
                                    size={16}
                                    color="#fff"
                                />
                                {editing ? 'Simpan' : 'Tambah'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete modal */}
            {confirm && (
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
                            width: '100%',
                            maxWidth: 400,
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
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
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Hapus pengguna?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Akun{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.name}
                            </strong>{' '}
                            akan dihapus permanen. Tindakan ini tidak dapat
                            dibatalkan.
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={() => setConfirm(null)}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                onClick={deleteUser}
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
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="trash-2" size={16} />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
};
