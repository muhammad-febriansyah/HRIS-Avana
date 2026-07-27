import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import LeaveTypeController from '@/actions/App/Http/Controllers/Avana/LeaveTypeController';
import { AIcon, C } from '@/lib/avana';
import { JenisCutiForm } from './jenis-cuti-form';
import { subTypeToForm } from './types';
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
        // A root always stores a concrete boolean; the nullable column only
        // matters for sub-types, which inherit when left unset.
        allow_negative: leaveType.allow_negative ?? false,
        requires_attachment: leaveType.requires_attachment ?? false,
        status: leaveType.status,
        children: leaveType.children.map(subTypeToForm),
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
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
