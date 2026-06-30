import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ContractController from '@/actions/App/Http/Controllers/Avana/ContractController';
import { AIcon, C } from '@/lib/avana';
import { KontrakForm } from './kontrak-form';
import type {
    ContractFormData,
    EmployeeOption,
    FlashProps,
} from './types';

/** The flat contract record serialized by `ContractController@edit`. */
interface EditContract {
    id: number;
    contract_number: string;
    employee_id: number;
    contract_type: string;
    start_date: string | null;
    end_date: string | null;
    basic_salary: number;
    status: string;
    notes: string | null;
}

interface KontrakEditProps {
    contract: EditContract;
    employees: EmployeeOption[];
}

export default function KontrakEdit({ contract, employees }: KontrakEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ContractFormData>({
        employee_id: String(contract.employee_id),
        contract_number: contract.contract_number,
        contract_type: contract.contract_type,
        start_date: contract.start_date ?? '',
        end_date: contract.end_date ?? '',
        basic_salary: String(contract.basic_salary),
        status: contract.status,
        notes: contract.notes ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(ContractController.update(contract.id));
    };

    return (
        <>
            <Head title="Ubah Kontrak" />
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
                    <span style={{ color: C.muted }}>
                        {contract.contract_number}
                    </span>
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
                    Ubah Kontrak
                </h1>

                <KontrakForm
                    form={form}
                    employees={employees}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={ContractController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
