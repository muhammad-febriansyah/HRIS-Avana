import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ClaimController from '@/actions/App/Http/Controllers/Avana/ClaimController';
import { AIcon, C } from '@/lib/avana';
import { KlaimForm } from './klaim-form';
import type { ClaimFormData, KlaimEditProps } from './types';

export default function KlaimEdit({ claim, employees, claimTypes }: KlaimEditProps) {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;

    const form = useForm<ClaimFormData>({
        employee_id: String(claim.employee_id),
        claim_type: claim.claim_type,
        title: claim.title,
        amount: String(claim.amount),
        claim_date: claim.claim_date ?? '',
        description: claim.description ?? '',
        notes: claim.notes ?? '',
        receipt: null,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(ClaimController.update(claim.id), { forceFormData: true });
    };

    return (
        <>
            <Head title="Ubah Klaim" />
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
                        href={ClaimController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Klaim &amp; Reimbursement
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{claim.title}</span>
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
                    Ubah Klaim
                </h1>

                <KlaimForm
                    form={form}
                    employees={employees}
                    claimTypes={claimTypes}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={ClaimController.index().url}
                    existingReceiptUrl={claim.receipt_url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
