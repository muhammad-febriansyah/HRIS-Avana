import type { PropsWithChildren } from 'react';
import { C } from '@/lib/avana';

/**
 * Bare shell for the referral partner portal (/mitra) — background only. The
 * sidebar, top bar and logout live in the page itself (mitra/index.tsx),
 * which owns the active-section state a persistent layout can't share with
 * it. Deliberately separate from AvanaLayout: a partner has no tenant, no
 * employee record, and none of that layout's sidebar/permission machinery
 * applies to them — see EnsurePartner.
 */
export default function MitraLayout({ children }: PropsWithChildren) {
    return <div style={{ minHeight: '100dvh', background: C.surface }}>{children}</div>;
}
