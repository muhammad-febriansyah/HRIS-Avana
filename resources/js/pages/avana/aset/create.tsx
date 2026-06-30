import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import AssetController from '@/actions/App/Http/Controllers/Avana/AssetController';
import { AIcon, C } from '@/lib/avana';
import { AsetForm } from './aset-form';
import { emptyAssetForm } from './types';
import type {
    AssetFormData,
    AssetFormOptions,
    FlashProps,
} from './types';

export default function AsetCreate({
    categories,
    conditions,
    statuses,
}: AssetFormOptions) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<AssetFormData>({ ...emptyAssetForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(AssetController.store());
    };

    return (
        <>
            <Head title="Tambah Aset" />
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
                        href={AssetController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Aset
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Aset</span>
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
                    Tambah Aset Baru
                </h1>

                <AsetForm
                    form={form}
                    categories={categories}
                    conditions={conditions}
                    statuses={statuses}
                    submitLabel="Simpan Aset"
                    submitIcon="plus"
                    cancelHref={AssetController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
