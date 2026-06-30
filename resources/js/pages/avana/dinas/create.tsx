import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import DutyTravelController from '@/actions/App/Http/Controllers/Avana/DutyTravelController';
import { AIcon, C } from '@/lib/avana';
import { DinasForm } from './dinas-form';
import { emptyTravelForm } from './types';
import type { DinasCreateProps, FlashProps, TravelFormData } from './types';

export default function DinasCreate({ employees }: DinasCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TravelFormData>({ ...emptyTravelForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(DutyTravelController.store());
    };

    return (
        <>
            <Head title="Ajukan Perjalanan Dinas" />
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
                        href={DutyTravelController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Perjalanan Dinas
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Ajukan Dinas</span>
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
                    Ajukan Perjalanan Dinas
                </h1>

                <DinasForm
                    form={form}
                    employees={employees}
                    submitLabel="Ajukan Dinas"
                    submitIcon="plus"
                    cancelHref={DutyTravelController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
