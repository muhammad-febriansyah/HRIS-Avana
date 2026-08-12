import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { PropsWithChildren } from 'react';
import type { CSSProperties } from 'react';
import WebsiteSettingController from '@/actions/App/Http/Controllers/Avana/WebsiteSettingController';
import { GlobalSearch } from '@/components/avana-ui/global-search';
import { NotificationSheet } from '@/components/avana-ui/notification-sheet';
import type { NotificationItem } from '@/components/avana-ui/notification-sheet';
import { WaIcon } from '@/components/avana-ui/wa-icon';
import { SearchableSelect } from '@/components/searchable-select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AIcon, C, hexA, NAV } from '@/lib/avana';
import type { NavGroup, NavItem } from '@/lib/avana';

/** The five configurable theme colours (per-tenant). */
type ThemeColors = {
    sidebar_bg: string;
    sidebar_text: string;
    sidebar_accent: string;
    topbar_bg: string;
    topbar_text: string;
};

const THEME_FALLBACK: ThemeColors = {
    sidebar_bg: '#FFFFFF',
    sidebar_text: '#5B6472',
    sidebar_accent: '#2F54C9',
    topbar_bg: '#FFFFFF',
    topbar_text: '#1A2333',
};

/** Build the CSS custom properties (colours + derived tints) from a theme. */
function themeVars(theme?: Partial<ThemeColors>): CSSProperties {
    const t = { ...THEME_FALLBACK, ...(theme ?? {}) };

    return {
        '--avn-sidebar-bg': t.sidebar_bg,
        '--avn-sidebar-text': t.sidebar_text,
        '--avn-sidebar-muted': hexA(t.sidebar_text, 0.7),
        '--avn-sidebar-border': hexA(t.sidebar_text, 0.16),
        '--avn-sidebar-soft': hexA(t.sidebar_text, 0.07),
        '--avn-sidebar-accent': t.sidebar_accent,
        '--avn-sidebar-active-bg': hexA(t.sidebar_accent, 0.14),
        '--avn-topbar-bg': t.topbar_bg,
        '--avn-topbar-text': t.topbar_text,
        '--avn-topbar-muted': hexA(t.topbar_text, 0.65),
        '--avn-topbar-border': hexA(t.topbar_text, 0.14),
        '--avn-topbar-soft': hexA(t.topbar_text, 0.06),
    } as CSSProperties;
}
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

type AuthUser = { name?: string; email?: string };

/**
 * Shared by `HandleInertiaRequests` only while the tenant's subscription is
 * inside the warning window (or already past its end date); null otherwise.
 */
type SubscriptionNotice = {
    end_date: string;
    end_date_label: string;
    days_left: number;
    level: 'expired' | 'critical' | 'warning';
    package: string | null;
};

const NOTICE_TONE: Record<
    SubscriptionNotice['level'],
    { bg: string; border: string; color: string; icon: string }
> = {
    expired: {
        bg: 'rgba(220,38,38,.09)',
        border: 'rgba(220,38,38,.28)',
        color: '#B91C1C',
        icon: 'circle-alert',
    },
    critical: {
        bg: 'rgba(220,38,38,.07)',
        border: 'rgba(220,38,38,.22)',
        color: '#DC2626',
        icon: 'triangle-alert',
    },
    warning: {
        bg: 'rgba(217,119,6,.09)',
        border: 'rgba(217,119,6,.25)',
        color: '#B45309',
        icon: 'clock',
    },
};

/** How long is left, phrased the way the notification phrases it. */
function countdownLabel(daysLeft: number): string {
    if (daysLeft < 0) {
        return 'sudah berakhir';
    }

    if (daysLeft === 0) {
        return 'berakhir hari ini';
    }

    if (daysLeft === 1) {
        return 'berakhir besok';
    }

    return `berakhir ${daysLeft} hari lagi`;
}

/**
 * Card above the page content warning a client that their subscription is
 * running out, with the two ways to act on it: renew and pay, or ask on
 * WhatsApp. Only warns — nothing in the app is blocked when it lapses.
 */
function SubscriptionBanner({
    notice,
    waHref,
}: {
    notice: SubscriptionNotice;
    waHref: string;
}) {
    const tone = NOTICE_TONE[notice.level] ?? NOTICE_TONE.warning;

    return (
        <div
            role="status"
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                flexWrap: 'wrap',
                margin: '18px 28px 0',
                padding: '13px 16px',
                background: tone.bg,
                border: `1px solid ${tone.border}`,
                borderRadius: 12,
                fontSize: 13,
                color: tone.color,
            }}
        >
            <span
                style={{
                    width: 32,
                    height: 32,
                    flex: 'none',
                    borderRadius: 9,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: '#fff',
                    border: `1px solid ${tone.border}`,
                }}
            >
                <AIcon name={tone.icon} size={16} color={tone.color} />
            </span>
            <span style={{ display: 'grid', gap: 2, minWidth: 240 }}>
                <span style={{ fontWeight: 600 }}>
                    Langganan{notice.package ? ` ${notice.package}` : ''}{' '}
                    {countdownLabel(notice.days_left)}
                </span>
                <span style={{ color: hexA(tone.color, 0.8) }}>
                    Masa aktif sampai {notice.end_date_label}. Perpanjang
                    sekarang agar layanan tidak terganggu.
                </span>
            </span>
            <span
                style={{
                    marginLeft: 'auto',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    flexWrap: 'wrap',
                }}
            >
                <a
                    href={waHref}
                    target="_blank"
                    rel="noreferrer"
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 7,
                        height: 36,
                        padding: '0 13px',
                        borderRadius: 9,
                        border: '1px solid rgba(37,211,102,.4)',
                        background: '#fff',
                        color: '#128C7E',
                        fontWeight: 600,
                        textDecoration: 'none',
                    }}
                >
                    <WaIcon size={15} color="#25D366" />
                    Tanya via WhatsApp
                </a>
                <Link
                    href="/avana/langganan"
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 7,
                        height: 36,
                        padding: '0 14px',
                        borderRadius: 9,
                        border: 'none',
                        background: tone.color,
                        color: '#fff',
                        fontWeight: 600,
                        textDecoration: 'none',
                    }}
                >
                    <AIcon name="refresh-cw" size={14} color="#fff" />
                    Perpanjang
                </Link>
            </span>
        </div>
    );
}

/** A single contact row in the sidebar "Butuh Bantuan?" card. */
function HelpRow({
    href,
    icon,
    label,
    value,
    external = false,
}: {
    href: string;
    icon: string;
    label: string;
    value: string;
    external?: boolean;
}) {
    return (
        <a
            href={href}
            {...(external ? { target: '_blank', rel: 'noreferrer' } : {})}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '6px 6px',
                borderRadius: 8,
                textDecoration: 'none',
                color: 'var(--avn-sidebar-text)',
            }}
            onMouseEnter={(e) => {
                e.currentTarget.style.background =
                    'var(--avn-sidebar-active-bg)';
            }}
            onMouseLeave={(e) => {
                e.currentTarget.style.background = 'transparent';
            }}
        >
            <span
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    width: 28,
                    height: 28,
                    borderRadius: 8,
                    background: 'var(--avn-sidebar-active-bg)',
                    flex: 'none',
                }}
            >
                <AIcon
                    name={icon}
                    size={14}
                    color="var(--avn-sidebar-accent)"
                />
            </span>
            <span
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    minWidth: 0,
                    lineHeight: 1.3,
                }}
            >
                <span
                    style={{
                        fontSize: 10,
                        fontWeight: 600,
                        color: 'var(--avn-sidebar-muted)',
                        letterSpacing: '.03em',
                        textTransform: 'uppercase',
                    }}
                >
                    {label}
                </span>
                <span
                    style={{
                        fontSize: 12.5,
                        fontWeight: 500,
                        color: 'var(--avn-sidebar-text)',
                        overflowWrap: 'anywhere',
                    }}
                >
                    {value}
                </span>
            </span>
        </a>
    );
}

function AvanaFonts() {
    return (
        <Head>
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link
                rel="preconnect"
                href="https://fonts.gstatic.com"
                crossOrigin=""
            />
            <link
                href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
                rel="stylesheet"
            />
        </Head>
    );
}

export default function AvanaLayout({ children }: PropsWithChildren) {
    const page = usePage<{
        auth?: {
            user?: AuthUser;
            avatar?: string | null;
            isSuperAdmin?: boolean;
            tenant?: {
                id: number;
                name: string;
                company_name?: string | null;
                logo_url?: string | null;
            };
        };
        nav?: NavGroup[];
        theme?: Partial<ThemeColors>;
        website?: { contact?: { phone?: string; whatsapp?: string } };
        superAdminView?: {
            is_super: boolean;
            view_tenant_id: string;
            tenants: { id: number; name: string }[];
        };
        notifications?: { items: NotificationItem[]; unread: number };
        subscriptionNotice?: SubscriptionNotice | null;
    }>();
    const vars = themeVars(page.props.theme);
    const url = page.url;
    const user = page.props.auth?.user;
    const avatar = page.props.auth?.avatar;
    const navGroups = page.props.nav?.length ? page.props.nav : NAV;
    const sav = page.props.superAdminView;

    // Support contact: DB-driven (website settings) with sensible fallbacks.
    const contact = page.props.website?.contact;
    const helpPhone = contact?.phone || '(021) 5099-9000';
    const helpWhatsapp = contact?.whatsapp || helpPhone;
    const waHref = `https://wa.me/${helpWhatsapp.replace(/[^\d]/g, '')}`;

    const switchTenant = (id: string) =>
        router.post(
            '/avana/view-tenant',
            { tenant_id: id },
            { preserveScroll: false },
        );
    const userName = user?.name ?? 'Rina Anggraeni';
    const userInitials =
        userName
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]?.toUpperCase())
            .join('') || 'A';
    // White-label: a non-platform tenant with an uploaded company logo shows
    // their own brand in the sidebar; super admins keep the AvanaHR mark.
    const tenantLogo = page.props.auth?.tenant?.logo_url;
    const useTenantLogo = !page.props.auth?.isSuperAdmin && Boolean(tenantLogo);
    const brandName = page.props.auth?.tenant?.company_name || 'AvanaHR';
    const notif = page.props.notifications ?? { items: [], unread: 0 };
    const subscriptionNotice = page.props.subscriptionNotice ?? null;
    const [notifOpen, setNotifOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [mobileNav, setMobileNav] = useState(false);
    const [openMenus, setOpenMenus] = useState<Record<string, boolean>>({});

    const handleLogout = () => router.flushAll();

    // Resolve a single active item: the nav link whose href is the longest
    // prefix of the current URL. Prevents a parent leaf (e.g. /avana/absensi)
    // from also lighting up on a child route (/avana/absensi/monitor).
    const activeHref = useMemo(() => {
        const hrefs: string[] = [];

        for (const grp of navGroups) {
            for (const it of grp.items) {
                if (it.children) {
                    for (const c of it.children) {
                        if (c.href) {
                            hrefs.push(c.href);
                        }
                    }
                } else if (it.href) {
                    hrefs.push(it.href);
                }
            }
        }

        // Match on the pathname only — a query string (e.g. the monitor's
        // ?date_from=…) must not stop /avana/absensi/monitor from matching and
        // let the shorter /avana/absensi parent win instead.
        const path = url.split('?')[0];

        return hrefs
            .filter((h) => path === h || path.startsWith(h + '/'))
            .sort((a, b) => b.length - a.length)[0];
    }, [url, navGroups]);

    const isActive = (href: string) => href === activeHref;

    const toggleSidebar = () => {
        if (typeof window !== 'undefined' && window.innerWidth <= 860) {
            setMobileNav((v) => !v);
        } else {
            setCollapsed((v) => !v);
        }
    };

    return (
        <div
            style={{
                ...vars,
                display: 'flex',
                minHeight: '100vh',
                background: C.surface,
                fontFamily: "'Poppins',system-ui,sans-serif",
                color: C.text,
            }}
        >
            <AvanaFonts />

            {/* SIDEBAR */}
            <aside
                className={`avn-sidebar ${mobileNav ? 'avn-open' : ''}`}
                style={{
                    width: collapsed ? 76 : 248,
                    background: 'var(--avn-sidebar-bg)',
                    borderRight: '1px solid var(--avn-sidebar-border)',
                    display: 'flex',
                    flexDirection: 'column',
                    flex: 'none',
                    transition: 'width .2s',
                    position: 'sticky',
                    top: 0,
                    height: '100vh',
                    zIndex: 40,
                }}
            >
                <div
                    style={{
                        height: 64,
                        display: 'flex',
                        alignItems: 'center',
                        padding: '0 18px',
                        borderBottom: '1px solid var(--avn-sidebar-border)',
                        gap: 10,
                        flex: 'none',
                    }}
                >
                    {useTenantLogo ? (
                        <img
                            src={tenantLogo ?? undefined}
                            alt={brandName}
                            style={{
                                height: collapsed ? 32 : 34,
                                width: 'auto',
                                maxWidth: collapsed ? 44 : 168,
                                objectFit: 'contain',
                            }}
                        />
                    ) : collapsed ? (
                        <img
                            src="/avana/logo-mark.png"
                            alt="AvanaHR"
                            style={{ height: 30, width: 'auto' }}
                        />
                    ) : (
                        <img
                            src="/avana/logo-full.png"
                            alt="AvanaHR"
                            style={{ height: 24 }}
                        />
                    )}
                </div>

                <nav
                    style={{ flex: 1, overflowY: 'auto', padding: '14px 12px' }}
                >
                    {navGroups.map((grp, gi) => (
                        <div key={gi} style={{ marginBottom: 6 }}>
                            {grp.title && (
                                // Sticky: the sidebar scrolls, and a header that
                                // scrolls away leaves the rows under it looking
                                // like they belong to whatever section is still
                                // on screen — the reason "Slip Gaji / Dokumen"
                                // read as stray management menus.
                                <div
                                    style={{
                                        position: 'sticky',
                                        top: -14,
                                        zIndex: 1,
                                        background: 'var(--avn-sidebar-bg)',
                                        fontSize: 10.5,
                                        fontWeight: 600,
                                        letterSpacing: '.06em',
                                        color: 'var(--avn-sidebar-muted)',
                                        padding: collapsed
                                            ? '8px 0 4px'
                                            : '10px 12px 4px',
                                        textAlign: collapsed
                                            ? 'center'
                                            : 'left',
                                    }}
                                >
                                    {grp.title}
                                </div>
                            )}
                            {grp.items.map((it) => {
                                const leafLink = (
                                    item: NavItem,
                                    nested: boolean,
                                ) => {
                                    const active = isActive(item.href ?? '##');

                                    return (
                                        <Link
                                            key={item.id}
                                            href={item.href ?? '#'}
                                            onClick={() => setMobileNav(false)}
                                            title={item.label}
                                            style={{
                                                width: '100%',
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 12,
                                                padding: collapsed
                                                    ? 0
                                                    : nested
                                                      ? '0 12px 0 34px'
                                                      : '0 12px',
                                                justifyContent: collapsed
                                                    ? 'center'
                                                    : 'flex-start',
                                                height: nested ? 38 : 42,
                                                marginBottom: 3,
                                                borderRadius: 9,
                                                cursor: 'pointer',
                                                fontSize: nested ? 13 : 13.5,
                                                textDecoration: 'none',
                                                fontWeight: active ? 600 : 500,
                                                color: active
                                                    ? 'var(--avn-sidebar-accent)'
                                                    : 'var(--avn-sidebar-text)',
                                                background: active
                                                    ? 'var(--avn-sidebar-active-bg)'
                                                    : 'transparent',
                                            }}
                                        >
                                            {nested ? (
                                                <span
                                                    style={{
                                                        width: 16,
                                                        display: 'flex',
                                                        justifyContent:
                                                            'center',
                                                        fontSize: 16,
                                                        lineHeight: 1,
                                                        color: active
                                                            ? 'var(--avn-sidebar-accent)'
                                                            : 'var(--avn-sidebar-muted)',
                                                    }}
                                                >
                                                    ›
                                                </span>
                                            ) : (
                                                <AIcon
                                                    name={item.icon}
                                                    size={18}
                                                />
                                            )}
                                            {!collapsed && (
                                                <span
                                                    style={{
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    {item.label}
                                                </span>
                                            )}
                                        </Link>
                                    );
                                };

                                if (!it.children) {
                                    return leafLink(it, false);
                                }

                                // Collapsed rail: flatten children to icon-only links.
                                if (collapsed) {
                                    return (
                                        <div key={it.id}>
                                            {it.children.map((c) =>
                                                leafLink(c, false),
                                            )}
                                        </div>
                                    );
                                }

                                const hasActiveChild = it.children.some((c) =>
                                    isActive(c.href ?? '##'),
                                );
                                const open = openMenus[it.id] ?? hasActiveChild;

                                return (
                                    <div key={it.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setOpenMenus((m) => ({
                                                    ...m,
                                                    [it.id]: !open,
                                                }))
                                            }
                                            style={{
                                                width: '100%',
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 12,
                                                padding: '0 12px',
                                                height: 42,
                                                marginBottom: 3,
                                                borderRadius: 9,
                                                border: 'none',
                                                cursor: 'pointer',
                                                fontSize: 13.5,
                                                fontFamily: 'inherit',
                                                fontWeight: hasActiveChild
                                                    ? 600
                                                    : 500,
                                                color: hasActiveChild
                                                    ? 'var(--avn-sidebar-accent)'
                                                    : 'var(--avn-sidebar-text)',
                                                background: 'transparent',
                                            }}
                                        >
                                            <AIcon name={it.icon} size={18} />
                                            <span
                                                style={{
                                                    whiteSpace: 'nowrap',
                                                    flex: 1,
                                                    textAlign: 'left',
                                                }}
                                            >
                                                {it.label}
                                            </span>
                                            <AIcon
                                                name={
                                                    open
                                                        ? 'chevron-down'
                                                        : 'chevron-right'
                                                }
                                                size={15}
                                            />
                                        </button>
                                        {open &&
                                            it.children.map((c) =>
                                                leafLink(c, true),
                                            )}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </nav>

                <div
                    style={{
                        padding: '14px 12px',
                        borderTop: '1px solid var(--avn-sidebar-border)',
                        flex: 'none',
                    }}
                >
                    {collapsed ? (
                        <a
                            href={waHref}
                            target="_blank"
                            rel="noreferrer"
                            title="Hubungi support via WhatsApp"
                            style={{
                                width: '100%',
                                height: 40,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: 'var(--avn-sidebar-soft)',
                                borderRadius: 9,
                                color: 'var(--avn-sidebar-accent)',
                                textDecoration: 'none',
                            }}
                        >
                            <AIcon name="life-buoy" size={19} />
                        </a>
                    ) : (
                        <div
                            style={{
                                background: 'var(--avn-sidebar-soft)',
                                border: '1px solid var(--avn-sidebar-border)',
                                borderRadius: 12,
                                padding: 12,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    marginBottom: 10,
                                }}
                            >
                                <span
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        width: 26,
                                        height: 26,
                                        borderRadius: 8,
                                        background:
                                            'var(--avn-sidebar-active-bg)',
                                        flex: 'none',
                                    }}
                                >
                                    <AIcon
                                        name="life-buoy"
                                        size={15}
                                        color="var(--avn-sidebar-accent)"
                                    />
                                </span>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        fontWeight: 700,
                                        color: 'var(--avn-sidebar-text)',
                                        letterSpacing: '.01em',
                                    }}
                                >
                                    Butuh Bantuan?
                                </span>
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 2,
                                }}
                            >
                                <HelpRow
                                    href={waHref}
                                    icon="message-circle"
                                    label="WhatsApp"
                                    value={helpWhatsapp}
                                    external
                                />
                            </div>
                        </div>
                    )}
                </div>
            </aside>

            {mobileNav && (
                <div
                    onClick={() => setMobileNav(false)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(14,26,58,.45)',
                        zIndex: 55,
                    }}
                />
            )}

            {/* MAIN */}
            <div
                style={{
                    flex: 1,
                    minWidth: 0,
                    display: 'flex',
                    flexDirection: 'column',
                }}
            >
                {/* TOPBAR */}
                <header
                    style={{
                        height: 64,
                        background: 'var(--avn-topbar-bg)',
                        borderBottom: '1px solid var(--avn-topbar-border)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 16,
                        padding: '0 24px',
                        position: 'sticky',
                        top: 0,
                        zIndex: 30,
                    }}
                >
                    <button
                        onClick={toggleSidebar}
                        style={{
                            width: 38,
                            height: 38,
                            border: '1px solid var(--avn-topbar-border)',
                            background: 'var(--avn-topbar-soft)',
                            borderRadius: 8,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            cursor: 'pointer',
                            color: 'var(--avn-topbar-text)',
                            flex: 'none',
                        }}
                    >
                        <AIcon name="panel-left" size={18} />
                    </button>
                    <GlobalSearch nav={navGroups} />
                    <div style={{ flex: 1 }} />
                    {sav?.is_super && sav.tenants.length > 0 && (
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                padding: '4px 4px 4px 10px',
                                border: `1px solid ${sav.view_tenant_id ? '#F59E0B' : C.border}`,
                                borderRadius: 9,
                                background: sav.view_tenant_id
                                    ? '#FFFBEB'
                                    : '#fff',
                            }}
                            title="Lihat data sebagai tenant (Super Admin)"
                        >
                            <AIcon
                                name="eye"
                                size={15}
                                color={sav.view_tenant_id ? '#B45309' : C.faint}
                            />
                            <SearchableSelect
                                value={sav.view_tenant_id}
                                onChange={switchTenant}
                                options={[
                                    { value: '', label: '— Tenant Saya —' },
                                    ...sav.tenants.map((t) => ({
                                        value: String(t.id),
                                        label: t.name,
                                    })),
                                ]}
                                placeholder="Lihat tenant…"
                                searchPlaceholder="Cari tenant…"
                                style={{ width: 200 }}
                            />
                        </div>
                    )}
                    <button
                        onClick={() => setNotifOpen(true)}
                        aria-label="Notifikasi"
                        style={{
                            position: 'relative',
                            width: 40,
                            height: 40,
                            border: '1px solid var(--avn-topbar-border)',
                            background: 'var(--avn-topbar-soft)',
                            borderRadius: 8,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            cursor: 'pointer',
                            color: 'var(--avn-topbar-text)',
                        }}
                    >
                        <AIcon name="bell" size={18} />
                        {notif.unread > 0 && (
                            <span
                                style={{
                                    position: 'absolute',
                                    top: -4,
                                    right: -4,
                                    minWidth: 16,
                                    height: 16,
                                    padding: '0 4px',
                                    fontSize: 10,
                                    fontWeight: 700,
                                    color: '#fff',
                                    background: C.red,
                                    borderRadius: 999,
                                    border: '1.5px solid #fff',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                {notif.unread > 9 ? '9+' : notif.unread}
                            </span>
                        )}
                    </button>
                    <div
                        style={{
                            width: 1,
                            height: 30,
                            background: 'var(--avn-topbar-border)',
                            margin: '0 2px',
                        }}
                    />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 11,
                                    padding: '5px 8px 5px 5px',
                                    borderRadius: 10,
                                    cursor: 'pointer',
                                    border: 'none',
                                    background: 'none',
                                }}
                            >
                                {avatar ? (
                                    <img
                                        src={avatar}
                                        alt={userName}
                                        style={{
                                            width: 36,
                                            height: 36,
                                            borderRadius: 9,
                                            objectFit: 'cover',
                                            flex: 'none',
                                        }}
                                    />
                                ) : (
                                    <div
                                        style={{
                                            width: 36,
                                            height: 36,
                                            borderRadius: 9,
                                            background:
                                                'linear-gradient(135deg,#2F54C9,#6E9BE6)',
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontWeight: 600,
                                            fontSize: 14,
                                        }}
                                    >
                                        {userInitials}
                                    </div>
                                )}
                                <div
                                    className="avn-usermeta"
                                    style={{
                                        lineHeight: 1.25,
                                        textAlign: 'left',
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 600,
                                            color: 'var(--avn-topbar-text)',
                                        }}
                                    >
                                        {userName}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--avn-topbar-muted)',
                                        }}
                                    >
                                        {page.props.auth?.tenant?.name ??
                                            'PT Nusantara Jaya'}
                                    </div>
                                </div>
                                <AIcon
                                    name="chevron-down"
                                    size={16}
                                    color="var(--avn-topbar-muted)"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuItem asChild>
                                <Link
                                    href={
                                        page.props.auth?.isSuperAdmin
                                            ? WebsiteSettingController.edit()
                                            : editProfile()
                                    }
                                    className="cursor-pointer"
                                >
                                    <AIcon name="settings" size={15} />
                                    {page.props.auth?.isSuperAdmin
                                        ? 'Pengaturan'
                                        : 'Edit Profil'}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link
                                    href={logout()}
                                    as="button"
                                    onClick={handleLogout}
                                    className="w-full cursor-pointer text-[#DC2626]"
                                >
                                    <AIcon
                                        name="log-out"
                                        size={15}
                                        color={C.red}
                                    />
                                    Keluar
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>

                {/* CONTENT */}
                <main style={{ flex: 1, overflowY: 'auto' }}>
                    {subscriptionNotice && (
                        <SubscriptionBanner
                            notice={subscriptionNotice}
                            waHref={waHref}
                        />
                    )}
                    {children}
                </main>
            </div>

            <NotificationSheet
                open={notifOpen}
                onClose={() => setNotifOpen(false)}
                items={notif.items}
                unread={notif.unread}
            />
        </div>
    );
}
