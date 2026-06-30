import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import LetterTemplateController from '@/actions/App/Http/Controllers/Avana/LetterTemplateController';
import { AIcon, C } from '@/lib/avana';
import { SuratForm } from './surat-form';
import { emptyTemplateForm } from './types';
import type { FlashProps, SuratCreateProps, TemplateFormData } from './types';

export default function SuratCreate({ types, placeholders }: SuratCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TemplateFormData>({ ...emptyTemplateForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LetterTemplateController.store());
    };

    return (
        <>
            <Head title="Buat Template Surat" />
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
                        href={LetterTemplateController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Template Surat
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Template</span>
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
                    Buat Template Surat
                </h1>

                <SuratForm
                    form={form}
                    types={types}
                    placeholders={placeholders}
                    submitLabel="Simpan Template"
                    submitIcon="plus"
                    cancelHref={LetterTemplateController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
