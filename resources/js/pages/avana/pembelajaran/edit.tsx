import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import LearningController from '@/actions/App/Http/Controllers/Avana/LearningController';
import { AIcon, C } from '@/lib/avana';
import { PembelajaranForm } from './pembelajaran-form';
import type { FlashProps, TrainingFormData, TrainingRow } from './types';

interface PembelajaranEditProps {
    training: TrainingRow;
}

export default function PembelajaranEdit({ training }: PembelajaranEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TrainingFormData>({
        title: training.title,
        category: training.category,
        type: training.type,
        start_date: training.start_date ?? '',
        end_date: training.end_date ?? '',
        cost: String(training.cost),
        instructor: training.instructor ?? '',
        quota: training.quota ? String(training.quota) : '',
        status: training.status,
        description: training.description ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LearningController.update(training.id));
    };

    return (
        <>
            <Head title="Ubah Pelatihan" />
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
                        href={LearningController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Pembelajaran
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{training.title}</span>
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
                    Ubah Pelatihan
                </h1>

                <PembelajaranForm
                    form={form}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={LearningController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
