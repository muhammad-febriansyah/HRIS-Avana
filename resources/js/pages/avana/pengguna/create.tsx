import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import UserController from '@/actions/App/Http/Controllers/Avana/UserController';
import { AIcon, C } from '@/lib/avana';
import { PenggunaForm } from './pengguna-form';
import { emptyUserForm } from './types';
import type {
    BranchOption,
    FlashProps,
    RoleOption,
    UserFormData,
} from './types';

interface PenggunaCreateProps {
    roles: RoleOption[];
    branches: BranchOption[];
}

export default function PenggunaCreate({
    roles,
    branches = [],
}: PenggunaCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<UserFormData>({ ...emptyUserForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(UserController.store());
    };

    return (
        <>
            <Head title="Tambah Pengguna" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href={UserController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Pengguna
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Pengguna</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 24px',
                        letterSpacing: '-.01em',
                    }}
                >
                    Tambah Pengguna Baru
                </h1>

                <PenggunaForm
                    form={form}
                    roles={roles}
                    branches={branches}
                    isEdit={false}
                    submitLabel="Simpan Pengguna"
                    submitIcon="plus"
                    cancelHref={UserController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
