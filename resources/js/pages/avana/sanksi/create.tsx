import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import AttendancePenaltyController from '@/actions/App/Http/Controllers/Avana/AttendancePenaltyController';
import { AIcon, C } from '@/lib/avana';
import { SanksiForm } from './sanksi-form';
import { emptyPenaltyForm } from './types';
import type { FlashProps, PenaltyFormData, SanksiCreateProps } from './types';

export default function SanksiCreate({ employees }: SanksiCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<PenaltyFormData>({ ...emptyPenaltyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(AttendancePenaltyController.store());
    };

    return (
        <>
            <Head title="Tambah Sanksi" />
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
                        href={AttendancePenaltyController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Sanksi
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Sanksi</span>
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
                    Tambah Sanksi Baru
                </h1>

                <SanksiForm
                    form={form}
                    employees={employees}
                    submitLabel="Simpan Sanksi"
                    submitIcon="plus"
                    cancelHref={AttendancePenaltyController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
