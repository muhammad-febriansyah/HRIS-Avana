import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ReferralController from '@/actions/App/Http/Controllers/Avana/ReferralController';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { ActionBtn, AIcon, btnOut, btnP, btnSave, btnDanger, C, card, RupiahInput, thCell } from '@/lib/avana';

/* ---------- types ---------- */

interface Application {
    id: number;
    full_name: string;
    email: string;
    whatsapp: string;
    partner_type: string;
    company_name: string | null;
    network_size: string | null;
    network_focus: string | null;
    network_description: string | null;
    created_at: string | null;
}

interface PartnerRow {
    id: number;
    code: string;
    name: string | null;
    email: string | null;
    phone: string | null;
    status: string;
    commission_mode: string | null;
    commission_value: number | null;
    has_bank: boolean;
    balance_points: number;
    available_points: number;
    leads_count: number;
    conversions_count: number;
    created_at: string | null;
}

interface LeadRow {
    id: number;
    company_name: string;
    contact_name: string;
    email: string;
    phone: string;
    note: string | null;
    status: string;
    partner_code: string | null;
    tenant_name: string | null;
    created_at: string | null;
}

interface ConversionRow {
    id: number;
    partner_name: string | null;
    tenant_name: string | null;
    base_amount: number;
    points: number;
    commission_amount: number;
    status: string;
    hold_until: string | null;
    created_at: string | null;
}

interface WithdrawalRow {
    id: number;
    partner_name: string | null;
    points: number;
    amount: number;
    bank_name: string;
    bank_account_number: string;
    bank_account_holder: string;
    status: string;
    note: string | null;
    admin_note: string | null;
    proof_url: string | null;
    created_at: string | null;
}

interface Settings {
    mode: string;
    points_per_conversion: number;
    percent_rate: number;
    point_value: number;
    hold_days: number;
    min_withdrawal_points: number;
}

interface PageProps {
    stats: {
        pending_applications: number;
        pending_withdrawals: number;
        active_partners: number;
        points_outstanding: number;
    };
    applications: Application[];
    partners: PartnerRow[];
    leads: LeadRow[];
    conversions: ConversionRow[];
    withdrawals: WithdrawalRow[];
    settings: Settings;
    flash?: {
        success?: string;
        error?: string;
        credentials?: { name: string; email: string; password: string };
    };
    errors: Record<string, string>;
    [key: string]: unknown;
}

const TABS = [
    { key: 'mitra', label: 'Mitra' },
    { key: 'leads', label: 'Leads' },
    { key: 'konversi', label: 'Konversi' },
    { key: 'penarikan', label: 'Penarikan' },
    { key: 'pengaturan', label: 'Pengaturan Komisi' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

function rp(n: number): string {
    return 'Rp' + Math.round(n).toLocaleString('id-ID');
}

type Tone = 'green' | 'amber' | 'red' | 'muted' | 'primary';

function Badge({ label, tone }: { label: string; tone: Tone }) {
    const map: Record<string, [string, string]> = {
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

const PARTNER_STATUS_BADGE: Record<string, { label: string; tone: Tone }> = {
    active: { label: 'Aktif', tone: 'green' },
    suspended: { label: 'Ditangguhkan', tone: 'red' },
};

const LEAD_STATUS_BADGE: Record<string, { label: string; tone: Tone }> = {
    new: { label: 'Baru', tone: 'amber' },
    contacted: { label: 'Dihubungi', tone: 'primary' },
    converted: { label: 'Jadi Klien', tone: 'green' },
    lost: { label: 'Hilang', tone: 'muted' },
};

const CONVERSION_STATUS_BADGE: Record<string, { label: string; tone: Tone }> = {
    pending: { label: 'Tertahan', tone: 'amber' },
    approved: { label: 'Disetujui', tone: 'green' },
    void: { label: 'Dibatalkan', tone: 'red' },
};

const WITHDRAWAL_STATUS_BADGE: Record<string, { label: string; tone: Tone }> = {
    pending: { label: 'Menunggu', tone: 'amber' },
    approved: { label: 'Disetujui', tone: 'primary' },
    paid: { label: 'Lunas', tone: 'green' },
    rejected: { label: 'Ditolak', tone: 'red' },
};

const sectionTitle: CSSProperties = { fontSize: 15, fontWeight: 700, color: C.navy };
const emptyState: CSSProperties = { padding: '32px 16px', textAlign: 'center', color: C.muted, fontSize: 13 };

export default function ReferralIndex({
    stats,
    applications,
    partners,
    leads,
    conversions,
    withdrawals,
    settings,
}: PageProps) {
    const { flash, errors } = usePage<PageProps>().props;
    const [tab, setTab] = useState<TabKey>('mitra');

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

    return (
        <>
            <Head title="Referral" />
            <div style={{ padding: '28px 32px' }}>
                <h1 style={{ fontSize: 20, fontWeight: 800, color: C.navy }}>Referral</h1>
                <p style={{ fontSize: 13, color: C.muted, marginTop: 4 }}>
                    Mitra referral, lead yang mereka bawa, komisi, dan penarikan.
                </p>

                {flash?.credentials && <CredentialsBanner credentials={flash.credentials} />}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                        gap: 12,
                        marginTop: 18,
                    }}
                >
                    <StatCard label="Mitra Aktif" value={String(stats.active_partners)} icon="handshake" />
                    <StatCard label="Pengajuan Pending" value={String(stats.pending_applications)} icon="user-plus" tone={stats.pending_applications > 0 ? 'amber' : undefined} />
                    <StatCard label="Penarikan Pending" value={String(stats.pending_withdrawals)} icon="banknote" tone={stats.pending_withdrawals > 0 ? 'amber' : undefined} />
                    <StatCard label="Poin Beredar" value={stats.points_outstanding.toLocaleString('id-ID')} icon="coins" />
                </div>

                <div style={{ display: 'flex', gap: 4, marginTop: 22, borderBottom: `1px solid ${C.border}` }}>
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => setTab(t.key)}
                            style={{
                                padding: '10px 14px',
                                fontSize: 13,
                                fontWeight: 600,
                                background: 'none',
                                border: 'none',
                                borderBottom: tab === t.key ? `2px solid ${C.primary}` : '2px solid transparent',
                                color: tab === t.key ? C.primary : C.muted,
                                cursor: 'pointer',
                            }}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                <div style={{ marginTop: 18 }}>
                    {tab === 'mitra' && <MitraTab applications={applications} partners={partners} errors={errors} />}
                    {tab === 'leads' && <LeadsTab leads={leads} />}
                    {tab === 'konversi' && <KonversiTab conversions={conversions} />}
                    {tab === 'penarikan' && <PenarikanTab withdrawals={withdrawals} />}
                    {tab === 'pengaturan' && <PengaturanTab settings={settings} />}
                </div>
            </div>
        </>
    );
}

function CredentialsBanner({ credentials }: { credentials: { name: string; email: string; password: string } }) {
    return (
        <div style={{ ...card, padding: '16px 18px', borderLeft: `3px solid ${C.green}`, background: '#F0FDF4', marginTop: 16 }}>
            <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy, marginBottom: 4 }}>
                Kredensial mitra baru — catat sekarang
            </div>
            <div style={{ fontSize: 12.5, color: C.muted, marginBottom: 12 }}>
                Password hanya ditampilkan sekali. Sampaikan ke mitra secara aman.
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12 }}>
                <div>
                    <div style={{ fontSize: 11.5, color: C.faint, textTransform: 'uppercase' }}>Nama</div>
                    <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text }}>{credentials.name}</div>
                </div>
                <div>
                    <div style={{ fontSize: 11.5, color: C.faint, textTransform: 'uppercase' }}>Email</div>
                    <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text }}>{credentials.email}</div>
                </div>
                <div>
                    <div style={{ fontSize: 11.5, color: C.faint, textTransform: 'uppercase' }}>Password</div>
                    <code style={{ fontSize: 13.5, fontWeight: 600, background: '#fff', border: `1px solid ${C.line}`, borderRadius: 6, padding: '3px 8px' }}>
                        {credentials.password}
                    </code>
                </div>
            </div>
        </div>
    );
}

function StatCard({ label, value, icon, tone }: { label: string; value: string; icon: string; tone?: 'amber' }) {
    return (
        <div style={{ ...card, padding: '16px 18px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: tone === 'amber' ? C.amber : C.muted }}>
                <AIcon name={icon} size={16} color={tone === 'amber' ? C.amber : C.muted} />
                <span style={{ fontSize: 12, fontWeight: 600 }}>{label}</span>
            </div>
            <div style={{ fontSize: 24, fontWeight: 800, color: C.navy, marginTop: 6 }}>{value}</div>
        </div>
    );
}

/* ---------- Mitra tab ---------- */

function MitraTab({ applications, partners, errors }: { applications: Application[]; partners: PartnerRow[]; errors: Record<string, string> }) {
    const [editing, setEditing] = useState<PartnerRow | null>(null);

    const approve = (app: Application) => {
        if (!confirm(`Setujui pengajuan mitra dari ${app.full_name}?`)) {
            return;
        }

        router.post(ReferralController.approvePartner(app.id).url, {}, { preserveScroll: true });
    };

    const reject = (app: Application) => {
        if (!confirm(`Tolak pengajuan mitra dari ${app.full_name}?`)) {
            return;
        }

        router.post(ReferralController.rejectPartner(app.id).url, {}, { preserveScroll: true });
    };

    return (
        <div style={{ display: 'grid', gap: 20 }}>
            {applications.length > 0 && (
                <div>
                    <div style={sectionTitle}>Pengajuan Mitra Baru</div>
                    <div style={{ display: 'grid', gap: 10, marginTop: 10 }}>
                        {applications.map((app) => (
                            <div key={app.id} style={{ ...card, padding: '14px 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                                <div>
                                    <div style={{ fontSize: 13.5, fontWeight: 700, color: C.navy }}>{app.full_name}</div>
                                    <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>
                                        {app.email} · {app.whatsapp} · {app.partner_type}
                                        {app.company_name ? ` · ${app.company_name}` : ''}
                                    </div>
                                    {app.network_description && (
                                        <div style={{ fontSize: 12, color: C.faint, marginTop: 4, maxWidth: 520 }}>{app.network_description}</div>
                                    )}
                                </div>
                                <div style={{ display: 'flex', gap: 8, flexShrink: 0 }}>
                                    <button style={{ ...btnSave, height: 34, padding: '0 12px', fontSize: 12.5 }} onClick={() => approve(app)}>
                                        Setujui
                                    </button>
                                    <button style={{ ...btnDanger, height: 34, padding: '0 12px', fontSize: 12.5 }} onClick={() => reject(app)}>
                                        Tolak
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div>
                <div style={sectionTitle}>Daftar Mitra</div>
                <div style={{ ...card, marginTop: 10, overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                        <thead>
                            <tr>
                                <th style={thCell}>Kode</th>
                                <th style={thCell}>Mitra</th>
                                <th style={thCell}>Status</th>
                                <th style={thCell}>Rekening</th>
                                <th style={thCell}>Saldo Poin</th>
                                <th style={thCell}>Tersedia</th>
                                <th style={thCell}>Leads / Konversi</th>
                                <th style={thCell} />
                            </tr>
                        </thead>
                        <tbody>
                            {partners.length === 0 && (
                                <tr>
                                    <td colSpan={8} style={emptyState}>
                                        Belum ada mitra.
                                    </td>
                                </tr>
                            )}
                            {partners.map((p) => {
                                const st = PARTNER_STATUS_BADGE[p.status] ?? PARTNER_STATUS_BADGE.active;

                                return (
                                    <tr key={p.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td style={{ padding: '10px 16px', fontWeight: 700, color: C.navy }}>{p.code}</td>
                                        <td style={{ padding: '10px 16px' }}>
                                            <div style={{ fontWeight: 600, color: C.text }}>{p.name}</div>
                                            <div style={{ fontSize: 12, color: C.muted }}>{p.email}</div>
                                        </td>
                                        <td style={{ padding: '10px 16px' }}>
                                            <Badge label={st.label} tone={st.tone} />
                                        </td>
                                        <td style={{ padding: '10px 16px' }}>
                                            <Badge label={p.has_bank ? 'Lengkap' : 'Belum diisi'} tone={p.has_bank ? 'green' : 'muted'} />
                                        </td>
                                        <td style={{ padding: '10px 16px' }}>{p.balance_points.toLocaleString('id-ID')}</td>
                                        <td style={{ padding: '10px 16px' }}>{p.available_points.toLocaleString('id-ID')}</td>
                                        <td style={{ padding: '10px 16px', color: C.muted }}>
                                            {p.leads_count} / {p.conversions_count}
                                        </td>
                                        <td style={{ padding: '10px 16px', textAlign: 'right' }}>
                                            <ActionBtn
                                                icon="settings-2"
                                                label="Kelola"
                                                variant="primary"
                                                title={`Kelola mitra ${p.code}`}
                                                onClick={() => setEditing(p)}
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {editing && <EditPartnerPanel partner={editing} errors={errors} onClose={() => setEditing(null)} />}
        </div>
    );
}

function EditPartnerPanel({ partner, errors, onClose }: { partner: PartnerRow; errors: Record<string, string>; onClose: () => void }) {
    const form = useForm({
        status: partner.status,
        commission_mode: partner.commission_mode ?? '',
        commission_value: partner.commission_value !== null ? String(partner.commission_value) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            commission_mode: data.commission_mode || null,
            commission_value: data.commission_value || null,
        }));
        form.put(ReferralController.updatePartner(partner.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={sectionTitle}>Kelola Mitra — {partner.code}</div>
                <button onClick={onClose} style={{ ...btnOut, height: 30, padding: '0 10px', fontSize: 12 }}>
                    Tutup
                </button>
            </div>
            <form onSubmit={submit} style={{ display: 'grid', gap: 14, marginTop: 14, maxWidth: 480 }}>
                <div>
                    <label style={fieldLabel}>Status</label>
                    <select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} style={fieldInput}>
                        <option value="active">Aktif</option>
                        <option value="suspended">Ditangguhkan</option>
                    </select>
                </div>
                <div>
                    <label style={fieldLabel}>Override Komisi (opsional)</label>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <select value={form.data.commission_mode} onChange={(e) => form.setData('commission_mode', e.target.value)} style={{ ...fieldInput, maxWidth: 160 }}>
                            <option value="">Pakai default</option>
                            <option value="flat">Poin tetap</option>
                            <option value="percent">Persen invoice</option>
                        </select>
                        <input
                            value={form.data.commission_value}
                            onChange={(e) => form.setData('commission_value', e.target.value)}
                            placeholder={form.data.commission_mode === 'percent' ? '% invoice' : 'Poin per konversi'}
                            style={fieldInput}
                            disabled={!form.data.commission_mode}
                        />
                    </div>
                    {errors.commission_value && <div style={fieldError}>{errors.commission_value}</div>}
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    <button type="submit" style={btnSave} disabled={form.processing}>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    );
}

/* ---------- Leads tab ---------- */

function LeadsTab({ leads }: { leads: LeadRow[] }) {
    const setStatus = (lead: LeadRow, status: string) => {
        router.put(ReferralController.updateLeadStatus(lead.id).url, { status }, { preserveScroll: true });
    };

    return (
        <div style={{ ...card, overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                <thead>
                    <tr>
                        <th style={thCell}>Perusahaan</th>
                        <th style={thCell}>Kontak</th>
                        <th style={thCell}>Mitra</th>
                        <th style={thCell}>Status</th>
                        <th style={thCell} />
                    </tr>
                </thead>
                <tbody>
                    {leads.length === 0 && (
                        <tr>
                            <td colSpan={5} style={emptyState}>
                                Belum ada lead.
                            </td>
                        </tr>
                    )}
                    {leads.map((lead) => {
                        const st = LEAD_STATUS_BADGE[lead.status] ?? LEAD_STATUS_BADGE.new;
                        const canConvert = lead.status !== 'converted' && lead.status !== 'lost';

                        return (
                            <tr key={lead.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                <td style={{ padding: '10px 16px' }}>
                                    <div style={{ fontWeight: 600, color: C.text }}>{lead.company_name}</div>
                                    {lead.tenant_name && <div style={{ fontSize: 12, color: C.green }}>→ {lead.tenant_name}</div>}
                                </td>
                                <td style={{ padding: '10px 16px', color: C.muted }}>
                                    {lead.contact_name}
                                    <div style={{ fontSize: 12 }}>
                                        {lead.email} · {lead.phone}
                                    </div>
                                </td>
                                <td style={{ padding: '10px 16px' }}>{lead.partner_code ?? <span style={{ color: C.faint }}>—</span>}</td>
                                <td style={{ padding: '10px 16px' }}>
                                    {lead.status === 'converted' ? (
                                        <Badge label={st.label} tone={st.tone} />
                                    ) : (
                                        <select
                                            value={lead.status}
                                            onChange={(e) => setStatus(lead, e.target.value)}
                                            style={{ ...fieldInput, height: 30, fontSize: 12, padding: '0 8px' }}
                                        >
                                            <option value="new">Baru</option>
                                            <option value="contacted">Dihubungi</option>
                                            <option value="lost">Hilang</option>
                                        </select>
                                    )}
                                </td>
                                <td style={{ padding: '10px 16px', textAlign: 'right' }}>
                                    {canConvert && (
                                        <Link
                                            href={TenantController.create({ query: { referral_lead_id: lead.id } }).url}
                                            style={{ ...btnP, height: 30, padding: '0 10px', fontSize: 12, textDecoration: 'none' }}
                                        >
                                            Jadikan Klien
                                        </Link>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/* ---------- Konversi tab ---------- */

function KonversiTab({ conversions }: { conversions: ConversionRow[] }) {
    return (
        <div style={{ ...card, overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                <thead>
                    <tr>
                        <th style={thCell}>Mitra</th>
                        <th style={thCell}>Klien</th>
                        <th style={thCell}>Nilai Invoice</th>
                        <th style={thCell}>Poin</th>
                        <th style={thCell}>Komisi</th>
                        <th style={thCell}>Status</th>
                        <th style={thCell}>Berlaku Sejak</th>
                    </tr>
                </thead>
                <tbody>
                    {conversions.length === 0 && (
                        <tr>
                            <td colSpan={7} style={emptyState}>
                                Belum ada konversi.
                            </td>
                        </tr>
                    )}
                    {conversions.map((c) => {
                        const st = CONVERSION_STATUS_BADGE[c.status] ?? CONVERSION_STATUS_BADGE.pending;

                        return (
                            <tr key={c.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                <td style={{ padding: '10px 16px', fontWeight: 600 }}>{c.partner_name}</td>
                                <td style={{ padding: '10px 16px' }}>{c.tenant_name}</td>
                                <td style={{ padding: '10px 16px' }}>{rp(c.base_amount)}</td>
                                <td style={{ padding: '10px 16px' }}>{c.points.toLocaleString('id-ID')}</td>
                                <td style={{ padding: '10px 16px', fontWeight: 600 }}>{rp(c.commission_amount)}</td>
                                <td style={{ padding: '10px 16px' }}>
                                    <Badge label={st.label} tone={st.tone} />
                                </td>
                                <td style={{ padding: '10px 16px', color: C.muted }}>{c.hold_until ?? '—'}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/* ---------- Penarikan tab ---------- */

function PenarikanTab({ withdrawals }: { withdrawals: WithdrawalRow[] }) {
    const [paying, setPaying] = useState<WithdrawalRow | null>(null);

    const approve = (w: WithdrawalRow) => {
        router.post(ReferralController.approveWithdrawal(w.id).url, {}, { preserveScroll: true });
    };

    const reject = (w: WithdrawalRow) => {
        const note = prompt('Alasan penolakan:');

        if (!note) {
            return;
        }

        router.post(ReferralController.rejectWithdrawal(w.id).url, { admin_note: note }, { preserveScroll: true });
    };

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <div style={{ ...card, overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                    <thead>
                        <tr>
                            <th style={thCell}>Mitra</th>
                            <th style={thCell}>Poin</th>
                            <th style={thCell}>Nominal</th>
                            <th style={thCell}>Rekening Tujuan</th>
                            <th style={thCell}>Status</th>
                            <th style={thCell} />
                        </tr>
                    </thead>
                    <tbody>
                        {withdrawals.length === 0 && (
                            <tr>
                                <td colSpan={6} style={emptyState}>
                                    Belum ada penarikan.
                                </td>
                            </tr>
                        )}
                        {withdrawals.map((w) => {
                            const st = WITHDRAWAL_STATUS_BADGE[w.status] ?? WITHDRAWAL_STATUS_BADGE.pending;

                            return (
                                <tr key={w.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                    <td style={{ padding: '10px 16px', fontWeight: 600 }}>{w.partner_name}</td>
                                    <td style={{ padding: '10px 16px' }}>{w.points.toLocaleString('id-ID')}</td>
                                    <td style={{ padding: '10px 16px', fontWeight: 600 }}>{rp(w.amount)}</td>
                                    <td style={{ padding: '10px 16px', color: C.muted }}>
                                        {w.bank_name} · {w.bank_account_number}
                                        <div style={{ fontSize: 12 }}>a.n. {w.bank_account_holder}</div>
                                    </td>
                                    <td style={{ padding: '10px 16px' }}>
                                        <Badge label={st.label} tone={st.tone} />
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
                                    <td style={{ padding: '10px 16px', textAlign: 'right', whiteSpace: 'nowrap' }}>
                                        {w.status === 'pending' && (
                                            <div style={{ display: 'inline-flex', gap: 6 }}>
                                                <button style={{ ...btnSave, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => approve(w)}>
                                                    Setujui
                                                </button>
                                                <button style={{ ...btnDanger, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => reject(w)}>
                                                    Tolak
                                                </button>
                                            </div>
                                        )}
                                        {w.status === 'approved' && (
                                            <div style={{ display: 'inline-flex', gap: 6 }}>
                                                <button style={{ ...btnP, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => setPaying(w)}>
                                                    Bayar
                                                </button>
                                                <button style={{ ...btnDanger, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => reject(w)}>
                                                    Tolak
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {paying && <PayWithdrawalPanel withdrawal={paying} onClose={() => setPaying(null)} />}
        </div>
    );
}

function PayWithdrawalPanel({ withdrawal, onClose }: { withdrawal: WithdrawalRow; onClose: () => void }) {
    const form = useForm<{ proof: File | null; admin_note: string }>({ proof: null, admin_note: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (!form.data.proof) {
            toast.error('Unggah bukti transfer terlebih dahulu');

            return;
        }

        form.post(ReferralController.payWithdrawal(withdrawal.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div style={{ ...card, padding: '18px 20px', maxWidth: 480 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={sectionTitle}>
                    Bayar Penarikan — {rp(withdrawal.amount)} ke {withdrawal.partner_name}
                </div>
                <button onClick={onClose} style={{ ...btnOut, height: 30, padding: '0 10px', fontSize: 12 }}>
                    Tutup
                </button>
            </div>
            <form onSubmit={submit} style={{ display: 'grid', gap: 14, marginTop: 14 }}>
                <div>
                    <label style={fieldLabel}>
                        Bukti Transfer <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(e) => form.setData('proof', e.target.files?.[0] ?? null)}
                        style={fieldInput}
                    />
                    {form.errors.proof && <div style={fieldError}>{form.errors.proof}</div>}
                </div>
                <div>
                    <label style={fieldLabel}>Catatan (opsional)</label>
                    <textarea
                        value={form.data.admin_note}
                        onChange={(e) => form.setData('admin_note', e.target.value)}
                        rows={2}
                        style={fieldInput}
                    />
                </div>
                <button type="submit" style={btnSave} disabled={form.processing}>
                    Tandai Lunas
                </button>
            </form>
        </div>
    );
}

/* ---------- Pengaturan tab ---------- */

function PengaturanTab({ settings }: { settings: Settings }) {
    const form = useForm({
        mode: settings.mode,
        points_per_conversion: String(settings.points_per_conversion),
        percent_rate: String(settings.percent_rate),
        point_value: String(settings.point_value),
        hold_days: String(settings.hold_days),
        min_withdrawal_points: String(settings.min_withdrawal_points),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(ReferralController.updateSettings().url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} style={{ ...card, padding: '20px 22px', maxWidth: 560, display: 'grid', gap: 16 }}>
            <div>
                <label style={fieldLabel}>Cara Hitung Komisi</label>
                <select value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value)} style={fieldInput}>
                    <option value="flat">Poin tetap per konversi</option>
                    <option value="percent">Persen dari invoice pertama</option>
                </select>
            </div>

            {form.data.mode === 'flat' ? (
                <div>
                    <label style={fieldLabel}>Poin per Konversi</label>
                    <input
                        value={form.data.points_per_conversion}
                        onChange={(e) => form.setData('points_per_conversion', e.target.value)}
                        style={fieldInput}
                    />
                    <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                        Setiap klien baru yang lunas invoice pertamanya = {form.data.points_per_conversion || 0} poin ≈{' '}
                        {rp(Number(form.data.points_per_conversion || 0) * Number(form.data.point_value || 0))}.
                    </div>
                    {form.errors.points_per_conversion && <div style={fieldError}>{form.errors.points_per_conversion}</div>}
                </div>
            ) : (
                <div>
                    <label style={fieldLabel}>Persen dari Invoice (%)</label>
                    <input value={form.data.percent_rate} onChange={(e) => form.setData('percent_rate', e.target.value)} style={fieldInput} />
                    <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                        Contoh: invoice pertama Rp1.000.000 = komisi{' '}
                        {rp(1_000_000 * (Number(form.data.percent_rate || 0) / 100))}.
                    </div>
                    {form.errors.percent_rate && <div style={fieldError}>{form.errors.percent_rate}</div>}
                </div>
            )}

            <div>
                <label style={fieldLabel}>Nilai 1 Poin</label>
                <RupiahInput value={form.data.point_value} onChange={(digits) => form.setData('point_value', digits)} invalid={!!form.errors.point_value} />
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                    Rupiah yang dibayarkan mitra per poin saat penarikan dicairkan.
                </div>
                {form.errors.point_value && <div style={fieldError}>{form.errors.point_value}</div>}
            </div>

            <div>
                <label style={fieldLabel}>Masa Tahan Komisi (hari)</label>
                <input value={form.data.hold_days} onChange={(e) => form.setData('hold_days', e.target.value)} style={fieldInput} />
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                    Komisi baru bisa ditarik setelah masa tahan ini lewat, sebagai jaga-jaga bila invoice dibatalkan.
                </div>
            </div>

            <div>
                <label style={fieldLabel}>Minimal Penarikan (poin)</label>
                <input value={form.data.min_withdrawal_points} onChange={(e) => form.setData('min_withdrawal_points', e.target.value)} style={fieldInput} />
            </div>

            <div>
                <button type="submit" style={btnSave} disabled={form.processing}>
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    );
}

/* ---------- shared field styles ---------- */

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
