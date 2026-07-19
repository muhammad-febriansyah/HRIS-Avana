import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import SettlementController from '@/actions/App/Http/Controllers/Avana/SettlementController';
import { AIcon, C } from '@/lib/avana';
import { SettlementForm } from './settlement-form';
import {
    emptySettlementLine,
    type FlashProps,
    type SettlementEditProps,
    type SettlementFormData,
} from './types';

export default function SettlementEdit({
    settlement,
    employees,
    categories,
}: SettlementEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<SettlementFormData>({
        employee_id: String(settlement.employee_id),
        title: settlement.title,
        category: settlement.category ?? '',
        department: settlement.department ?? '',
        submission_date: settlement.submission_date ?? '',
        notes: settlement.notes ?? '',
        items:
            settlement.items.length > 0
                ? settlement.items.map((item) => ({
                      description: item.description,
                      // Carried through untouched; the web form has no input.
                      detail: item.detail ?? '',
                      category: item.category,
                      amount: String(item.amount ?? ''),
                  }))
                : [emptySettlementLine()],
        attachments: [],
        action: 'draft',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submit = (action: 'draft' | 'submit'): void => {
        form.transform((data) => ({ ...data, action }));
        form.post(SettlementController.update(settlement.id).url, {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title={`Ubah ${settlement.number ?? 'Settlement'}`} />
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
                        href={SettlementController.index().url}
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Settlement
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>
                        Ubah {settlement.number}
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
                    Ubah Pengajuan Settlement
                </h1>

                <SettlementForm
                    form={form}
                    employees={employees}
                    categories={categories}
                    cancelHref={SettlementController.show(settlement.id).url}
                    existingAttachments={settlement.attachments}
                    submit={submit}
                />
            </div>
        </>
    );
}
