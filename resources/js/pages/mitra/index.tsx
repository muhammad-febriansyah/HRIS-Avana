import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Coins, Users, Wallet } from 'lucide-react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PortalController from '@/actions/App/Http/Controllers/Mitra/PortalController';
import { DataTable } from '@/components/avana-ui/data-table';
import type { DataTableColumn, DataTableMeta } from '@/components/avana-ui/data-table';
import { EmptyState } from '@/components/avana-ui/empty-state';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AvanaFonts } from '@/layouts/avana-layout';
import { AIcon, btnSave, C, card, RupiahInput, thCell } from '@/lib/avana';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

interface Partner {
    code: string;
    status: string;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_holder: string | null;
    npwp: string | null;
    has_bank: boolean;
}

interface Stats {
    clicks: number;
    leads: number;
    conversions: number;
    balance_amount: number;
    available_amount: number;
    pending_amount: number;
}

interface Settings {
    flat_amount: number;
    min_withdrawal_amount: number;
    hold_days: number;
    withdrawal_enabled: boolean;
    leads_tab_enabled: boolean;
    komisi_tab_enabled: boolean;
    rekening_tab_enabled: boolean;
}

interface LeadRow {
    id: number;
    company_name: string;
    contact_name: string;
    status: string;
    tenant_name: string | null;
    created_at: string | null;
}

interface ConversionRow {
    id: number;
    tenant_name: string | null;
    commission_amount: number;
    status: string;
    hold_until: string | null;
    created_at: string | null;
}

interface WithdrawalRow {
    id: number;
    amount: number;
    status: string;
    admin_note: string | null;
    proof_url: string | null;
    created_at: string | null;
}

interface PendingRegistration {
    id: number;
    company_name: string;
    industry: string | null;
    employee_count_range: string | null;
    created_at: string | null;
}

interface Paginated<T> {
    data: T[];
    meta: DataTableMeta;
    search: string;
}

interface PageProps {
    partner: Partner;
    stats: Stats;
    settings: Settings;
    referralUrl: string;
    recentConversions: ConversionRow[];
    pendingRegistrations: PendingRegistration[];
    leads: Paginated<LeadRow>;
    conversions: Paginated<ConversionRow>;
    withdrawals: Paginated<WithdrawalRow>;
    flash?: { success?: string; error?: string };
    errors: Record<string, string>;
    auth: { user: { name: string; email: string } | null };
    website?: { contact?: { phone?: string; whatsapp?: string } };
    [key: string]: unknown;
}

const TABS = [
    { key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard' },
    { key: 'leads', label: 'Leads', icon: 'users' },
    { key: 'komisi', label: 'Komisi', icon: 'coins' },
    { key: 'penarikan', label: 'Penarikan', icon: 'wallet' },
    { key: 'rekening', label: 'Rekening', icon: 'landmark' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

const TAB_DESCRIPTION: Record<TabKey, string> = {
    dashboard: 'Ringkasan performa referral Anda dan link unik untuk dibagikan.',
    leads: 'Perusahaan yang mendaftar lewat link referral Anda.',
    komisi: 'Riwayat komisi dari setiap klien yang berhasil dikonversi.',
    penarikan: 'Ajukan pencairan saldo komisi dan pantau statusnya.',
    rekening: 'Data rekening bank untuk pencairan komisi.',
};

function rp(n: number): string {
    return 'Rp' + Math.round(n).toLocaleString('id-ID');
}

type Tone = 'green' | 'amber' | 'red' | 'muted' | 'primary';

function Badge({ label, tone }: { label: string; tone: Tone }) {
    const map: Record<Tone, [string, string]> = {
        green: [C.green, 'rgba(22,163,74,.1)'],
        amber: [C.amber, 'rgba(217,119,6,.1)'],
        red: [C.red, 'rgba(220,38,38,.1)'],
        muted: [C.muted, 'rgba(107,114,128,.12)'],
        primary: [C.primary, 'rgba(47,84,201,.1)'],
    };
    const [color, bg] = map[tone];

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                padding: '3px 9px',
                borderRadius: 999,
                fontSize: 11.5,
                fontWeight: 700,
                color,
                background: bg,
            }}
        >
            {label}
        </span>
    );
}

const LEAD_STATUS: Record<string, { label: string; tone: Tone }> = {
    new: { label: 'Baru', tone: 'amber' },
    contacted: { label: 'Dihubungi', tone: 'primary' },
    converted: { label: 'Jadi Klien', tone: 'green' },
    lost: { label: 'Hilang', tone: 'muted' },
};

const CONVERSION_STATUS: Record<string, { label: string; tone: Tone }> = {
    pending: { label: 'Tertahan', tone: 'amber' },
    approved: { label: 'Disetujui', tone: 'green' },
    void: { label: 'Dibatalkan', tone: 'red' },
};

const WITHDRAWAL_STATUS: Record<string, { label: string; tone: Tone }> = {
    pending: { label: 'Menunggu', tone: 'amber' },
    approved: { label: 'Diproses', tone: 'primary' },
    paid: { label: 'Lunas', tone: 'green' },
    rejected: { label: 'Ditolak', tone: 'red' },
};

export default function MitraIndex({ partner, stats, settings, referralUrl, recentConversions, pendingRegistrations, leads, conversions, withdrawals }: PageProps) {
    const { flash, auth, website } = usePage<PageProps>().props;
    const user = auth.user;
    const [tab, setTab] = useState<TabKey>('dashboard');
    const [collapsed, setCollapsed] = useState(false);
    const [mobileNav, setMobileNav] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    useEffect(() => {
        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash?.error]);

    // Super admin's per-tab kill switches (Referral > Pengaturan Komisi).
    // Dashboard is mandatory — it carries the referral link.
    const TAB_VISIBLE: Record<TabKey, boolean> = {
        dashboard: true,
        leads: settings.leads_tab_enabled,
        komisi: settings.komisi_tab_enabled,
        penarikan: settings.withdrawal_enabled,
        rekening: settings.rekening_tab_enabled,
    };
    const visibleTabs = TABS.filter((t) => TAB_VISIBLE[t.key]);
    // The sidebar only ever offers visible tabs, so `tab` can't land on a
    // hidden one — except right after the admin turns one off mid-session,
    // which a fresh page load (settings come from server props) resolves.
    const activeTab = TAB_VISIBLE[tab] ? tab : 'dashboard';
    const activeLabel = TABS.find((t) => t.key === activeTab)?.label ?? 'Dashboard';

    const userName = user?.name ?? 'Mitra';
    const userInitials =
        userName
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]?.toUpperCase())
            .join('') || 'M';

    const helpWhatsapp = website?.contact?.whatsapp || website?.contact?.phone || '(021) 5099-9000';
    const waHref = `https://wa.me/${helpWhatsapp.replace(/[^\d]/g, '')}`;

    const handleLogout = () => router.flushAll();

    const toggleSidebar = () => {
        if (typeof window !== 'undefined' && window.innerWidth <= 860) {
            setMobileNav((v) => !v);
        } else {
            setCollapsed((v) => !v);
        }
    };

    const selectTab = (key: TabKey) => {
        setTab(key);
        setMobileNav(false);
    };

    return (
        <>
            <Head title="Dashboard Mitra" />
            <AvanaFonts />

            <div
                style={{
                    display: 'flex',
                    minHeight: '100dvh',
                    background: C.surface,
                    fontFamily: "'Poppins',system-ui,sans-serif",
                    color: C.text,
                }}
            >
                {/* SIDEBAR — same chrome as the super admin panel (AvanaLayout) */}
                <aside
                    className={`avn-sidebar ${mobileNav ? 'avn-open' : ''}`}
                    style={{
                        width: collapsed ? 76 : 248,
                        flexShrink: 0,
                        background: '#fff',
                        borderRight: `1px solid ${C.border}`,
                        display: 'flex',
                        flexDirection: 'column',
                        transition: 'width .2s',
                        position: 'sticky',
                        top: 0,
                        height: '100dvh',
                        zIndex: 40,
                    }}
                >
                    <div
                        style={{
                            height: 64,
                            display: 'flex',
                            alignItems: 'center',
                            padding: '0 18px',
                            borderBottom: `1px solid ${C.border}`,
                            flex: 'none',
                        }}
                    >
                        {collapsed ? (
                            <img src="/avana/logo-mark.png" alt="AvanaHR" style={{ height: 30, width: 'auto' }} />
                        ) : (
                            <img src="/avana/logo-full.png" alt="AvanaHR" style={{ height: 24 }} />
                        )}
                    </div>

                    <nav style={{ flex: 1, overflowY: 'auto', padding: '14px 12px' }}>
                        {!collapsed && (
                            <div
                                style={{
                                    fontSize: 10.5,
                                    fontWeight: 600,
                                    letterSpacing: '.06em',
                                    color: C.faint,
                                    padding: '10px 12px 4px',
                                    textTransform: 'uppercase',
                                }}
                            >
                                Mitra Referral
                            </div>
                        )}
                        {visibleTabs.map((t) => {
                            const active = activeTab === t.key;

                            return (
                                <button
                                    key={t.key}
                                    type="button"
                                    title={t.label}
                                    onClick={() => selectTab(t.key)}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        width: '100%',
                                        height: 42,
                                        padding: collapsed ? 0 : '0 12px',
                                        justifyContent: collapsed ? 'center' : 'flex-start',
                                        marginBottom: 3,
                                        border: 'none',
                                        borderRadius: 9,
                                        cursor: 'pointer',
                                        fontSize: 13.5,
                                        fontFamily: 'inherit',
                                        textAlign: 'left',
                                        fontWeight: active ? 600 : 500,
                                        color: active ? C.primary : C.text,
                                        background: active ? 'rgba(47,84,201,.14)' : 'transparent',
                                    }}
                                >
                                    <AIcon name={t.icon} size={18} color={active ? C.primary : C.muted} />
                                    {!collapsed && <span style={{ whiteSpace: 'nowrap' }}>{t.label}</span>}
                                </button>
                            );
                        })}
                    </nav>

                    <div style={{ padding: '14px 12px', borderTop: `1px solid ${C.border}`, flex: 'none' }}>
                        {collapsed ? (
                            <a
                                href={waHref}
                                target="_blank"
                                rel="noreferrer"
                                title="Hubungi support via WhatsApp"
                                aria-label="Hubungi support via WhatsApp"
                                style={{
                                    width: '100%',
                                    height: 40,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    background: C.surface,
                                    borderRadius: 9,
                                    color: C.primary,
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon name="life-buoy" size={19} />
                            </a>
                        ) : (
                            <div style={{ display: 'grid', gap: 10 }}>
                                <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 12, padding: 12 }}>
                                    <div style={{ fontWeight: 700, color: C.text, marginBottom: 2 }}>{partner.code}</div>
                                    <div style={{ fontSize: 11.5, color: C.muted }}>Kode referral Anda</div>
                                </div>

                                <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 12, padding: 12 }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 10 }}>
                                        <span
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                width: 26,
                                                height: 26,
                                                borderRadius: 8,
                                                background: 'rgba(47,84,201,.14)',
                                                flex: 'none',
                                            }}
                                        >
                                            <AIcon name="life-buoy" size={15} color={C.primary} />
                                        </span>
                                        <span style={{ fontSize: 12.5, fontWeight: 700, color: C.text, letterSpacing: '.01em' }}>Butuh Bantuan?</span>
                                    </div>
                                    <a
                                        href={waHref}
                                        target="_blank"
                                        rel="noreferrer"
                                        style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '6px 6px', borderRadius: 8, textDecoration: 'none' }}
                                    >
                                        <span
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                width: 28,
                                                height: 28,
                                                borderRadius: 8,
                                                background: 'rgba(47,84,201,.14)',
                                                flex: 'none',
                                            }}
                                        >
                                            <AIcon name="message-circle" size={14} color={C.primary} />
                                        </span>
                                        <span style={{ display: 'flex', flexDirection: 'column', minWidth: 0, lineHeight: 1.3 }}>
                                            <span style={{ fontSize: 10, fontWeight: 600, color: C.muted, letterSpacing: '.03em', textTransform: 'uppercase' }}>
                                                WhatsApp
                                            </span>
                                            <span style={{ fontSize: 12.5, fontWeight: 500, color: C.text, overflowWrap: 'anywhere' }}>{helpWhatsapp}</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        )}
                    </div>
                </aside>

                {mobileNav && (
                    <div
                        onClick={() => setMobileNav(false)}
                        style={{ position: 'fixed', inset: 0, background: 'rgba(14,26,58,.45)', zIndex: 55 }}
                    />
                )}

                {/* MAIN */}
                <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
                    {/* TOPBAR */}
                    <header
                        style={{
                            height: 64,
                            background: '#fff',
                            borderBottom: `1px solid ${C.border}`,
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
                            type="button"
                            onClick={toggleSidebar}
                            aria-label={collapsed || mobileNav ? 'Buka sidebar' : 'Tutup sidebar'}
                            aria-expanded={!collapsed}
                            style={{
                                width: 38,
                                height: 38,
                                border: `1px solid ${C.border}`,
                                background: C.surface,
                                borderRadius: 8,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                cursor: 'pointer',
                                color: C.text,
                                flex: 'none',
                            }}
                        >
                            <AIcon name="panel-left" size={18} />
                        </button>
                        <div style={{ flex: 1 }} />

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 11,
                                        padding: '5px 8px 5px 5px',
                                        borderRadius: 10,
                                        cursor: 'pointer',
                                        border: 'none',
                                        background: 'none',
                                        fontFamily: 'inherit',
                                    }}
                                >
                                    <div
                                        style={{
                                            width: 36,
                                            height: 36,
                                            borderRadius: 9,
                                            background: 'linear-gradient(135deg,#2F54C9,#6E9BE6)',
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontWeight: 600,
                                            fontSize: 14,
                                            flex: 'none',
                                        }}
                                    >
                                        {userInitials}
                                    </div>
                                    <div className="avn-usermeta" style={{ lineHeight: 1.25, textAlign: 'left' }}>
                                        <div style={{ fontSize: 13, fontWeight: 600, color: C.text }}>{userName}</div>
                                        <div style={{ fontSize: 11.5, color: C.muted }}>{user?.email}</div>
                                    </div>
                                    <AIcon name="chevron-down" size={16} color={C.muted} />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                <DropdownMenuItem asChild>
                                    <Link href={editProfile()} className="cursor-pointer">
                                        <AIcon name="settings" size={15} />
                                        Edit Profil
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

                    <main style={{ flex: 1, overflowY: 'auto', padding: '24px 28px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                            <span>Mitra Referral</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>{activeLabel}</span>
                        </div>
                        <h1 style={{ fontSize: 20, fontWeight: 800, color: C.navy }}>{activeLabel}</h1>
                        <p style={{ fontSize: 13, color: C.muted, marginTop: 4, marginBottom: 18 }}>{TAB_DESCRIPTION[activeTab]}</p>

                        {partner.status === 'suspended' && (
                            <div style={{ ...card, padding: '12px 16px', borderLeft: `3px solid ${C.red}`, background: '#FEF2F2', marginBottom: 16 }}>
                                <strong style={{ color: C.red }}>Akun mitra Anda sedang ditangguhkan.</strong> Hubungi tim AvanaHR untuk info lebih lanjut.
                            </div>
                        )}

                        {activeTab === 'dashboard' && (
                            <div style={{ display: 'grid', gap: 16 }}>
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
                                        gap: 12,
                                    }}
                                >
                                    <StatCard label="Klik Link" value={stats.clicks.toLocaleString('id-ID')} icon="mouse-pointer-click" tone="sky" />
                                    <StatCard label="Leads" value={stats.leads.toLocaleString('id-ID')} icon="users" tone="primary" />
                                    <StatCard label="Saldo Komisi" value={rp(stats.balance_amount)} icon="coins" tone="amber" />
                                    <StatCard label="Tersedia Ditarik" value={rp(stats.available_amount)} icon="wallet" tone="green" />
                                </div>

                                <ReferralLinkBox referralUrl={referralUrl} />

                                <DashboardTab stats={stats} settings={settings} recentConversions={recentConversions} pendingRegistrations={pendingRegistrations} />
                            </div>
                        )}
                        {activeTab === 'leads' && <LeadsTab leads={leads} />}
                        {activeTab === 'komisi' && <KomisiTab conversions={conversions} />}
                        {activeTab === 'penarikan' && (
                            <PenarikanTab withdrawals={withdrawals} stats={stats} settings={settings} hasBank={partner.has_bank} />
                        )}
                        {activeTab === 'rekening' && <RekeningTab partner={partner} />}
                    </main>
                </div>
            </div>
        </>
    );
}

const STAT_TONE = {
    primary: { bg: 'rgba(47,84,201,.1)', color: C.primary },
    green: { bg: 'rgba(22,163,74,.1)', color: C.green },
    sky: { bg: 'rgba(110,155,230,.16)', color: C.sky },
    amber: { bg: 'rgba(217,119,6,.1)', color: C.amber },
} as const;

function StatCard({
    label,
    value,
    icon,
    tone = 'sky',
}: {
    label: string;
    value: string;
    icon: string;
    tone?: keyof typeof STAT_TONE;
}) {
    const { bg, color } = STAT_TONE[tone];

    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div
                style={{
                    width: 40,
                    height: 40,
                    borderRadius: 10,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: bg,
                    color,
                }}
            >
                <AIcon name={icon} size={20} color={color} />
            </div>
            <div style={{ fontSize: 24, fontWeight: 700, color: C.navy, marginTop: 14, wordBreak: 'break-word' }}>{value}</div>
            <div style={{ fontSize: 12.5, fontWeight: 600, color: C.muted, marginTop: 3 }}>{label}</div>
        </div>
    );
}

function ReferralLinkBox({ referralUrl }: { referralUrl: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(referralUrl);
            setCopied(true);
            toast.success('Link disalin');
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Gagal menyalin link');
        }
    };

    return (
        <div style={{ ...card, padding: '14px 16px', marginTop: 14, display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
            <AIcon name="link" size={16} color={C.primary} />
            <code style={{ flex: 1, minWidth: 220, fontSize: 13, color: C.text, wordBreak: 'break-all' }}>{referralUrl}</code>
            <button onClick={copy} style={{ ...btnSave, height: 34, padding: '0 14px', fontSize: 12.5 }}>
                {copied ? 'Tersalin!' : 'Salin Link'}
            </button>
        </div>
    );
}

function DashboardTab({ stats, settings, recentConversions, pendingRegistrations }: { stats: Stats; settings: Settings; recentConversions: ConversionRow[]; pendingRegistrations: PendingRegistration[] }) {
    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <div style={{ ...card, padding: '16px 18px', fontSize: 13, color: C.text, lineHeight: 1.6 }}>
                Setiap perusahaan yang mendaftar lewat link Anda dan invoice pertamanya lunas akan dikreditkan sebagai komisi
                sebesar <strong>{rp(settings.flat_amount)}</strong>. Komisi ditahan {settings.hold_days} hari sebelum bisa
                ditarik. Anda punya <strong>{rp(stats.pending_amount)}</strong> yang masih dalam masa tahan.
            </div>
            {pendingRegistrations.length > 0 && (
                <div>
                    <div style={{ fontSize: 14, fontWeight: 700, color: C.navy, marginBottom: 10 }}>Pengajuan Perusahaan</div>
                    <div style={{ display: 'grid', gap: 8 }}>
                        {pendingRegistrations.map((registration) => (
                            <div key={registration.id} style={{ ...card, padding: '12px 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
                                <div>
                                    <div style={{ fontSize: 13, fontWeight: 700, color: C.text }}>{registration.company_name}</div>
                                    <div style={{ fontSize: 12, color: C.muted, marginTop: 3 }}>
                                        {registration.industry || 'Industri belum diisi'} · {registration.employee_count_range || 'Jumlah karyawan belum diisi'}
                                    </div>
                                </div>
                                <Badge label="Menunggu review" tone="amber" />
                            </div>
                        ))}
                    </div>
                </div>
            )}
            {settings.komisi_tab_enabled && (
                <div>
                    <div style={{ fontSize: 14, fontWeight: 700, color: C.navy, marginBottom: 10 }}>Komisi Terbaru</div>
                    <KomisiMiniTable conversions={recentConversions} />
                </div>
            )}
        </div>
    );
}

/** Small static preview — the paginated/searchable version lives in the Komisi tab (`KomisiTab`). */
function KomisiMiniTable({ conversions }: { conversions: ConversionRow[] }) {
    return (
        <div style={{ ...card, overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                <thead>
                    <tr>
                        <th style={thCell}>Klien</th>
                        <th style={thCell}>Komisi</th>
                        <th style={thCell}>Status</th>
                        <th style={thCell}>Cair Sejak</th>
                    </tr>
                </thead>
                <tbody>
                    {conversions.length === 0 && (
                        <tr>
                            <td colSpan={4} style={{ padding: '32px 16px', textAlign: 'center', color: C.muted }}>
                                Belum ada komisi.
                            </td>
                        </tr>
                    )}
                    {conversions.map((c) => (
                        <tr key={c.id} style={{ borderTop: `1px solid ${C.line}` }}>
                            <td style={{ padding: '10px 16px', fontWeight: 600 }}>{c.tenant_name}</td>
                            <td style={{ padding: '10px 16px', fontWeight: 600 }}>{rp(c.commission_amount)}</td>
                            <td style={{ padding: '10px 16px' }}>
                                <Badge label={CONVERSION_STATUS[c.status]?.label ?? c.status} tone={CONVERSION_STATUS[c.status]?.tone ?? 'muted'} />
                            </td>
                            <td style={{ padding: '10px 16px', color: C.muted }}>{c.hold_until ?? '—'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function LeadsTab({ leads }: { leads: Paginated<LeadRow> }) {
    const columns: DataTableColumn<LeadRow>[] = [
        {
            key: 'company_name',
            header: 'Perusahaan',
            sortable: false,
            render: (lead) => (
                <div>
                    <div style={{ fontWeight: 600, color: '#1A2333' }}>{lead.company_name}</div>
                    {lead.tenant_name && <div style={{ fontSize: 12, color: C.green }}>→ {lead.tenant_name}</div>}
                </div>
            ),
        },
        { key: 'contact_name', header: 'Kontak', sortable: false },
        {
            key: 'status',
            header: 'Status',
            sortable: false,
            render: (lead) => <Badge label={LEAD_STATUS[lead.status]?.label ?? lead.status} tone={LEAD_STATUS[lead.status]?.tone ?? 'muted'} />,
        },
        { key: 'created_at', header: 'Tanggal', sortable: false },
    ];

    return (
        <DataTable<LeadRow>
            columns={columns}
            rows={leads.data}
            meta={leads.meta}
            filters={{ search: leads.search }}
            paramPrefix="leads_"
            searchPlaceholder="Cari perusahaan atau kontak…"
            rowKey={(lead) => lead.id}
            emptyState={
                <EmptyState
                    icon={Users}
                    title="Belum ada lead"
                    description="Perusahaan yang mendaftar lewat link referral Anda akan muncul di sini."
                />
            }
        />
    );
}

function KomisiTab({ conversions }: { conversions: Paginated<ConversionRow> }) {
    const columns: DataTableColumn<ConversionRow>[] = [
        {
            key: 'tenant_name',
            header: 'Klien',
            sortable: false,
            render: (c) => <span style={{ fontWeight: 600, color: '#1A2333' }}>{c.tenant_name}</span>,
        },
        {
            key: 'commission_amount',
            header: 'Komisi',
            sortable: false,
            render: (c) => <span style={{ fontWeight: 600 }}>{rp(c.commission_amount)}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            sortable: false,
            render: (c) => <Badge label={CONVERSION_STATUS[c.status]?.label ?? c.status} tone={CONVERSION_STATUS[c.status]?.tone ?? 'muted'} />,
        },
        { key: 'hold_until', header: 'Cair Sejak', sortable: false, render: (c) => c.hold_until ?? '—' },
    ];

    return (
        <DataTable<ConversionRow>
            columns={columns}
            rows={conversions.data}
            meta={conversions.meta}
            filters={{ search: conversions.search }}
            paramPrefix="komisi_"
            searchPlaceholder="Cari nama klien…"
            rowKey={(c) => c.id}
            emptyState={
                <EmptyState icon={Coins} title="Belum ada komisi" description="Komisi dari klien yang berhasil dikonversi akan tercatat di sini." />
            }
        />
    );
}

function PenarikanTab({
    withdrawals,
    stats,
    settings,
    hasBank,
}: {
    withdrawals: Paginated<WithdrawalRow>;
    stats: Stats;
    settings: Settings;
    hasBank: boolean;
}) {
    const form = useForm({ amount: String(Math.max(settings.min_withdrawal_amount, 1)) });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(PortalController.requestWithdrawal().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const columns: DataTableColumn<WithdrawalRow>[] = [
        { key: 'amount', header: 'Nominal', sortable: false, render: (w) => <span style={{ fontWeight: 600 }}>{rp(w.amount)}</span> },
        {
            key: 'status',
            header: 'Status',
            sortable: false,
            render: (w) => (
                <>
                    <Badge label={WITHDRAWAL_STATUS[w.status]?.label ?? w.status} tone={WITHDRAWAL_STATUS[w.status]?.tone ?? 'muted'} />
                    {w.status === 'rejected' && w.admin_note && (
                        <div style={{ fontSize: 11.5, color: C.red, marginTop: 3 }}>{w.admin_note}</div>
                    )}
                    {w.proof_url && (
                        <div style={{ marginTop: 3 }}>
                            <a href={w.proof_url} target="_blank" rel="noreferrer" style={{ fontSize: 11.5, color: C.primary }}>
                                Lihat bukti transfer
                            </a>
                        </div>
                    )}
                </>
            ),
        },
        { key: 'created_at', header: 'Tanggal', sortable: false },
    ];

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            {!hasBank && (
                <div style={{ ...card, padding: '12px 16px', borderLeft: `3px solid ${C.amber}`, background: '#FFFBEB', fontSize: 13 }}>
                    Lengkapi data rekening di tab <strong>Rekening</strong> sebelum bisa mengajukan penarikan.
                </div>
            )}

            <form onSubmit={submit} style={{ ...card, padding: '16px 18px', display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                <div>
                    <label style={fieldLabel}>
                        Jumlah Ditarik (Rp) <span style={{ color: C.red }}>*</span>
                    </label>
                    <RupiahInput
                        value={form.data.amount}
                        onChange={(digits) => form.setData('amount', digits)}
                        invalid={!!form.errors.amount}
                    />
                    <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                        Tersedia {rp(stats.available_amount)} · minimal {rp(settings.min_withdrawal_amount)}
                    </div>
                    {form.errors.amount && <div style={fieldError}>{form.errors.amount}</div>}
                </div>
                <button type="submit" style={btnSave} disabled={form.processing || !hasBank}>
                    {form.processing ? 'Mengajukan…' : 'Ajukan Penarikan'}
                </button>
            </form>

            <DataTable<WithdrawalRow>
                columns={columns}
                rows={withdrawals.data}
                meta={withdrawals.meta}
                filters={{ search: withdrawals.search }}
                paramPrefix="penarikan_"
                searchPlaceholder="Cari status…"
                rowKey={(w) => w.id}
                emptyState={
                    <EmptyState
                        icon={Wallet}
                        title="Belum ada penarikan"
                        description="Riwayat pengajuan pencairan saldo komisi Anda akan muncul di sini."
                    />
                }
            />
        </div>
    );
}

function RekeningTab({ partner }: { partner: Partner }) {
    const form = useForm({
        bank_name: partner.bank_name ?? '',
        bank_account_number: partner.bank_account_number ?? '',
        bank_account_holder: partner.bank_account_holder ?? '',
        npwp: partner.npwp ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(PortalController.updateProfile().url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} style={{ ...card, padding: '20px 22px', maxWidth: 480, display: 'grid', gap: 14 }}>
            <div>
                <label style={fieldLabel}>
                    Nama Bank <span style={{ color: C.red }}>*</span>
                </label>
                <input
                    value={form.data.bank_name}
                    onChange={(e) => form.setData('bank_name', e.target.value)}
                    style={fieldInput}
                    placeholder="BCA, Mandiri, dst."
                    required
                />
                {form.errors.bank_name && <div style={fieldError}>{form.errors.bank_name}</div>}
            </div>
            <div>
                <label style={fieldLabel}>
                    Nomor Rekening <span style={{ color: C.red }}>*</span>
                </label>
                <input
                    value={form.data.bank_account_number}
                    onChange={(e) => form.setData('bank_account_number', e.target.value)}
                    style={fieldInput}
                    required
                />
                {form.errors.bank_account_number && <div style={fieldError}>{form.errors.bank_account_number}</div>}
            </div>
            <div>
                <label style={fieldLabel}>
                    Nama Pemilik Rekening <span style={{ color: C.red }}>*</span>
                </label>
                <input
                    value={form.data.bank_account_holder}
                    onChange={(e) => form.setData('bank_account_holder', e.target.value)}
                    style={fieldInput}
                    required
                />
                {form.errors.bank_account_holder && <div style={fieldError}>{form.errors.bank_account_holder}</div>}
            </div>
            <div>
                <label style={fieldLabel}>NPWP (opsional)</label>
                <input value={form.data.npwp} onChange={(e) => form.setData('npwp', e.target.value)} style={fieldInput} />
            </div>
            <div>
                <button type="submit" style={{ ...btnSave }} disabled={form.processing}>
                    {form.processing ? 'Menyimpan…' : 'Simpan Rekening'}
                </button>
            </div>
        </form>
    );
}

const fieldLabel: CSSProperties = { display: 'block', fontSize: 12.5, fontWeight: 600, color: C.text, marginBottom: 6 };
const fieldInput: CSSProperties = {
    width: '100%',
    height: 38,
    padding: '0 12px',
    fontSize: 13,
    color: C.text,
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    outline: 'none',
};
const fieldError: CSSProperties = { fontSize: 12, color: C.red, marginTop: 4 };
