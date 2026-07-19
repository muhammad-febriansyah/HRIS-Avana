import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import FieldVisitController from '@/actions/App/Http/Controllers/Avana/FieldVisitController';
import { AIcon, C } from '@/lib/avana';
import type { FlashProps, VisitFormData, VisitingEditProps } from './types';
import { VisitingForm } from './visiting-form';

/** Continue a visit report that was parked as a draft. */
export default function VisitingEdit({
    visit,
    employees,
    branches,
}: VisitingEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<VisitFormData>({
        employee_ids: visit.employee_ids.map(String),
        branch_id: visit.branch_id === null ? '' : String(visit.branch_id),
        visit_date: visit.visit_date ?? '',
        location: visit.location ?? '',
        client_name: visit.client_name ?? '',
        purpose: visit.purpose ?? '',
        latitude: visit.latitude ?? '',
        longitude: visit.longitude ?? '',
        notes: visit.notes ?? '',
        photos: [],
        tasks: visit.tasks,
        action: 'submit',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const save = (action: 'draft' | 'submit') => {
        form.transform((data) => ({ ...data, action }));
        form.submit(FieldVisitController.update(visit.id), {
            forceFormData: true,
        });
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        save('submit');
    };

    return (
        <>
            <Head title="Lanjutkan Draft Kunjungan" />
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
                    <span style={{ color: C.muted }}>Lanjutkan Draft</span>
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
                    Lanjutkan Draft Kunjungan
                </h1>

                <VisitingForm
                    form={form}
                    employees={employees}
                    branches={branches}
                    submitLabel="Simpan Laporan"
                    submitIcon="check"
                    cancelHref={FieldVisitController.index().url}
                    onSubmit={handleSubmit}
                    onSaveDraft={() => save('draft')}
                />
            </div>
        </>
    );
}
