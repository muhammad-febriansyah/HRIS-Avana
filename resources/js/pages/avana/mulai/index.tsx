import { Head, Link, usePage } from '@inertiajs/react';
import { WaIcon } from '@/components/avana-ui/wa-icon';
import { AIcon, C, card, hexA } from '@/lib/avana';

/* ============================================================
 * "Mulai" checklist — where EnsureOnboardingComplete sends a
 * tenant that hasn't picked a package or saved a company
 * profile yet. Everything else stays locked until both are done.
 * ============================================================ */

interface PageProps {
    status: {
        needs_package: boolean;
        needs_profile: boolean;
        complete: boolean;
    };
    tenantName: string;
    canManage: boolean;
}

type SharedProps = {
    website?: { contact?: { phone?: string; whatsapp?: string } };
};

export default function Mulai({ status, tenantName, canManage }: PageProps) {
    const { website } = usePage<SharedProps>().props;
    const whatsapp = website?.contact?.whatsapp || website?.contact?.phone || '';
    const waHref = `https://wa.me/${whatsapp.replace(/[^\d]/g, '')}`;

    const steps = [
        {
            key: 'profile',
            done: !status.needs_profile,
            title: 'Lengkapi Profil Perusahaan',
            description: 'Nama resmi, alamat, dan kontak perusahaan Anda.',
            href: '/avana/perusahaan',
            cta: 'Lengkapi Profil',
        },
        {
            key: 'package',
            done: !status.needs_package,
            title: 'Pilih Paket Langganan',
            description: 'Pilih paket sesuai kebutuhan tim Anda dan selesaikan pembayaran.',
            href: '/avana/langganan',
            cta: 'Pilih Paket',
        },
    ];

    return (
        <>
            <Head title="Mulai" />
            <div style={{ minHeight: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '48px 24px' }}>
                <div style={{ ...card, maxWidth: 560, width: '100%', padding: '34px 32px', border: `1px solid ${hexA(C.primary, 0.2)}` }}>
                    <div style={{ textAlign: 'center' }}>
                        <div
                            style={{
                                width: 62,
                                height: 62,
                                margin: '0 auto 18px',
                                borderRadius: 18,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: hexA(C.primary, 0.1),
                            }}
                        >
                            <AIcon name="rocket" size={28} color={C.primary} />
                        </div>
                        <h1 style={{ fontSize: 22, fontWeight: 700, color: C.navy, margin: 0 }}>Selangkah lagi, {tenantName}</h1>
                        <div style={{ fontSize: 13.5, color: C.muted, marginTop: 10, lineHeight: 1.6 }}>
                            Lengkapi dua hal ini dulu sebelum bisa masuk ke menu lainnya.
                        </div>
                    </div>

                    <div style={{ display: 'grid', gap: 12, marginTop: 26 }}>
                        {steps.map((step) => (
                            <div
                                key={step.key}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 14,
                                    padding: '14px 16px',
                                    borderRadius: 12,
                                    border: `1px solid ${step.done ? hexA(C.green, 0.25) : C.border}`,
                                    background: step.done ? hexA(C.green, 0.05) : C.surface,
                                }}
                            >
                                <span
                                    style={{
                                        width: 30,
                                        height: 30,
                                        flex: 'none',
                                        borderRadius: '50%',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: step.done ? hexA(C.green, 0.14) : hexA(C.muted, 0.12),
                                    }}
                                >
                                    <AIcon name={step.done ? 'check' : 'circle'} size={15} color={step.done ? C.green : C.faint} />
                                </span>
                                <div style={{ flex: 1, textAlign: 'left', minWidth: 0 }}>
                                    <div style={{ fontSize: 13.5, fontWeight: 700, color: C.navy }}>{step.title}</div>
                                    <div style={{ fontSize: 12, color: C.muted, marginTop: 2 }}>{step.description}</div>
                                </div>
                                {!step.done && canManage && (
                                    <Link
                                        href={step.href}
                                        style={{
                                            flex: 'none',
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            height: 36,
                                            padding: '0 14px',
                                            borderRadius: 8,
                                            background: C.primary,
                                            color: '#fff',
                                            fontWeight: 600,
                                            fontSize: 12.5,
                                            textDecoration: 'none',
                                        }}
                                    >
                                        {step.cta}
                                    </Link>
                                )}
                            </div>
                        ))}
                    </div>

                    {!canManage && (
                        <div style={{ fontSize: 12.5, color: C.faint, marginTop: 20, textAlign: 'center' }}>
                            Hubungi admin tenant Anda untuk menyelesaikan langkah ini.
                        </div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'center', marginTop: 24 }}>
                        <a
                            href={waHref}
                            target="_blank"
                            rel="noreferrer"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                                height: 40,
                                padding: '0 16px',
                                borderRadius: 10,
                                border: '1px solid rgba(37,211,102,.4)',
                                background: '#fff',
                                color: '#128C7E',
                                fontWeight: 600,
                                fontSize: 13,
                                textDecoration: 'none',
                            }}
                        >
                            <WaIcon size={15} color="#25D366" />
                            Butuh bantuan? Hubungi AvanaHR
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}
