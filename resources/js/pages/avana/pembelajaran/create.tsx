import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import LearningController from '@/actions/App/Http/Controllers/Avana/LearningController';
import { AIcon, C } from '@/lib/avana';
import { PembelajaranForm } from './pembelajaran-form';
import { emptyTrainingForm } from './types';
import type { FlashProps, TrainingFormData } from './types';

export default function PembelajaranCreate() {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TrainingFormData>({ ...emptyTrainingForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LearningController.store());
    };

    return (
        <>
            <Head title="Tambah Pelatihan" />
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
                    <span style={{ color: C.muted }}>Tambah Pelatihan</span>
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
                    Tambah Pelatihan Baru
                </h1>

                <PembelajaranForm
                    form={form}
                    submitLabel="Simpan Pelatihan"
                    submitIcon="plus"
                    cancelHref={LearningController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
