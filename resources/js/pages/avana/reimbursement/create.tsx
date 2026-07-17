import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ReimbursementController from '@/actions/App/Http/Controllers/Avana/ReimbursementController';
import { AIcon, C } from '@/lib/avana';
import { ReimbursementForm } from './reimbursement-form';
import { emptyReimbursementForm } from './types';
import type {
    FlashProps,
    ReimbursementCreateProps,
    ReimbursementFormData,
} from './types';

export default function ReimbursementCreate({
    employees,
    categories,
}: ReimbursementCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ReimbursementFormData>({ ...emptyReimbursementForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(ReimbursementController.store(), { forceFormData: true });
    };

    return (
        <>
            <Head title="Ajukan Reimbursement" />
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
                    <span style={{ color: C.muted }}>Ajukan Reimbursement</span>
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
                    Ajukan Reimbursement Baru
                </h1>

                <ReimbursementForm
                    form={form}
                    employees={employees}
                    categories={categories}
                    submitLabel="Ajukan Reimbursement"
                    submitIcon="plus"
                    cancelHref={ReimbursementController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
