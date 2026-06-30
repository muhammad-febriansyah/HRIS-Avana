import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { AIcon, C } from '@/lib/avana';
import { RekrutmenForm } from './rekrutmen-form';
import { emptyPostingForm } from './types';
import type { DepartmentOption, FlashProps, PostingFormData } from './types';

interface RekrutmenCreateProps {
    departments: DepartmentOption[];
}

export default function RekrutmenCreate({ departments }: RekrutmenCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<PostingFormData>({ ...emptyPostingForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(RecruitmentController.store());
    };

    return (
        <>
            <Head title="Tambah Lowongan" />
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
                    <span style={{ color: C.muted }}>Tambah Lowongan</span>
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
                    Tambah Lowongan Baru
                </h1>

                <RekrutmenForm
                    form={form}
                    departments={departments}
                    submitLabel="Simpan Lowongan"
                    submitIcon="plus"
                    cancelHref={RecruitmentController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
