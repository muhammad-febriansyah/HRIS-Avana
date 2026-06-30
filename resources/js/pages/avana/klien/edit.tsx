import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { AIcon, C } from '@/lib/avana';
import { KlienForm } from './klien-form';
import type {
    FlashProps,
    PackageOption,
    TenantFormData,
    TenantRecord,
} from './types';

interface KlienEditProps {
    tenant: TenantRecord;
    packages: PackageOption[];
}

export default function KlienEdit({ tenant, packages }: KlienEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TenantFormData>({
        name: tenant.name,
        company_name: tenant.company_name ?? '',
        slug: tenant.slug,
        package_id: tenant.package_id ? String(tenant.package_id) : '',
        status: tenant.status,
        max_users: String(tenant.max_users ?? ''),
        max_employees: String(tenant.max_employees ?? ''),
        max_branches: String(tenant.max_branches ?? ''),
        billing_status: tenant.billing_status ?? '',
        start_date: tenant.start_date ?? '',
        end_date: tenant.end_date ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(TenantController.update(tenant.id));
    };

    return (
        <>
            <Head title="Ubah Klien" />
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
                    <span style={{ color: C.muted }}>{tenant.name}</span>
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
                    Ubah Klien
                </h1>

                <KlienForm
                    form={form}
                    packages={packages}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={TenantController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
