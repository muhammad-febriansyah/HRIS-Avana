import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import PerformanceController from '@/actions/App/Http/Controllers/Avana/PerformanceController';
import { AIcon, C } from '@/lib/avana';
import { KinerjaForm } from './kinerja-form';
import { emptyReviewForm } from './types';
import type {
    CycleOption,
    EmployeeOption,
    FlashProps,
    ReviewFormData,
    SelectOption,
} from './types';

interface KinerjaCreateProps {
    employees: EmployeeOption[];
    cycleOptions: CycleOption[];
    statuses: SelectOption[];
}

export default function KinerjaCreate({
    employees,
    cycleOptions,
    statuses,
}: KinerjaCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ReviewFormData>({ ...emptyReviewForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(PerformanceController.store());
    };

    return (
        <>
            <Head title="Tambah Penilaian" />
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
                        href={PerformanceController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Kinerja
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Penilaian</span>
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
                    Tambah Penilaian Kinerja
                </h1>

                <KinerjaForm
                    form={form}
                    employees={employees}
                    cycleOptions={cycleOptions}
                    statuses={statuses}
                    submitLabel="Simpan Penilaian"
                    submitIcon="plus"
                    cancelHref={PerformanceController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
