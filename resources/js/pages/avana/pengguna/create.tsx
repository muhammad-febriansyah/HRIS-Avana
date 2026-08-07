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
    LinkableEmployee,
    RoleOption,
    UserFormData,
} from './types';

interface PenggunaCreateProps {
    roles: RoleOption[];
    branches: BranchOption[];
    linkableEmployees: LinkableEmployee[];
}

export default function PenggunaCreate({
    roles,
    branches = [],
    linkableEmployees = [],
}: PenggunaCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<UserFormData>({ ...emptyUserForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
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

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 10,
                        padding: '12px 16px',
                        borderRadius: 10,
                        background: '#FFFBEB',
                        border: '1px solid #FDE68A',
                        marginBottom: 16,
                        maxWidth: 760,
                    }}
                >
                    <AIcon name="smartphone" size={17} color="#B45309" />
                    <div style={{ fontSize: 13, color: '#92400E' }}>
                        Akun dari menu ini <strong>tidak punya data
                        karyawan</strong>, jadi aplikasi mobile karyawan akan
                        menolaknya di menu absensi, cuti, dan profil. Untuk
                        akun yang dipakai absen di HP, buat dari form{' '}
                        <strong>Karyawan</strong> — atau tautkan akun ini
                        nanti lewat Karyawan → Akun &amp; Akses.
                    </div>
                </div>

                <PenggunaForm
                    form={form}
                    roles={roles}
                    branches={branches}
                    linkableEmployees={linkableEmployees}
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
