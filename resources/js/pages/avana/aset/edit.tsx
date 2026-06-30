import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import AssetController from '@/actions/App/Http/Controllers/Avana/AssetController';
import { AIcon, C } from '@/lib/avana';
import { AsetForm } from './aset-form';
import type {
    AssetFormData,
    AssetFormOptions,
    AssetRecord,
    FlashProps,
} from './types';

interface AsetEditProps extends AssetFormOptions {
    asset: AssetRecord;
}

export default function AsetEdit({
    asset,
    categories,
    conditions,
    statuses,
}: AsetEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<AssetFormData>({
        code: asset.code,
        name: asset.name,
        category: asset.category,
        purchase_date: asset.purchase_date ?? '',
        purchase_cost: String(asset.purchase_cost),
        depreciation_years: String(asset.depreciation_years),
        condition: asset.condition,
        status: asset.status,
        notes: asset.notes ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(AssetController.update(asset.id));
    };

    return (
        <>
            <Head title="Ubah Aset" />
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
                    <span style={{ color: C.muted }}>{asset.name}</span>
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
                    Ubah Aset
                </h1>

                <AsetForm
                    form={form}
                    categories={categories}
                    conditions={conditions}
                    statuses={statuses}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={AssetController.index().url}
                    onSubmit={handleSubmit}
                />
            </div>
        </>
    );
}
