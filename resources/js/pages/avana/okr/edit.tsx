import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import OkrController from '@/actions/App/Http/Controllers/Avana/OkrController';
import { AIcon, C } from '@/lib/avana';
import { OkrForm } from './okr-form';
import type { FlashProps, ObjectiveFormData, OkrFormOptions } from './types';

/** The objective record as serialized by `OkrController@edit`. */
interface ObjectiveEditRecord {
    id: number;
    title: string;
    description: string | null;
    level: string;
    status: string;
    cycle_id: number | null;
    employee_id: number | null;
}

interface OkrEditProps extends OkrFormOptions {
    objective: ObjectiveEditRecord;
}

export default function OkrEdit({
    objective,
    cycles,
    employees,
    levels,
    statuses,
}: OkrEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ObjectiveFormData>({
        title: objective.title,
        description: objective.description ?? '',
        level: objective.level,
        status: objective.status,
        cycle_id: objective.cycle_id ? String(objective.cycle_id) : '',
        employee_id: objective.employee_id ? String(objective.employee_id) : '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(OkrController.update(objective.id));
    };

    return (
        <>
            <Head title="Ubah Objective" />
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
                    <span style={{ color: C.muted }}>Ubah Objective</span>
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
                    Ubah Objective
                </h1>

                <OkrForm
                    form={form}
                    cycles={cycles}
                    employees={employees}
                    levels={levels}
                    statuses={statuses}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={OkrController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
