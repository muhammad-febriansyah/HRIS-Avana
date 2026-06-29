import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AccessController from '@/actions/App/Http/Controllers/Avana/AccessController';
import { AIcon, btnP, C, card } from '@/lib/avana';
import type { FlashProps } from './employees/types';

/** A role card as serialized by `AccessController@index`. */
interface AccessRole {
    id: number;
    name: string;
    code: string;
    desc: string;
    users: number;
    color: string;
}

/** A matrix row module: a UI menu group keyed by `key`. */
interface AccessModule {
    key: string;
    label: string;
}

interface HakAksesProps {
    roles: AccessRole[];
    modules: AccessModule[];
    permHeaders: string[];
    matrix: boolean[][];
}

export default function AvanaHakAkses({
    roles,
    modules,
    permHeaders,
    matrix,
}: HakAksesProps) {
    const { flash } = usePage<FlashProps>().props;
    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [roleName, setRoleName] = useState('');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const closeRoleModal = () => {
        setRoleModalOpen(false);
        setRoleName('');
    };

    const toggleCell = (rowIdx: number, colIdx: number) => {
        router.post(
            AccessController.togglePermission().url,
            { module_key: modules[rowIdx].key, role_id: roles[colIdx].id },
            { preserveScroll: true },
        );
    };

    const submitRole = () => {
        router.post(
            AccessController.storeRole().url,
            { name: roleName },
            { preserveScroll: true, onSuccess: closeRoleModal },
        );
    };

    return (
        <>
            <Head title="Hak Akses" />
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
                            <span>Pengaturan</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Hak Akses</span>
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
                            Hak Akses &amp; Peran
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Atur izin tiap peran terhadap modul &amp; aksi
                        </div>
                    </div>
                    <button onClick={() => setRoleModalOpen(true)} style={btnP}>
                        <AIcon name="plus" size={16} />
                        Buat Role Kustom
                    </button>
                </div>

                {/* Roles */}
                <div
                    className="avn-kpi"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    {roles.map((r) => (
                        <div
                            key={r.id}
                            style={{ ...card, padding: '16px 18px' }}
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
                                        width: 36,
                                        height: 36,
                                        borderRadius: 9,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: r.color + '1a',
                                        color: r.color,
                                    }}
                                >
                                    <AIcon
                                        name="shield"
                                        size={18}
                                        color={r.color}
                                    />
                                </div>
                                <div
                                    style={{
                                        fontSize: 14,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    {r.name}
                                </div>
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.muted,
                                    marginTop: 10,
                                    lineHeight: 1.45,
                                    minHeight: 34,
                                }}
                            >
                                {r.desc}
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    fontSize: 12,
                                    color: C.faint,
                                    marginTop: 8,
                                    paddingTop: 10,
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <AIcon name="users" size={14} />
                                {r.users} pengguna
                            </div>
                        </div>
                    ))}
                </div>

                {/* Permission matrix */}
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
                            Matriks Izin · Modul × Peran
                        </div>
                        <div
                            style={{
                                fontSize: 12,
                                color: C.faint,
                                display: 'flex',
                                alignItems: 'center',
                                gap: 6,
                            }}
                        >
                            <AIcon name="info" size={14} />
                            Klik sel untuk mengubah izin
                        </div>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 620,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th
                                        style={{
                                            padding: '13px 18px',
                                            textAlign: 'left',
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color: C.faint,
                                            textTransform: 'uppercase',
                                        }}
                                    >
                                        Modul / Menu
                                    </th>
                                    {permHeaders.map((h, i) => (
                                        <th
                                            key={i}
                                            style={{
                                                padding: '13px 16px',
                                                textAlign: 'center',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.muted,
                                                textTransform: 'uppercase',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {modules.map((module, rowIdx) => (
                                    <tr
                                        key={module.key}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                fontSize: 13.5,
                                                fontWeight: 500,
                                                color: C.text,
                                            }}
                                        >
                                            {module.label}
                                        </td>
                                        {roles.map((role, colIdx) => {
                                            const on = matrix[rowIdx][colIdx];

                                            return (
                                                <td
                                                    key={role.id}
                                                    style={{
                                                        padding: '11px 16px',
                                                        textAlign: 'center',
                                                    }}
                                                >
                                                    <button
                                                        onClick={() =>
                                                            toggleCell(
                                                                rowIdx,
                                                                colIdx,
                                                            )
                                                        }
                                                        style={{
                                                            width: 30,
                                                            height: 30,
                                                            borderRadius: 8,
                                                            border: 'none',
                                                            cursor: 'pointer',
                                                            display:
                                                                'inline-flex',
                                                            alignItems:
                                                                'center',
                                                            justifyContent:
                                                                'center',
                                                            transition: '.15s',
                                                            background: on
                                                                ? 'rgba(22,163,74,.12)'
                                                                : C.line,
                                                            color: on
                                                                ? C.green
                                                                : C.faint,
                                                        }}
                                                    >
                                                        <AIcon
                                                            name={
                                                                on
                                                                    ? 'check'
                                                                    : 'minus'
                                                            }
                                                            size={15}
                                                            color={
                                                                on
                                                                    ? C.green
                                                                    : C.faint
                                                            }
                                                        />
                                                    </button>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Buat Role Kustom modal */}
            {roleModalOpen && (
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
                        onClick={closeRoleModal}
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
                                background: C.primary + '1a',
                                color: C.primary,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: 16,
                            }}
                        >
                            <AIcon name="shield" size={22} color={C.primary} />
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Buat Role Kustom
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Tambahkan peran baru untuk tenant Anda. Atur izinnya
                            melalui matriks setelah dibuat.
                        </div>
                        <div style={{ marginTop: 18 }}>
                            <label
                                style={{
                                    display: 'block',
                                    fontSize: 13,
                                    fontWeight: 500,
                                    marginBottom: 7,
                                }}
                            >
                                Nama Role{' '}
                                <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="text"
                                value={roleName}
                                autoFocus
                                onChange={(event) =>
                                    setRoleName(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        submitRole();
                                    }
                                }}
                                placeholder="mis. Supervisor Cabang"
                                style={{
                                    width: '100%',
                                    height: 42,
                                    padding: '0 13px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    fontSize: 13.5,
                                    color: C.text,
                                    outline: 'none',
                                }}
                            />
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={closeRoleModal}
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
                                onClick={submitRole}
                                disabled={roleName.trim() === ''}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor:
                                        roleName.trim() === ''
                                            ? 'not-allowed'
                                            : 'pointer',
                                    opacity: roleName.trim() === '' ? 0.6 : 1,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="plus" size={16} />
                                Buat Role
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
