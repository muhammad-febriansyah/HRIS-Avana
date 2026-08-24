import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { AIcon, C } from '@/lib/avana';
import { logout } from '@/routes';

/**
 * Chrome for the referral partner portal (/mitra). Deliberately separate from
 * AvanaLayout: a partner has no tenant, no employee record, and none of that
 * layout's sidebar/permission machinery applies to them — see EnsurePartner.
 */
export default function MitraLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;
    const user = auth.user as { name: string; email: string } | null;

    const handleLogout = () => router.flushAll();

    return (
        <div style={{ minHeight: '100dvh', background: C.surface }}>
            <header
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '14px 28px',
                    background: '#fff',
                    borderBottom: `1px solid ${C.border}`,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        fontWeight: 700,
                        fontSize: 15,
                        color: C.navy,
                    }}
                >
                    <AIcon name="handshake" size={20} color={C.primary} />
                    AvanaHR
                    <span style={{ color: C.muted, fontWeight: 500 }}>
                        Mitra Referral
                    </span>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <div style={{ textAlign: 'right' }}>
                        <div
                            style={{
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.text,
                            }}
                        >
                            {user?.name}
                        </div>
                        <div style={{ fontSize: 12, color: C.muted }}>
                            {user?.email}
                        </div>
                    </div>
                    <Link
                        href={logout()}
                        onClick={handleLogout}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            height: 34,
                            padding: '0 12px',
                            borderRadius: 8,
                            border: `1px solid ${C.border}`,
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: C.text,
                            textDecoration: 'none',
                        }}
                    >
                        <AIcon name="log-out" size={14} />
                        Keluar
                    </Link>
                </div>
            </header>

            <main style={{ maxWidth: 1180, margin: '0 auto', padding: '28px 24px' }}>
                {children}
            </main>
        </div>
    );
}
