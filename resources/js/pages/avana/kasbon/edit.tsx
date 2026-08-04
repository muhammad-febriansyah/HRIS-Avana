import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import CashAdvanceController from '@/actions/App/Http/Controllers/Avana/CashAdvanceController';
import { AIcon, C } from '@/lib/avana';
import { KasbonForm } from './kasbon-form';
import type { CashAdvanceFormData, FlashProps, KasbonEditProps } from './types';

export default function KasbonEdit({ advance, employees }: KasbonEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<CashAdvanceFormData>({
        employee_id: String(advance.employee_id),
        amount: String(advance.amount),
        purpose: advance.purpose ?? '',
        request_date: advance.request_date ?? '',
        needed_date: advance.needed_date ?? '',
        reason: advance.reason ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(CashAdvanceController.update(advance.route_key));
    };

    return (
        <>
            <Head title="Ubah Uang Muka" />
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
                        href={CashAdvanceController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Cash Advance
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Ubah Pengajuan</span>
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
                    Ubah Pengajuan Uang Muka
                </h1>

                <KasbonForm
                    form={form}
                    employees={employees}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={CashAdvanceController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
