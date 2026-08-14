import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { AIcon, C } from '@/lib/avana';

export interface NotificationItem {
    id: number;
    type: string | null;
    title: string;
    body: string | null;
    is_read: boolean;
    created_at: string | null;
}

/** Icon + accent colour per notification type. */
function visual(type: string | null): { icon: string; color: string; bg: string } {
    switch (type) {
        case 'attendance':
            return { icon: 'clock', color: '#2F54C9', bg: 'rgba(47,84,201,.1)' };
        case 'approval':
            return { icon: 'check-check', color: '#16A34A', bg: 'rgba(22,163,74,.1)' };
        case 'announcement':
            return { icon: 'megaphone', color: '#EA580C', bg: 'rgba(234,88,12,.1)' };
        case 'payslip':
        case 'reimburse':
            return { icon: 'wallet', color: '#7C3AED', bg: 'rgba(124,58,237,.1)' };
        case 'invoice':
            return { icon: 'receipt', color: '#0D9488', bg: 'rgba(13,148,136,.1)' };
        case 'subscription':
            return { icon: 'refresh-cw', color: '#2F54C9', bg: 'rgba(47,84,201,.1)' };
        case 'tenant':
            return { icon: 'building-2', color: '#DB2777', bg: 'rgba(219,39,119,.1)' };
        default:
            return { icon: 'bell', color: C.faint, bg: 'rgba(100,116,139,.1)' };
    }
}

function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const then = new Date(iso).getTime();
    const secs = Math.floor((Date.now() - then) / 1000);

    if (secs < 60) {
        return 'Baru saja';
    }

    const mins = Math.floor(secs / 60);

    if (mins < 60) {
        return `${mins} mnt lalu`;
    }

    const hrs = Math.floor(mins / 60);

    if (hrs < 24) {
        return `${hrs} jam lalu`;
    }

    const days = Math.floor(hrs / 24);

    if (days < 7) {
        return `${days} hari lalu`;
    }

    return new Date(iso).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
}

interface Props {
    open: boolean;
    onClose: () => void;
    items: NotificationItem[];
    unread: number;
}

/** Right-side slide-in panel listing the current user's notifications. */
export function NotificationSheet({ open, onClose, items, unread }: Props) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };

        if (open) {
            document.addEventListener('keydown', onKey);
        }

        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    const reloadOpts = {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications'],
    };

    const markRead = (id: number, isRead: boolean) => {
        if (isRead) {
            return;
        }

        router.post(`/avana/notifications/${id}/read`, {}, reloadOpts);
    };

    const markAll = () => {
        if (unread === 0) {
            return;
        }

        router.post('/avana/notifications/read-all', {}, reloadOpts);
    };

    return (
        <>
            {/* Backdrop */}
            <div
                onClick={onClose}
                style={{
                    position: 'fixed',
                    inset: 0,
                    background: 'rgba(15,23,42,.35)',
                    opacity: open ? 1 : 0,
                    pointerEvents: open ? 'auto' : 'none',
                    transition: 'opacity .2s',
                    zIndex: 60,
                }}
            />
            {/* Panel */}
            <aside
                style={{
                    position: 'fixed',
                    top: 0,
                    right: 0,
                    height: '100vh',
                    width: 'min(400px, 100vw)',
                    background: '#fff',
                    boxShadow: '-8px 0 30px rgba(15,23,42,.12)',
                    transform: open ? 'translateX(0)' : 'translateX(100%)',
                    transition: 'transform .24s cubic-bezier(.4,0,.2,1)',
                    zIndex: 61,
                    display: 'flex',
                    flexDirection: 'column',
                    fontFamily: "'Poppins',system-ui,sans-serif",
                }}
            >
                <header
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '16px 18px',
                        borderBottom: `1px solid ${C.border}`,
                        flex: 'none',
                    }}
                >
                    <span
                        style={{ fontSize: 15, fontWeight: 700, color: C.text }}
                    >
                        Notifikasi
                    </span>
                    {unread > 0 && (
                        <span
                            style={{
                                fontSize: 11,
                                fontWeight: 700,
                                color: '#fff',
                                background: C.red,
                                borderRadius: 999,
                                padding: '1px 8px',
                            }}
                        >
                            {unread} baru
                        </span>
                    )}
                    <div style={{ flex: 1 }} />
                    {unread > 0 && (
                        <button
                            onClick={markAll}
                            style={{
                                border: 'none',
                                background: 'none',
                                cursor: 'pointer',
                                fontSize: 12,
                                fontWeight: 600,
                                color: C.primary,
                            }}
                        >
                            Tandai semua
                        </button>
                    )}
                    <button
                        onClick={onClose}
                        aria-label="Tutup"
                        style={{
                            border: 'none',
                            background: 'none',
                            cursor: 'pointer',
                            color: C.faint,
                            display: 'flex',
                            padding: 4,
                        }}
                    >
                        <AIcon name="x" size={18} />
                    </button>
                </header>

                <div style={{ flex: 1, overflowY: 'auto' }}>
                    {items.length === 0 ? (
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                justifyContent: 'center',
                                gap: 10,
                                height: '100%',
                                color: C.faint,
                                padding: 40,
                                textAlign: 'center',
                            }}
                        >
                            <AIcon name="bell-off" size={30} color={C.faint} />
                            <span style={{ fontSize: 13 }}>
                                Belum ada notifikasi
                            </span>
                        </div>
                    ) : (
                        items.map((n) => {
                            const v = visual(n.type);

                            return (
                                <button
                                    key={n.id}
                                    onClick={() => markRead(n.id, n.is_read)}
                                    style={{
                                        display: 'flex',
                                        gap: 12,
                                        width: '100%',
                                        textAlign: 'left',
                                        padding: '14px 18px',
                                        border: 'none',
                                        borderBottom: `1px solid ${C.border}`,
                                        background: n.is_read
                                            ? '#fff'
                                            : 'rgba(47,84,201,.04)',
                                        cursor: n.is_read ? 'default' : 'pointer',
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 36,
                                            height: 36,
                                            borderRadius: 10,
                                            background: v.bg,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            flex: 'none',
                                        }}
                                    >
                                        <AIcon
                                            name={v.icon}
                                            size={17}
                                            color={v.color}
                                        />
                                    </span>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 6,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    fontSize: 13,
                                                    fontWeight: 600,
                                                    color: C.text,
                                                }}
                                            >
                                                {n.title}
                                            </span>
                                            {!n.is_read && (
                                                <span
                                                    style={{
                                                        width: 7,
                                                        height: 7,
                                                        borderRadius: '50%',
                                                        background: C.red,
                                                        flex: 'none',
                                                    }}
                                                />
                                            )}
                                        </div>
                                        {n.body && (
                                            <p
                                                style={{
                                                    margin: '2px 0 0',
                                                    fontSize: 12,
                                                    lineHeight: 1.4,
                                                    color: C.faint,
                                                }}
                                            >
                                                {n.body}
                                            </p>
                                        )}
                                        <span
                                            style={{
                                                fontSize: 11,
                                                color: C.faint,
                                                opacity: 0.8,
                                            }}
                                        >
                                            {timeAgo(n.created_at)}
                                        </span>
                                    </div>
                                </button>
                            );
                        })
                    )}
                </div>
            </aside>
        </>
    );
}
