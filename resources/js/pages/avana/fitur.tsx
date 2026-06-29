import { Head, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, C, card } from '@/lib/avana';

type Feature = {
    id: number;
    code: string;
    name: string;
    module_group: string | null;
    is_enabled: boolean;
};

type FiturProps = {
    features: Feature[];
    tenantName: string | null;
    flash?: { success?: string };
};

export default function AvanaFitur() {
    const { props } = usePage<FiturProps>();
    const { features, tenantName, flash } = props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const toggle = (feature: Feature) => {
        router.post(
            '/avana/fitur/toggle',
            { feature_id: feature.id, enabled: !feature.is_enabled },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Menu & Fitur" />
            <div style={{ padding: '28px 32px', maxWidth: 880 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                    <span>Pengaturan</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Menu &amp; Fitur</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Menu &amp; Fitur</h1>
                <div style={{ fontSize: 14, color: C.muted, marginTop: 4, marginBottom: 24 }}>
                    Atur modul yang aktif untuk {tenantName ?? 'perusahaan'}. Modul nonaktif otomatis disembunyikan dari menu sidebar.
                </div>

                <div style={{ ...card, overflow: 'hidden' }}>
                    {features.map((feature, i) => (
                        <div
                            key={feature.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '16px 20px',
                                borderTop: i === 0 ? 'none' : `1px solid ${C.line}`,
                            }}
                        >
                            <div style={{ display: 'flex', alignItems: 'center', gap: 13 }}>
                                <div
                                    style={{
                                        width: 38,
                                        height: 38,
                                        borderRadius: 10,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: feature.is_enabled ? 'rgba(47,84,201,.1)' : '#F1F3F9',
                                        color: feature.is_enabled ? C.primary : C.faint,
                                    }}
                                >
                                    <AIcon name="layout-grid" size={18} color={feature.is_enabled ? C.primary : C.faint} />
                                </div>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: C.navy }}>{feature.name}</div>
                                    <div style={{ fontSize: 12, color: C.faint }}>
                                        {feature.code}
                                        {feature.module_group ? ` · ${feature.module_group}` : ''}
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={feature.is_enabled}
                                onClick={() => toggle(feature)}
                                style={{
                                    width: 46,
                                    height: 26,
                                    borderRadius: 100,
                                    border: 'none',
                                    cursor: 'pointer',
                                    position: 'relative',
                                    transition: 'background .15s',
                                    background: feature.is_enabled ? C.primary : '#D5DCEA',
                                    flex: 'none',
                                }}
                            >
                                <span
                                    style={{
                                        position: 'absolute',
                                        top: 3,
                                        left: feature.is_enabled ? 23 : 3,
                                        width: 20,
                                        height: 20,
                                        borderRadius: '50%',
                                        background: '#fff',
                                        transition: 'left .15s',
                                        boxShadow: '0 1px 3px rgba(15,23,42,.2)',
                                    }}
                                />
                            </button>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
