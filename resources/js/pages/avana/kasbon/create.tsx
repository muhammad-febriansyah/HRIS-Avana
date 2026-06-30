import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import CashAdvanceController from '@/actions/App/Http/Controllers/Avana/CashAdvanceController';
import { AIcon, C } from '@/lib/avana';
import { KasbonForm } from './kasbon-form';
import { emptyKasbonForm } from './types';
import type {
    CashAdvanceFormData,
    FlashProps,
    KasbonCreateProps,
} from './types';

export default function KasbonCreate({ employees }: KasbonCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<CashAdvanceFormData>({ ...emptyKasbonForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(CashAdvanceController.store());
    };

    return (
        <>
            <Head title="Ajukan Kasbon" />
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
                        Kasbon
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Ajukan Kasbon</span>
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
                    Ajukan Kasbon Baru
                </h1>

                <KasbonForm
                    form={form}
                    employees={employees}
                    submitLabel="Ajukan Kasbon"
                    submitIcon="plus"
                    cancelHref={CashAdvanceController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
