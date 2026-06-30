import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import LeaveTypeController from '@/actions/App/Http/Controllers/Avana/LeaveTypeController';
import { AIcon, C } from '@/lib/avana';
import { JenisCutiForm } from './jenis-cuti-form';
import type { FlashProps, LeaveTypeFormData, LeaveTypeRow } from './types';

interface JenisCutiEditProps {
    leaveType: LeaveTypeRow;
}

export default function JenisCutiEdit({ leaveType }: JenisCutiEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<LeaveTypeFormData>({
        code: leaveType.code,
        name: leaveType.name,
        default_quota: String(leaveType.default_quota),
        allow_negative: leaveType.allow_negative,
        requires_attachment: leaveType.requires_attachment,
        status: leaveType.status,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LeaveTypeController.update(leaveType.id));
    };

    return (
        <>
            <Head title="Ubah Jenis Cuti" />
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
                    <span style={{ color: C.muted }}>{leaveType.name}</span>
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
                    Ubah Jenis Cuti
                </h1>

                <JenisCutiForm
                    form={form}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={LeaveTypeController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
