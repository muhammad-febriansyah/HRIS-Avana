import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import FieldVisitController from '@/actions/App/Http/Controllers/Avana/FieldVisitController';
import { AIcon, C } from '@/lib/avana';
import { VisitingForm } from './visiting-form';
import { emptyVisitForm } from './types';
import type { FlashProps, VisitFormData, VisitingCreateProps } from './types';

export default function VisitingCreate({ employees }: VisitingCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<VisitFormData>({ ...emptyVisitForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(FieldVisitController.store(), { forceFormData: true });
    };

    return (
        <>
            <Head title="Catat Kunjungan" />
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
                        href={FieldVisitController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Visiting
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Catat Kunjungan</span>
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
                    Catat Kunjungan Baru
                </h1>

                <VisitingForm
                    form={form}
                    employees={employees}
                    submitLabel="Simpan Kunjungan"
                    submitIcon="plus"
                    cancelHref={FieldVisitController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
