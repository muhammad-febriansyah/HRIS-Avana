import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import PerformanceController from '@/actions/App/Http/Controllers/Avana/PerformanceController';
import { AIcon, C } from '@/lib/avana';
import { KinerjaForm } from './kinerja-form';
import type {
    CycleOption,
    EmployeeOption,
    FlashProps,
    ReviewFormData,
    SelectOption,
} from './types';

/** The review record as serialized by `PerformanceController@edit`. */
interface ReviewEditRecord {
    id: number;
    cycle_id: number;
    employee_id: number;
    reviewer_id: number | null;
    self_score: number | null;
    manager_score: number | null;
    final_score: number | null;
    status: string;
    notes: string | null;
    review_date: string | null;
}

interface KinerjaEditProps {
    review: ReviewEditRecord;
    employees: EmployeeOption[];
    cycleOptions: CycleOption[];
    statuses: SelectOption[];
}

export default function KinerjaEdit({
    review,
    employees,
    cycleOptions,
    statuses,
}: KinerjaEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ReviewFormData>({
        cycle_id: String(review.cycle_id),
        employee_id: String(review.employee_id),
        reviewer_id: review.reviewer_id ? String(review.reviewer_id) : '',
        self_score: review.self_score !== null ? String(review.self_score) : '',
        manager_score:
            review.manager_score !== null ? String(review.manager_score) : '',
        final_score:
            review.final_score !== null ? String(review.final_score) : '',
        status: review.status,
        notes: review.notes ?? '',
        review_date: review.review_date ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(PerformanceController.update(review.id));
    };

    return (
        <>
            <Head title="Ubah Penilaian" />
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
                    <span style={{ color: C.muted }}>Ubah Penilaian</span>
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
                    Ubah Penilaian Kinerja
                </h1>

                <KinerjaForm
                    form={form}
                    employees={employees}
                    cycleOptions={cycleOptions}
                    statuses={statuses}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={PerformanceController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
