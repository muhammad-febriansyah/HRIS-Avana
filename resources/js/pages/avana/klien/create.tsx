import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { AIcon, C } from '@/lib/avana';
import { KlienForm } from './klien-form';
import { emptyTenantForm } from './types';
import type { FlashProps, PackageOption, TenantFormData } from './types';

interface KlienCreateProps {
    packages: PackageOption[];
}

export default function KlienCreate({ packages }: KlienCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TenantFormData>({ ...emptyTenantForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(TenantController.store());
    };

    return (
        <>
            <Head title="Tambah Klien" />
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
                        href={TenantController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Klien
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Klien</span>
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
                    Tambah Klien Baru
                </h1>
                <p
                    style={{
                        fontSize: 13,
                        color: C.muted,
                        margin: '-16px 0 24px',
                        lineHeight: 1.6,
                        maxWidth: 640,
                    }}
                >
                    Sekali simpan: klien dibuat, seluruh modul diaktifkan, role
                    &amp; menu bawaan disiapkan, dan akun admin-nya langsung
                    bisa login. Kredensialnya ditampilkan setelah ini.
                </p>

                <KlienForm
                    form={form}
                    packages={packages}
                    submitLabel="Buat Klien & Akun Admin"
                    submitIcon="plus"
                    cancelHref={TenantController.index().url}
                    onSubmit={handleSubmit}
                    withAdminAccount
                />
            </div>
        </>
    );
}
