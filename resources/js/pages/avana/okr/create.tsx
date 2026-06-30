import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import OkrController from '@/actions/App/Http/Controllers/Avana/OkrController';
import { AIcon, C } from '@/lib/avana';
import { OkrForm } from './okr-form';
import { emptyObjectiveForm } from './types';
import type { FlashProps, ObjectiveFormData, OkrFormOptions } from './types';

export default function OkrCreate({
    cycles,
    employees,
    levels,
    statuses,
    perspectives,
}: OkrFormOptions) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ObjectiveFormData>({ ...emptyObjectiveForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(OkrController.store());
    };

    return (
        <>
            <Head title="Tambah Objective" />
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
                        href={OkrController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        OKR &amp; Goal
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Objective</span>
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
                    Tambah Objective Baru
                </h1>

                <OkrForm
                    form={form}
                    cycles={cycles}
                    employees={employees}
                    levels={levels}
                    statuses={statuses}
                    perspectives={perspectives}
                    submitLabel="Simpan Objective"
                    submitIcon="plus"
                    cancelHref={OkrController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
