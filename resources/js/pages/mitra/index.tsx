import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PortalController from '@/actions/App/Http/Controllers/Mitra/PortalController';
import { AIcon, btnSave, C, card, thCell } from '@/lib/avana';
import { logout } from '@/routes';

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
    balance_points: number;
    available_points: number;
    pending_points: number;
}

interface Settings {
    point_value: number;
    min_withdrawal_points: number;
    hold_days: number;
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
    points: number;
    commission_amount: number;
    status: string;
    hold_until: string | null;
    created_at: string | null;
}

interface WithdrawalRow {
    id: number;
    points: number;
    amount: number;
    status: string;
    admin_note: string | null;
    proof_url: string | null;
    created_at: string | null;
}

interface PageProps {
    partner: Partner;
    stats: Stats;
    settings: Settings;
    referralUrl: string;
    leads: LeadRow[];
    conversions: ConversionRow[];
    withdrawals: WithdrawalRow[];
    flash?: { success?: string; error?: string };
    errors: Record<string, string>;
    auth: { user: { name: string; email: string } | null };
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

function rp(n: number): string {
    return 'Rp' + Math.round(n).toLocaleString('id-ID');
}

const LEAD_LABEL: Record<string, string> = {
    new: 'Baru',
    contacted: 'Dihubungi',
    converted: 'Jadi Klien',
    lost: 'Hilang',
};

const CONVERSION_LABEL: Record<string, string> = {
    pending: 'Tertahan',
    approved: 'Disetujui',
    void: 'Dibatalkan',
};

const WITHDRAWAL_LABEL: Record<string, string> = {
    pending: 'Menunggu',
    approved: 'Diproses',
    paid: 'Lunas',
    rejected: 'Ditolak',
};

export default function MitraIndex({ partner, stats, settings, referralUrl, leads, conversions, withdrawals }: PageProps) {
    const { flash, auth } = usePage<PageProps>().props;
    const user = auth.user;
    const [tab, setTab] = useState<TabKey>('dashboard');

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

    const activeLabel = TABS.find((t) => t.key === tab)?.label ?? 'Dashboard';

    return (
        <>
            <Head title="Dashboard Mitra" />

            <div style={{ display: 'flex', minHeight: '100dvh' }}>
                <aside
                    style={{
                        width: 232,
                        flexShrink: 0,
                        background: '#fff',
                        borderRight: `1px solid ${C.border}`,
                        padding: '20px 12px',
                        display: 'flex',
                        flexDirection: 'column',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '0 8px',
                            marginBottom: 22,
                            fontWeight: 700,
                            fontSize: 15,
                            color: C.navy,
                        }}
                    >
                        <AIcon name="handshake" size={20} color={C.primary} />
                        AvanaHR
                    </div>
                    <div
                        style={{
                            fontSize: 11,
                            fontWeight: 700,
                            letterSpacing: '.04em',
                            color: C.faint,
                            padding: '0 8px',
                            marginBottom: 8,
                            textTransform: 'uppercase',
                        }}
                    >
                        Mitra Referral
                    </div>
                    <nav style={{ display: 'flex', flexDirection: 'column' }}>
                        {TABS.map((t) => {
                            const active = tab === t.key;

                            return (
                                <button
                                    key={t.key}
                                    onClick={() => setTab(t.key)}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        width: '100%',
                                        height: 40,
                                        padding: '0 12px',
                                        marginBottom: 3,
                                        border: 'none',
                                        borderRadius: 9,
                                        cursor: 'pointer',
                                        fontSize: 13.5,
                                        textAlign: 'left',
                                        fontWeight: active ? 600 : 500,
                                        color: active ? C.primary : C.text,
                                        background: active ? 'rgba(47,84,201,.09)' : 'transparent',
                                    }}
                                >
                                    <AIcon name={t.icon} size={17} color={active ? C.primary : C.muted} />
                                    {t.label}
                                </button>
                            );
                        })}
                    </nav>

                    <div style={{ marginTop: 'auto', paddingTop: 16 }}>
                        <div
                            style={{
                                padding: '12px',
                                borderRadius: 10,
                                background: C.surface,
                                fontSize: 12,
                                color: C.muted,
                            }}
                        >
                            <div style={{ fontWeight: 600, color: C.text, marginBottom: 2 }}>{partner.code}</div>
                            <div>Kode referral Anda</div>
                        </div>
                    </div>
                </aside>

                <div style={{ flex: 1, minWidth: 0 }}>
                    <header
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            padding: '16px 28px',
                            background: '#fff',
                            borderBottom: `1px solid ${C.border}`,
                        }}
                    >
                        <h1 style={{ fontSize: 18, fontWeight: 700, color: C.navy }}>{activeLabel}</h1>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                            <div style={{ textAlign: 'right' }}>
                                <div style={{ fontSize: 13, fontWeight: 600, color: C.text }}>{user?.name}</div>
                                <div style={{ fontSize: 12, color: C.muted }}>{user?.email}</div>
                            </div>
                            <Link
                                href={logout()}
                                onClick={() => router.flushAll()}
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

                    <main style={{ padding: '24px 28px' }}>
                        {partner.status === 'suspended' && (
                            <div style={{ ...card, padding: '12px 16px', borderLeft: `3px solid ${C.red}`, background: '#FEF2F2', marginBottom: 16 }}>
                                <strong style={{ color: C.red }}>Akun mitra Anda sedang ditangguhkan.</strong> Hubungi tim AvanaHR untuk info lebih lanjut.
                            </div>
                        )}

                        {tab === 'dashboard' && (
                            <div style={{ display: 'grid', gap: 16 }}>
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
                                        gap: 12,
                                    }}
                                >
                                    <StatCard label="Klik Link" value={stats.clicks.toLocaleString('id-ID')} icon="mouse-pointer-click" />
                                    <StatCard label="Leads" value={stats.leads.toLocaleString('id-ID')} icon="users" />
                                    <StatCard label="Saldo Poin" value={stats.balance_points.toLocaleString('id-ID')} icon="coins" />
                                    <StatCard label="Tersedia Ditarik" value={stats.available_points.toLocaleString('id-ID')} icon="wallet" tone="primary" />
                                </div>

                                <ReferralLinkBox referralUrl={referralUrl} />

                                <DashboardTab stats={stats} settings={settings} conversions={conversions} />
                            </div>
                        )}
                        {tab === 'leads' && <LeadsTab leads={leads} />}
                        {tab === 'komisi' && <KomisiTab conversions={conversions} />}
                        {tab === 'penarikan' && <PenarikanTab withdrawals={withdrawals} stats={stats} settings={settings} hasBank={partner.has_bank} />}
                        {tab === 'rekening' && <RekeningTab partner={partner} />}
                    </main>
                </div>
            </div>
        </>
    );
}

function StatCard({ label, value, icon, tone }: { label: string; value: string; icon: string; tone?: 'primary' }) {
    return (
        <div style={{ ...card, padding: '14px 16px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: tone === 'primary' ? C.primary : C.muted }}>
                <AIcon name={icon} size={15} color={tone === 'primary' ? C.primary : C.muted} />
                <span style={{ fontSize: 11.5, fontWeight: 600 }}>{label}</span>
            </div>
            <div style={{ fontSize: 20, fontWeight: 800, color: C.navy, marginTop: 6, wordBreak: 'break-word' }}>{value}</div>
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

function DashboardTab({ stats, settings, conversions }: { stats: Stats; settings: Settings; conversions: ConversionRow[] }) {
    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <div style={{ ...card, padding: '16px 18px', fontSize: 13, color: C.text, lineHeight: 1.6 }}>
                Setiap perusahaan yang mendaftar lewat link Anda dan invoice pertamanya lunas akan dikreditkan sebagai komisi.
                Komisi ditahan {settings.hold_days} hari sebelum bisa ditarik, senilai <strong>{rp(settings.point_value)}</strong> per poin.
                Anda punya <strong>{stats.pending_points.toLocaleString('id-ID')} poin</strong> yang masih dalam masa tahan.
            </div>
            <div>
                <div style={{ fontSize: 14, fontWeight: 700, color: C.navy, marginBottom: 10 }}>Komisi Terbaru</div>
                <KomisiTab conversions={conversions.slice(0, 5)} />
            </div>
        </div>
    );
}

function LeadsTab({ leads }: { leads: LeadRow[] }) {
    return (
        <div style={{ ...card, overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                <thead>
                    <tr>
                        <th style={thCell}>Perusahaan</th>
                        <th style={thCell}>Kontak</th>
                        <th style={thCell}>Status</th>
                        <th style={thCell}>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    {leads.length === 0 && (
                        <tr>
                            <td colSpan={4} style={{ padding: '32px 16px', textAlign: 'center', color: C.muted }}>
                                Belum ada lead dari link Anda.
                            </td>
                        </tr>
                    )}
                    {leads.map((lead) => (
                        <tr key={lead.id} style={{ borderTop: `1px solid ${C.line}` }}>
                            <td style={{ padding: '10px 16px', fontWeight: 600 }}>
                                {lead.company_name}
                                {lead.tenant_name && <div style={{ fontSize: 12, color: C.green, fontWeight: 400 }}>→ {lead.tenant_name}</div>}
                            </td>
                            <td style={{ padding: '10px 16px', color: C.muted }}>{lead.contact_name}</td>
                            <td style={{ padding: '10px 16px' }}>{LEAD_LABEL[lead.status] ?? lead.status}</td>
                            <td style={{ padding: '10px 16px', color: C.muted }}>{lead.created_at}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function KomisiTab({ conversions }: { conversions: ConversionRow[] }) {
    return (
        <div style={{ ...card, overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                <thead>
                    <tr>
                        <th style={thCell}>Klien</th>
                        <th style={thCell}>Poin</th>
                        <th style={thCell}>Komisi</th>
                        <th style={thCell}>Status</th>
                        <th style={thCell}>Cair Sejak</th>
                    </tr>
                </thead>
                <tbody>
                    {conversions.length === 0 && (
                        <tr>
                            <td colSpan={5} style={{ padding: '32px 16px', textAlign: 'center', color: C.muted }}>
                                Belum ada komisi.
                            </td>
                        </tr>
                    )}
                    {conversions.map((c) => (
                        <tr key={c.id} style={{ borderTop: `1px solid ${C.line}` }}>
                            <td style={{ padding: '10px 16px', fontWeight: 600 }}>{c.tenant_name}</td>
                            <td style={{ padding: '10px 16px' }}>{c.points.toLocaleString('id-ID')}</td>
                            <td style={{ padding: '10px 16px', fontWeight: 600 }}>{rp(c.commission_amount)}</td>
                            <td style={{ padding: '10px 16px' }}>{CONVERSION_LABEL[c.status] ?? c.status}</td>
                            <td style={{ padding: '10px 16px', color: C.muted }}>{c.hold_until ?? '—'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function PenarikanTab({
    withdrawals,
    stats,
    settings,
    hasBank,
}: {
    withdrawals: WithdrawalRow[];
    stats: Stats;
    settings: Settings;
    hasBank: boolean;
}) {
    const form = useForm({ points: String(Math.max(settings.min_withdrawal_points, 1)) });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(PortalController.requestWithdrawal().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            {!hasBank && (
                <div style={{ ...card, padding: '12px 16px', borderLeft: `3px solid ${C.amber}`, background: '#FFFBEB', fontSize: 13 }}>
                    Lengkapi data rekening di tab <strong>Rekening</strong> sebelum bisa mengajukan penarikan.
                </div>
            )}

            <form onSubmit={submit} style={{ ...card, padding: '16px 18px', display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                <div>
                    <label style={fieldLabel}>Jumlah Poin Ditarik</label>
                    <input value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} style={{ ...fieldInput, width: 180 }} />
                    <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                        Tersedia {stats.available_points.toLocaleString('id-ID')} poin · minimal {settings.min_withdrawal_points.toLocaleString('id-ID')} poin · ≈{' '}
                        {rp(Number(form.data.points || 0) * settings.point_value)}
                    </div>
                    {form.errors.points && <div style={fieldError}>{form.errors.points}</div>}
                </div>
                <button type="submit" style={btnSave} disabled={form.processing || !hasBank}>
                    Ajukan Penarikan
                </button>
            </form>

            <div style={{ ...card, overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                    <thead>
                        <tr>
                            <th style={thCell}>Poin</th>
                            <th style={thCell}>Nominal</th>
                            <th style={thCell}>Status</th>
                            <th style={thCell}>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {withdrawals.length === 0 && (
                            <tr>
                                <td colSpan={4} style={{ padding: '32px 16px', textAlign: 'center', color: C.muted }}>
                                    Belum ada penarikan.
                                </td>
                            </tr>
                        )}
                        {withdrawals.map((w) => (
                            <tr key={w.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                <td style={{ padding: '10px 16px' }}>{w.points.toLocaleString('id-ID')}</td>
                                <td style={{ padding: '10px 16px', fontWeight: 600 }}>{rp(w.amount)}</td>
                                <td style={{ padding: '10px 16px' }}>
                                    {WITHDRAWAL_LABEL[w.status] ?? w.status}
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
                                </td>
                                <td style={{ padding: '10px 16px', color: C.muted }}>{w.created_at}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
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
                <label style={fieldLabel}>Nama Bank</label>
                <input value={form.data.bank_name} onChange={(e) => form.setData('bank_name', e.target.value)} style={fieldInput} placeholder="BCA, Mandiri, dst." />
                {form.errors.bank_name && <div style={fieldError}>{form.errors.bank_name}</div>}
            </div>
            <div>
                <label style={fieldLabel}>Nomor Rekening</label>
                <input
                    value={form.data.bank_account_number}
                    onChange={(e) => form.setData('bank_account_number', e.target.value)}
                    style={fieldInput}
                />
                {form.errors.bank_account_number && <div style={fieldError}>{form.errors.bank_account_number}</div>}
            </div>
            <div>
                <label style={fieldLabel}>Nama Pemilik Rekening</label>
                <input
                    value={form.data.bank_account_holder}
                    onChange={(e) => form.setData('bank_account_holder', e.target.value)}
                    style={fieldInput}
                />
                {form.errors.bank_account_holder && <div style={fieldError}>{form.errors.bank_account_holder}</div>}
            </div>
            <div>
                <label style={fieldLabel}>NPWP (opsional)</label>
                <input value={form.data.npwp} onChange={(e) => form.setData('npwp', e.target.value)} style={fieldInput} />
            </div>
            <div>
                <button type="submit" style={{ ...btnSave }} disabled={form.processing}>
                    Simpan Rekening
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
