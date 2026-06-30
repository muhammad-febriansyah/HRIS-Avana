import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ContractController from '@/actions/App/Http/Controllers/Avana/ContractController';
import { AIcon, C } from '@/lib/avana';
import { KontrakForm } from './kontrak-form';
import { emptyContractForm } from './types';
import type {
    ContractFormData,
    EmployeeOption,
    FlashProps,
} from './types';

interface KontrakCreateProps {
    employees: EmployeeOption[];
}

export default function KontrakCreate({ employees }: KontrakCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ContractFormData>({ ...emptyContractForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(ContractController.store());
    };

    return (
        <>
            <Head title="Tambah Kontrak" />
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
                        href={ContractController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Kontrak Kerja
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Kontrak</span>
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
                    Tambah Kontrak Baru
                </h1>

                <KontrakForm
                    form={form}
                    employees={employees}
                    submitLabel="Simpan Kontrak"
                    submitIcon="plus"
                    cancelHref={ContractController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
