import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ContractController from '@/actions/App/Http/Controllers/Avana/ContractController';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import { AIcon, C } from '@/lib/avana';
import { KontrakForm } from './kontrak-form';
import type { ContractFormData, EmployeeOption, FlashProps } from './types';

/** The flat contract record serialized by `ContractController@edit`. */
interface EditContract {
    id: number;
    route_key: string;
    contract_number: string;
    employee_id: number;
    contract_type: string;
    start_date: string | null;
    end_date: string | null;
    status: string;
    notes: string | null;
    document_name?: string | null;
    document_size?: number | null;
    has_document?: boolean;
}

interface KontrakEditProps {
    contract: EditContract;
    employees: EmployeeOption[];
    /** Set when the form was opened from an employee's Kontrak tab. */
    return_to?: string | null;
}

export default function KontrakEdit({
    contract,
    employees,
    return_to = null,
}: KontrakEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ContractFormData>({
        employee_id: String(contract.employee_id),
        contract_number: contract.contract_number,
        contract_type: contract.contract_type,
        start_date: contract.start_date ?? '',
        end_date: contract.end_date ?? '',
        status: contract.status,
        notes: contract.notes ?? '',
        document: null,
        return_to: return_to ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    /** Detach the stored document without touching the contract itself. */
    const removeDocument = () => {
        if (!window.confirm('Hapus dokumen kontrak ini?')) {
            return;
        }

        router.delete(ContractController.destroyDocument(contract.route_key).url, {
            preserveScroll: true,
        });
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        // A PUT cannot carry multipart, so the upload is posted with a method
        // override — the route stays PUT.
        form.transform((payload) => ({ ...payload, _method: 'put' }));
        form.post(ContractController.update(contract.route_key).url, {
            forceFormData: true,
        });
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
                    existingDocument={
                        contract.has_document
                            ? {
                                  name: contract.document_name ?? 'Dokumen kontrak',
                                  size: contract.document_size ?? null,
                                  href: ContractController.download(contract.route_key).url,
                              }
                            : null
                    }
                    onRemoveDocument={removeDocument}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
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
