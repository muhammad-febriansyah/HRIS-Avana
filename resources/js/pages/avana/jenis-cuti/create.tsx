import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import LeaveTypeController from '@/actions/App/Http/Controllers/Avana/LeaveTypeController';
import { AIcon, C } from '@/lib/avana';
import { JenisCutiForm } from './jenis-cuti-form';
import { emptyLeaveTypeForm } from './types';
import type { FlashProps, LeaveTypeFormData } from './types';

export default function JenisCutiCreate() {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<LeaveTypeFormData>({ ...emptyLeaveTypeForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LeaveTypeController.store());
    };

    return (
        <>
            <Head title="Tambah Jenis Cuti" />
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
                        href={LeaveTypeController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Jenis Cuti
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Jenis Cuti</span>
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
                    Tambah Jenis Cuti Baru
                </h1>

                <JenisCutiForm
                    form={form}
                    submitLabel="Simpan Jenis Cuti"
                    submitIcon="plus"
                    cancelHref={LeaveTypeController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
