import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { AIcon, C } from '@/lib/avana';
import { RekrutmenForm } from './rekrutmen-form';
import type {
    DepartmentOption,
    FlashProps,
    PostingFormData,
    PostingRow,
} from './types';

interface RekrutmenEditProps {
    posting: PostingRow;
    departments: DepartmentOption[];
}

export default function RekrutmenEdit({
    posting,
    departments,
}: RekrutmenEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<PostingFormData>({
        title: posting.title,
        department_id: posting.department_id
            ? String(posting.department_id)
            : '',
        location: posting.location ?? '',
        employment_type: posting.employment_type,
        quota: String(posting.quota),
        status: posting.status,
        description: posting.description ?? '',
        posted_date: posting.posted_date ?? '',
        closing_date: posting.closing_date ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(RecruitmentController.update(posting.id));
    };

    return (
        <>
            <Head title="Ubah Lowongan" />
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
                        href={RecruitmentController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Rekrutmen
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{posting.title}</span>
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
                    Ubah Lowongan
                </h1>

                <RekrutmenForm
                    form={form}
                    departments={departments}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={RecruitmentController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
