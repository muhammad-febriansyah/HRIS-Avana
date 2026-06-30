import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import LetterTemplateController from '@/actions/App/Http/Controllers/Avana/LetterTemplateController';
import { AIcon, C } from '@/lib/avana';
import { SuratForm } from './surat-form';
import type { FlashProps, SuratEditProps, TemplateFormData } from './types';

export default function SuratEdit({
    template,
    types,
    placeholders,
}: SuratEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TemplateFormData>({
        name: template.name,
        type: template.type,
        body: template.body,
        is_active: template.is_active,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(LetterTemplateController.update(template.id));
    };

    return (
        <>
            <Head title="Ubah Template Surat" />
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
                    <span style={{ color: C.muted }}>{template.name}</span>
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
                    Ubah Template Surat
                </h1>

                <SuratForm
                    form={form}
                    types={types}
                    placeholders={placeholders}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={LetterTemplateController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
