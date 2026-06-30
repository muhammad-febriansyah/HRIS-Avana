import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import BenefitController from '@/actions/App/Http/Controllers/Avana/BenefitController';
import { AIcon, C } from '@/lib/avana';
import { BenefitForm } from './benefit-form';
import type { BenefitFormData, BenefitRow, FlashProps } from './types';

interface BenefitEditProps {
    benefit: BenefitRow;
}

export default function BenefitEdit({ benefit }: BenefitEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<BenefitFormData>({
        code: benefit.code,
        name: benefit.name,
        type: benefit.type,
        value: String(benefit.value),
        description: benefit.description ?? '',
        status: benefit.status,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(BenefitController.update(benefit.id));
    };

    return (
        <>
            <Head title="Ubah Benefit" />
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
                    <span style={{ color: C.muted }}>{benefit.name}</span>
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
                    Ubah Benefit
                </h1>

                <BenefitForm
                    form={form}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={BenefitController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
