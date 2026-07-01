import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { PropsWithChildren } from 'react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, NAV   } from '@/lib/avana';
import type {NavGroup, NavItem} from '@/lib/avana';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

type AuthUser = { name?: string; email?: string };

function AvanaFonts() {
    return (
        <Head>
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
        </Head>
    );
}

export default function AvanaLayout({ children }: PropsWithChildren) {
    const page = usePage<{
        auth?: { user?: AuthUser; tenant?: { id: number; name: string } };
        nav?: NavGroup[];
        superAdminView?: { is_super: boolean; view_tenant_id: string; tenants: { id: number; name: string }[] };
    }>();
    const url = page.url;
    const user = page.props.auth?.user;
    const navGroups = page.props.nav?.length ? page.props.nav : NAV;
    const sav = page.props.superAdminView;

    const switchTenant = (id: string) =>
        router.post('/avana/view-tenant', { tenant_id: id }, { preserveScroll: false });
    const userName = user?.name ?? 'Rina Anggraeni';
    const userInitials =
        userName
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]?.toUpperCase())
            .join('') || 'A';
    const [collapsed, setCollapsed] = useState(false);
    const [mobileNav, setMobileNav] = useState(false);
    const [openMenus, setOpenMenus] = useState<Record<string, boolean>>({});

    const handleLogout = () => router.flushAll();

    const isActive = (href: string) => url === href || url.startsWith(href + '/') || (href === '/avana/employees' && url.startsWith('/avana/employees'));

    const toggleSidebar = () => {
        if (typeof window !== 'undefined' && window.innerWidth <= 860) {
            setMobileNav((v) => !v);
        } else {
            setCollapsed((v) => !v);
        }
    };

    return (
        <div style={{ display: 'flex', minHeight: '100vh', background: C.surface, fontFamily: "'Poppins',system-ui,sans-serif", color: C.text }}>
            <AvanaFonts />

            {/* SIDEBAR */}
            <aside
                className={`avn-sidebar ${mobileNav ? 'avn-open' : ''}`}
                style={{
                    width: collapsed ? 76 : 248,
                    background: '#fff',
                    borderRight: `1px solid ${C.border}`,
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
                <div style={{ height: 64, display: 'flex', alignItems: 'center', padding: '0 18px', borderBottom: `1px solid ${C.border}`, gap: 10, flex: 'none' }}>
                    {collapsed ? (
                        <img src="/avana/logo-mark.png" alt="AvanaHR" style={{ height: 30, width: 'auto' }} />
                    ) : (
                        <img src="/avana/logo-full.png" alt="AvanaHR" style={{ height: 24 }} />
                    )}
                </div>

                <nav style={{ flex: 1, overflowY: 'auto', padding: '14px 12px' }}>
                    {navGroups.map((grp, gi) => (
                        <div key={gi} style={{ marginBottom: 6 }}>
                            {grp.title && (
                                <div
                                    style={{
                                        fontSize: 10.5,
                                        fontWeight: 600,
                                        letterSpacing: '.06em',
                                        color: C.faint,
                                        padding: collapsed ? '8px 0 4px' : '10px 12px 4px',
                                        textAlign: collapsed ? 'center' : 'left',
                                    }}
                                >
                                    {grp.title}
                                </div>
                            )}
                            {grp.items.map((it) => {
                                const leafLink = (item: NavItem, nested: boolean) => {
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
                                                padding: collapsed ? 0 : nested ? '0 12px 0 34px' : '0 12px',
                                                justifyContent: collapsed ? 'center' : 'flex-start',
                                                height: nested ? 38 : 42,
                                                marginBottom: 3,
                                                borderRadius: 9,
                                                cursor: 'pointer',
                                                fontSize: nested ? 13 : 13.5,
                                                textDecoration: 'none',
                                                fontWeight: active ? 600 : 500,
                                                color: active ? C.primary : '#5B6472',
                                                background: active ? 'rgba(47,84,201,.09)' : 'transparent',
                                            }}
                                        >
                                            {nested ? (
                                                <span style={{ width: 16, display: 'flex', justifyContent: 'center', fontSize: 16, lineHeight: 1, color: active ? C.primary : C.faint }}>›</span>
                                            ) : (
                                                <AIcon name={item.icon} size={18} />
                                            )}
                                            {!collapsed && <span style={{ whiteSpace: 'nowrap' }}>{item.label}</span>}
                                        </Link>
                                    );
                                };

                                if (!it.children) {
                                    return leafLink(it, false);
                                }

                                // Collapsed rail: flatten children to icon-only links.
                                if (collapsed) {
                                    return <div key={it.id}>{it.children.map((c) => leafLink(c, false))}</div>;
                                }

                                const hasActiveChild = it.children.some((c) => isActive(c.href ?? '##'));
                                const open = openMenus[it.id] ?? hasActiveChild;

                                return (
                                    <div key={it.id}>
                                        <button
                                            type="button"
                                            onClick={() => setOpenMenus((m) => ({ ...m, [it.id]: !open }))}
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
                                                fontWeight: hasActiveChild ? 600 : 500,
                                                color: hasActiveChild ? C.primary : '#5B6472',
                                                background: 'transparent',
                                            }}
                                        >
                                            <AIcon name={it.icon} size={18} />
                                            <span style={{ whiteSpace: 'nowrap', flex: 1, textAlign: 'left' }}>{it.label}</span>
                                            <AIcon name={open ? 'chevron-down' : 'chevron-right'} size={15} />
                                        </button>
                                        {open && it.children.map((c) => leafLink(c, true))}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </nav>

                <div style={{ padding: '14px 12px', borderTop: `1px solid ${C.border}`, flex: 'none' }}>
                    {collapsed ? (
                        <a
                            href="mailto:support@avanahr.co.id"
                            title="Hubungi support"
                            style={{ width: '100%', height: 40, display: 'flex', alignItems: 'center', justifyContent: 'center', background: C.surface, borderRadius: 9, color: C.primary, textDecoration: 'none' }}
                        >
                            <AIcon name="life-buoy" size={19} />
                        </a>
                    ) : (
                        <div style={{ background: C.surface, borderRadius: 10, padding: '13px 14px' }}>
                            <div style={{ fontSize: 11, fontWeight: 600, color: C.faint, letterSpacing: '.04em', textTransform: 'uppercase', marginBottom: 9 }}>Butuh Bantuan?</div>
                            <a href="mailto:support@avanahr.co.id" style={{ display: 'flex', alignItems: 'center', gap: 9, textDecoration: 'none', color: C.text, marginBottom: 8 }}>
                                <AIcon name="mail" size={15} color={C.primary} />
                                <span style={{ fontSize: 12.5 }}>support@avanahr.co.id</span>
                            </a>
                            <a href="tel:+622150999000" style={{ display: 'flex', alignItems: 'center', gap: 9, textDecoration: 'none', color: C.text }}>
                                <AIcon name="phone" size={15} color={C.primary} />
                                <span style={{ fontSize: 12.5 }}>(021) 5099-9000</span>
                            </a>
                        </div>
                    )}
                </div>
            </aside>

            {mobileNav && <div onClick={() => setMobileNav(false)} style={{ position: 'fixed', inset: 0, background: 'rgba(14,26,58,.45)', zIndex: 55 }} />}

            {/* MAIN */}
            <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
                {/* TOPBAR */}
                <header style={{ height: 64, background: '#fff', borderBottom: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', gap: 16, padding: '0 24px', position: 'sticky', top: 0, zIndex: 30 }}>
                    <button
                        onClick={toggleSidebar}
                        style={{ width: 38, height: 38, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', color: C.text, flex: 'none' }}
                    >
                        <AIcon name="panel-left" size={18} />
                    </button>
                    <div style={{ position: 'relative', flex: 1, maxWidth: 420 }} className="avn-search">
                        <span style={{ position: 'absolute', left: 13, top: '50%', transform: 'translateY(-50%)', color: C.faint, display: 'flex' }}>
                            <AIcon name="search" size={17} />
                        </span>
                        <input
                            placeholder="Cari karyawan, dokumen, menu…"
                            style={{ width: '100%', height: 40, padding: '0 14px 0 40px', background: C.surface, border: '1px solid transparent', borderRadius: 8, fontSize: 13.5, outline: 'none' }}
                        />
                    </div>
                    <div style={{ flex: 1 }} />
                    {sav?.is_super && sav.tenants.length > 0 && (
                        <div
                            style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '4px 4px 4px 10px', border: `1px solid ${sav.view_tenant_id ? '#F59E0B' : C.border}`, borderRadius: 9, background: sav.view_tenant_id ? '#FFFBEB' : '#fff' }}
                            title="Lihat data sebagai tenant (Super Admin)"
                        >
                            <AIcon name="eye" size={15} color={sav.view_tenant_id ? '#B45309' : C.faint} />
                            <SearchableSelect
                                value={sav.view_tenant_id}
                                onChange={switchTenant}
                                options={[
                                    { value: '', label: '— Tenant Saya —' },
                                    ...sav.tenants.map((t) => ({ value: String(t.id), label: t.name })),
                                ]}
                                placeholder="Lihat tenant…"
                                searchPlaceholder="Cari tenant…"
                                style={{ width: 200 }}
                            />
                        </div>
                    )}
                    <button style={{ position: 'relative', width: 40, height: 40, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', color: C.text }}>
                        <AIcon name="bell" size={18} />
                        <span style={{ position: 'absolute', top: 7, right: 7, width: 7, height: 7, background: C.red, borderRadius: '50%', border: '1.5px solid #fff' }} />
                    </button>
                    <div style={{ width: 1, height: 30, background: C.border, margin: '0 2px' }} />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '5px 8px 5px 5px', borderRadius: 10, cursor: 'pointer', border: 'none', background: 'none' }}>
                                <div style={{ width: 36, height: 36, borderRadius: 9, background: 'linear-gradient(135deg,#2F54C9,#6E9BE6)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 600, fontSize: 14 }}>{userInitials}</div>
                                <div className="avn-usermeta" style={{ lineHeight: 1.25, textAlign: 'left' }}>
                                    <div style={{ fontSize: 13, fontWeight: 600, color: C.navy }}>{userName}</div>
                                    <div style={{ fontSize: 11.5, color: C.muted }}>{page.props.auth?.tenant?.name ?? 'PT Nusantara Jaya'}</div>
                                </div>
                                <AIcon name="chevron-down" size={16} color={C.faint} />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuItem asChild>
                                <Link href={editProfile()} className="cursor-pointer">
                                    <AIcon name="settings" size={15} />
                                    Pengaturan
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href={logout()} as="button" onClick={handleLogout} className="w-full cursor-pointer text-[#DC2626]">
                                    <AIcon name="log-out" size={15} color={C.red} />
                                    Keluar
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>

                {/* CONTENT */}
                <main style={{ flex: 1, overflowY: 'auto' }}>{children}</main>
            </div>
        </div>
    );
}
