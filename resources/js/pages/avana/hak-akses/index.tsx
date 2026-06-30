import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AccessController from '@/actions/App/Http/Controllers/Avana/AccessController';
import { AIcon, btnP, C } from '@/lib/avana';
import { PermissionMatrix } from './permission-matrix';
import { RoleCards } from './role-cards';
import { RoleModal } from './role-modal';
import type { FlashProps, HakAksesProps } from './types';

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
                <RoleCards roles={roles} />

                {/* Permission matrix */}
                <PermissionMatrix
                    roles={roles}
                    modules={modules}
                    permHeaders={permHeaders}
                    matrix={matrix}
                    onToggle={toggleCell}
                />
            </div>

            {/* Buat Role Kustom modal */}
            {roleModalOpen && (
                <RoleModal
                    roleName={roleName}
                    onChangeName={setRoleName}
                    onSubmit={submitRole}
                    onClose={closeRoleModal}
                />
            )}
        </>
    );
}
