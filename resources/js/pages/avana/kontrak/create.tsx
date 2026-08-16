import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ContractController from '@/actions/App/Http/Controllers/Avana/ContractController';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import { AIcon, C } from '@/lib/avana';
import { KontrakForm } from './kontrak-form';
import { emptyContractForm } from './types';
import type { ContractFormData, EmployeeOption, FlashProps } from './types';

interface KontrakCreateProps {
    employees: EmployeeOption[];
    /** Preselected when the form was opened from an employee's Kontrak tab. */
    selected_employee_id?: number | null;
    return_to?: string | null;
}

export default function KontrakCreate({
    employees,
    selected_employee_id = null,
    return_to = null,
}: KontrakCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ContractFormData>({
        ...emptyContractForm,
        employee_id:
            selected_employee_id === null ? '' : String(selected_employee_id),
        return_to: return_to ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        // forceFormData: the contract document rides along as multipart.
        form.post(ContractController.store().url, { forceFormData: true });
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
                    cancelHref={
                        return_to
                            ? EmployeeController.show(return_to).url
                            : ContractController.index().url
                    }
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
