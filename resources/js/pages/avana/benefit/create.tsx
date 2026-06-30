import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import BenefitController from '@/actions/App/Http/Controllers/Avana/BenefitController';
import { AIcon, C } from '@/lib/avana';
import { BenefitForm } from './benefit-form';
import { emptyBenefitForm } from './types';
import type { BenefitFormData, FlashProps } from './types';

export default function BenefitCreate() {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<BenefitFormData>({ ...emptyBenefitForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(BenefitController.store());
    };

    return (
        <>
            <Head title="Tambah Benefit" />
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
                        href={BenefitController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Benefit
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Benefit</span>
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
                    Tambah Benefit Baru
                </h1>

                <BenefitForm
                    form={form}
                    submitLabel="Simpan Benefit"
                    submitIcon="plus"
                    cancelHref={BenefitController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
