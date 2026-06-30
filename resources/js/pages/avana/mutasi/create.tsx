import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import MovementController from '@/actions/App/Http/Controllers/Avana/MovementController';
import { AIcon, C } from '@/lib/avana';
import { MutasiForm } from './mutasi-form';
import { emptyMovementForm } from './types';
import type {
    FlashProps,
    MovementFormData,
    MutasiCreateProps,
} from './types';

export default function MutasiCreate({
    employees,
    positions,
    departments,
    branches,
}: MutasiCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<MovementFormData>({ ...emptyMovementForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(MovementController.store());
    };

    return (
        <>
            <Head title="Buat Mutasi" />
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
                        href={MovementController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Mutasi
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Mutasi</span>
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
                    Buat Mutasi Baru
                </h1>

                <MutasiForm
                    form={form}
                    employees={employees}
                    positions={positions}
                    departments={departments}
                    branches={branches}
                    submitLabel="Simpan Mutasi"
                    submitIcon="plus"
                    cancelHref={MovementController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
