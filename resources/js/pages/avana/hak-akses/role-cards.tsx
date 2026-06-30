import { AIcon, C, card } from '@/lib/avana';
import type { AccessRole } from './types';

interface RoleCardsProps {
    roles: AccessRole[];
}

/** Responsive grid of role summary cards (icon, name, description, user count). */
export function RoleCards({ roles }: RoleCardsProps) {
    return (
        <div
            className="avn-kpi"
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(4,1fr)',
                gap: 14,
                marginBottom: 18,
            }}
        >
            {roles.map((r) => (
                <div key={r.id} style={{ ...card, padding: '16px 18px' }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <div
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 9,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: r.color + '1a',
                                color: r.color,
                            }}
                        >
                            <AIcon name="shield" size={18} color={r.color} />
                        </div>
                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            {r.name}
                        </div>
                    </div>
                    <div
                        style={{
                            fontSize: 12,
                            color: C.muted,
                            marginTop: 10,
                            lineHeight: 1.45,
                            minHeight: 34,
                        }}
                    >
                        {r.desc}
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            fontSize: 12,
                            color: C.faint,
                            marginTop: 8,
                            paddingTop: 10,
                            borderTop: `1px solid ${C.line}`,
                        }}
                    >
                        <AIcon name="users" size={14} />
                        {r.users} pengguna
                    </div>
                </div>
            ))}
        </div>
    );
}
