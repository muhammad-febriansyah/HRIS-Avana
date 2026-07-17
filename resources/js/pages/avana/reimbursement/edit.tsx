import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ReimbursementController from '@/actions/App/Http/Controllers/Avana/ReimbursementController';
import { AIcon, C } from '@/lib/avana';
import { ReimbursementForm } from './reimbursement-form';
import type {
    FlashProps,
    ReimbursementEditProps,
    ReimbursementFormData,
} from './types';

export default function ReimbursementEdit({
    reimbursement,
    employees,
    categories,
}: ReimbursementEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ReimbursementFormData>({
        employee_id: String(reimbursement.employee_id),
        category: reimbursement.category,
        title: reimbursement.title,
        amount: String(reimbursement.amount),
        expense_date: reimbursement.expense_date ?? '',
        description: reimbursement.description ?? '',
        notes: reimbursement.notes ?? '',
        receipt: null,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(ReimbursementController.update(reimbursement.id), {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title="Ubah Reimbursement" />
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
                        href={ReimbursementController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Reimbursement
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>
                        {reimbursement.number}
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
                    Ubah Pengajuan Reimbursement
                </h1>

                <ReimbursementForm
                    form={form}
                    employees={employees}
                    categories={categories}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={ReimbursementController.index().url}
                    existingReceiptUrl={reimbursement.receipt_url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
