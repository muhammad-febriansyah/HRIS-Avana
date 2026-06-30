import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import UserController from '@/actions/App/Http/Controllers/Avana/UserController';
import { AIcon, C } from '@/lib/avana';
import { PenggunaForm } from './pengguna-form';
import type {
    BranchOption,
    FlashProps,
    RoleOption,
    UserEditRecord,
    UserFormData,
} from './types';

interface PenggunaEditProps {
    user: UserEditRecord;
    roles: RoleOption[];
    branches: BranchOption[];
}

export default function PenggunaEdit({
    user,
    roles,
    branches = [],
}: PenggunaEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<UserFormData>({
        name: user.name,
        email: user.email,
        phone: user.phone ?? '',
        password: '',
        status: user.status,
        role_ids: [...user.role_ids],
        data_scope: user.data_scope ?? 'company',
        branch_ids: [...user.branch_ids],
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(UserController.update(user.id));
    };

    return (
        <>
            <Head title="Ubah Pengguna" />
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
                    <span style={{ color: C.muted }}>{user.name}</span>
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
                    Ubah Pengguna
                </h1>

                <PenggunaForm
                    form={form}
                    roles={roles}
                    branches={branches}
                    isEdit
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={UserController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
