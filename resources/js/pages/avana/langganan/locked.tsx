import { Head, Link, usePage } from '@inertiajs/react';
import { WaIcon } from '@/components/avana-ui/wa-icon';
import { AIcon, C, card, hexA } from '@/lib/avana';

/* ============================================================
 * Lock notice for a lapsed tenant. Every screen redirects here
 * until the subscription is renewed.
 * ============================================================ */

interface PageProps {
    subscription: {
        end_date: string;
        end_date_label: string;
        days_left: number;
        level: string;
        package: string | null;
    } | null;
    canRenew: boolean;
    tenantName: string;
}

type SharedProps = {
    website?: { contact?: { phone?: string; whatsapp?: string } };
};

export default function Locked({
    subscription,
    canRenew,
    tenantName,
}: PageProps) {
    const { website } = usePage<SharedProps>().props;
    const whatsapp =
        website?.contact?.whatsapp || website?.contact?.phone || '';
    const waHref = `https://wa.me/${whatsapp.replace(/[^\d]/g, '')}`;
    const daysOver =
        subscription && subscription.days_left < 0
            ? Math.abs(subscription.days_left)
            : 0;

    return (
        <>
            <Head title="Langganan Berakhir" />
            <div
                style={{
                    minHeight: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: '48px 24px',
                }}
            >
                <div
                    style={{
                        ...card,
                        maxWidth: 560,
                        width: '100%',
                        padding: '34px 32px',
                        textAlign: 'center',
                        border: `1px solid ${hexA(C.red, 0.25)}`,
                    }}
                >
                    <div
                        style={{
                            width: 62,
                            height: 62,
                            margin: '0 auto 18px',
                            borderRadius: 18,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            background: hexA(C.red, 0.1),
                        }}
                    >
                        <AIcon name="lock" size={28} color={C.red} />
                    </div>

                    <h1
                        style={{
                            fontSize: 22,
                            fontWeight: 700,
                            color: C.navy,
                            margin: 0,
                        }}
                    >
                        Langganan telah berakhir
                    </h1>

                    <div
                        style={{
                            fontSize: 13.5,
                            color: C.muted,
                            marginTop: 10,
                            lineHeight: 1.6,
                        }}
                    >
                        Semua fitur AvanaHR untuk <strong>{tenantName}</strong>{' '}
                        dikunci sementara
                        {subscription
                            ? ` karena masa aktif berakhir pada ${subscription.end_date_label}`
                            : ''}
                        {daysOver > 0 ? ` (${daysOver} hari lalu)` : ''}. Data
                        Anda tetap aman dan langsung bisa dipakai kembali
                        setelah langganan diperpanjang.
                    </div>

                    {subscription?.package && (
                        <div
                            style={{
                                display: 'inline-block',
                                marginTop: 16,
                                fontSize: 12,
                                fontWeight: 600,
                                color: C.muted,
                                background: C.surface,
                                border: `1px solid ${C.border}`,
                                borderRadius: 999,
                                padding: '5px 13px',
                            }}
                        >
                            Paket terakhir: {subscription.package}
                        </div>
                    )}

                    <div
                        style={{
                            display: 'flex',
                            gap: 10,
                            justifyContent: 'center',
                            flexWrap: 'wrap',
                            marginTop: 26,
                        }}
                    >
                        {canRenew && (
                            <Link
                                href="/avana/langganan"
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    height: 44,
                                    padding: '0 20px',
                                    borderRadius: 10,
                                    background: C.primary,
                                    color: '#fff',
                                    fontWeight: 600,
                                    fontSize: 14,
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon
                                    name="refresh-cw"
                                    size={16}
                                    color="#fff"
                                />
                                Perpanjang Sekarang
                            </Link>
                        )}
                        <a
                            href={waHref}
                            target="_blank"
                            rel="noreferrer"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                                height: 44,
                                padding: '0 18px',
                                borderRadius: 10,
                                border: '1px solid rgba(37,211,102,.4)',
                                background: '#fff',
                                color: '#128C7E',
                                fontWeight: 600,
                                fontSize: 14,
                                textDecoration: 'none',
                            }}
                        >
                            <WaIcon size={16} color="#25D366" />
                            Hubungi AvanaHR
                        </a>
                    </div>

                    {!canRenew && (
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginTop: 18,
                            }}
                        >
                            Hubungi admin HR perusahaan Anda untuk perpanjangan.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
