import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Coins, Handshake, Users, Wallet } from 'lucide-react';
import type { CSSProperties, DragEvent, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ReferralController from '@/actions/App/Http/Controllers/Avana/ReferralController';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { DataTable } from '@/components/avana-ui/data-table';
import type { DataTableColumn, DataTableMeta } from '@/components/avana-ui/data-table';
import { EmptyState } from '@/components/avana-ui/empty-state';
import { FormDialog } from '@/components/avana-ui/form-dialog';
import { ActionBtn, AIcon, btnOut, btnP, btnSave, btnDanger, C, card, RupiahInput } from '@/lib/avana';

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

interface TenantApplication {
    id: number;
    company_name: string;
    phone: string;
    admin_name: string;
    admin_email: string;
    partner_code: string | null;
    partner_name: string | null;
    source: string;
    industry: string | null;
    employee_count_range: string | null;
    created_at: string | null;
}

interface PartnerRow {
    id: number;
    code: string;
    name: string | null;
    email: string | null;
    phone: string | null;
    status: string;
    commission_value: number | null;
    has_bank: boolean;
    balance_amount: number;
    available_amount: number;
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
    commission_amount: number;
    status: string;
    hold_until: string | null;
    created_at: string | null;
}

interface WithdrawalRow {
    id: number;
    partner_name: string | null;
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
    percent_rate: number;
    hold_days: number;
    min_withdrawal_amount: number;
    withdrawal_enabled: boolean;
    leads_tab_enabled: boolean;
    komisi_tab_enabled: boolean;
    rekening_tab_enabled: boolean;
    klien_tab_enabled: boolean;
}

interface Paginated<T> {
    data: T[];
    meta: DataTableMeta;
    search: string;
}

interface PageProps {
    stats: {
        pending_applications: number;
        pending_tenant_applications: number;
        pending_withdrawals: number;
        active_partners: number;
        amount_outstanding: number;
    };
    applications: Application[];
    tenantApplications: TenantApplication[];
    partners: Paginated<PartnerRow>;
    leads: Paginated<LeadRow>;
    conversions: Paginated<ConversionRow>;
    withdrawals: Paginated<WithdrawalRow>;
    settings: Settings;
    flash?: {
        success?: string;
        error?: string;
    };
    errors: Record<string, string>;
    [key: string]: unknown;
}

const TABS = [
    { key: 'mitra', label: 'Mitra' },
    { key: 'pengajuan', label: 'Pengajuan Perusahaan' },
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

export default function ReferralIndex({
    stats,
    applications,
    tenantApplications,
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

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                        gap: 12,
                        marginTop: 18,
                    }}
                >
                    <StatCard label="Mitra Aktif" value={String(stats.active_partners)} icon="handshake" tone="primary" />
                    <StatCard
                        label="Pengajuan Mitra Pending"
                        value={String(stats.pending_applications)}
                        icon="user-plus"
                        tone={stats.pending_applications > 0 ? 'amber' : 'sky'}
                    />
                    <StatCard
                        label="Klien Menunggu Persetujuan"
                        value={String(stats.pending_tenant_applications)}
                        icon="building-2"
                        tone={stats.pending_tenant_applications > 0 ? 'amber' : 'sky'}
                    />
                    <StatCard
                        label="Penarikan Pending"
                        value={String(stats.pending_withdrawals)}
                        icon="banknote"
                        tone={stats.pending_withdrawals > 0 ? 'amber' : 'sky'}
                    />
                    <StatCard label="Komisi Beredar" value={rp(stats.amount_outstanding)} icon="coins" tone="green" />
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
                    {tab === 'mitra' && (
                        <MitraTab applications={applications} partners={partners} errors={errors} />
                    )}
                    {tab === 'pengajuan' && <TenantApplicationsTab tenantApplications={tenantApplications} />}
                    {tab === 'leads' && <LeadsTab leads={leads} />}
                    {tab === 'konversi' && <KonversiTab conversions={conversions} />}
                    {tab === 'penarikan' && <PenarikanTab withdrawals={withdrawals} />}
                    {tab === 'pengaturan' && <PengaturanTab settings={settings} />}
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
            <div style={{ fontSize: 27, fontWeight: 700, color: C.navy, marginTop: 14 }}>{value}</div>
            <div style={{ fontSize: 12.5, fontWeight: 600, color: C.muted, marginTop: 3 }}>{label}</div>
        </div>
    );
}

function mitraColumns(setEditing: (p: PartnerRow) => void): DataTableColumn<PartnerRow>[] {
    return [
        { key: 'code', header: 'Kode', sortable: false, render: (p) => <span style={{ fontWeight: 700, color: C.navy }}>{p.code}</span> },
        {
            key: 'name',
            header: 'Mitra',
            sortable: false,
            render: (p) => (
                <div>
                    <div style={{ fontWeight: 600, color: C.text }}>{p.name}</div>
                    <div style={{ fontSize: 12, color: C.muted }}>{p.email}</div>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: false,
            render: (p) => {
                const st = PARTNER_STATUS_BADGE[p.status] ?? PARTNER_STATUS_BADGE.active;

                return <Badge label={st.label} tone={st.tone} />;
            },
        },
        {
            key: 'has_bank',
            header: 'Rekening',
            sortable: false,
            render: (p) => <Badge label={p.has_bank ? 'Lengkap' : 'Belum diisi'} tone={p.has_bank ? 'green' : 'muted'} />,
        },
        { key: 'balance_amount', header: 'Saldo Komisi', sortable: false, render: (p) => rp(p.balance_amount) },
        { key: 'available_amount', header: 'Tersedia', sortable: false, render: (p) => rp(p.available_amount) },
        {
            key: 'leads_count',
            header: 'Leads / Konversi',
            sortable: false,
            render: (p) => (
                <span style={{ color: C.muted }}>
                    {p.leads_count} / {p.conversions_count}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            sortable: false,
            align: 'right',
            render: (p) => <ActionBtn icon="settings-2" label="Kelola" variant="primary" title={`Kelola mitra ${p.code}`} onClick={() => setEditing(p)} />,
        },
    ];
}

/* ---------- Mitra tab ---------- */

function MitraTab({
    applications,
    partners,
    errors,
}: {
    applications: Application[];
    partners: Paginated<PartnerRow>;
    errors: Record<string, string>;
}) {
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
                <div style={{ marginTop: 10 }}>
                    <DataTable<PartnerRow>
                        columns={mitraColumns(setEditing)}
                        rows={partners.data}
                        meta={partners.meta}
                        filters={{ search: partners.search }}
                        paramPrefix="mitra_"
                        searchPlaceholder="Cari kode, nama, atau email mitra…"
                        rowKey={(p) => p.id}
                        emptyState={<EmptyState icon={Handshake} title="Belum ada mitra" description="Mitra yang disetujui dari pengajuan akan muncul di sini." />}
                    />
                </div>
            </div>

            {editing && <EditPartnerPanel partner={editing} errors={errors} onClose={() => setEditing(null)} />}
        </div>
    );
}

function TenantApplicationsTab({ tenantApplications }: { tenantApplications: TenantApplication[] }) {
    const [rejecting, setRejecting] = useState<TenantApplication | null>(null);

    const approve = (application: TenantApplication) => {
        if (confirm(`Setujui pendaftaran ${application.company_name}? Tenant dan login admin akan dibuat.`)) {
            router.post(ReferralController.approveTenant(application.id).url, {}, { preserveScroll: true });
        }
    };

    return (
        <div style={{ display: 'grid', gap: 12 }}>
            <div>
                <div style={sectionTitle}>Pengajuan Perusahaan</div>
                <div style={{ color: C.muted, fontSize: 12.5, marginTop: 4 }}>Review pendaftaran organik dan referral sebelum akun perusahaan dibuat.</div>
            </div>
            {tenantApplications.length === 0 && <EmptyState icon={Handshake} title="Tidak ada pengajuan" description="Pengajuan perusahaan yang masuk akan tampil di sini." />}
            {tenantApplications.map((application) => (
                <div key={application.id} style={{ ...card, padding: '14px 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <div style={{ fontSize: 13.5, fontWeight: 700, color: C.navy }}>{application.company_name}</div>
                            <Badge label={application.source === 'referral' ? 'Referral' : 'Organik'} tone={application.source === 'referral' ? 'green' : 'primary'} />
                        </div>
                        <div style={{ fontSize: 12.5, color: C.muted, marginTop: 4 }}>{application.admin_name} · {application.admin_email} · {application.phone}</div>
                        <div style={{ fontSize: 12, color: C.faint, marginTop: 4 }}>
                            {application.industry || 'Industri belum diisi'} · {application.employee_count_range || 'Jumlah karyawan belum diisi'}
                            {application.partner_name ? ` · Mitra ${application.partner_name} (${application.partner_code})` : ''}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexShrink: 0 }}>
                        <button style={{ ...btnSave, height: 34, padding: '0 12px', fontSize: 12.5 }} onClick={() => approve(application)}>Setujui</button>
                        <button style={{ ...btnDanger, height: 34, padding: '0 12px', fontSize: 12.5 }} onClick={() => setRejecting(application)}>Tolak</button>
                    </div>
                </div>
            ))}
            <RejectTenantDialog application={rejecting} onClose={() => setRejecting(null)} />
        </div>
    );
}

function RejectTenantDialog({ application, onClose }: { application: TenantApplication | null; onClose: () => void }) {
    const form = useForm<{ admin_note: string }>({ admin_note: '' });

    useEffect(() => {
        if (application) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [application?.id]);

    const submit = () => {
        if (!application) {
            return;
        }

        form.post(ReferralController.rejectTenant(application.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <FormDialog
            open={application !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Tolak Pendaftaran Perusahaan"
            description={application ? `Pengajuan ${application.company_name} akan ditolak dan tidak jadi klien.` : undefined}
            submitLabel="Tolak Pendaftaran"
            onSubmit={submit}
            processing={form.processing}
        >
            <div>
                <label style={fieldLabel}>
                    Alasan Penolakan <span style={{ color: C.red }}>*</span>
                </label>
                <textarea
                    value={form.data.admin_note}
                    onChange={(e) => form.setData('admin_note', e.target.value)}
                    rows={3}
                    placeholder="Contoh: Data perusahaan tidak dapat diverifikasi."
                    style={fieldInput}
                    autoFocus
                />
                {form.errors.admin_note && <div style={fieldError}>{form.errors.admin_note}</div>}
            </div>
        </FormDialog>
    );
}

function EditPartnerPanel({ partner, errors, onClose }: { partner: PartnerRow; errors: Record<string, string>; onClose: () => void }) {
    const form = useForm({
        status: partner.status,
        commission_value: partner.commission_value !== null ? String(partner.commission_value) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
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
                    <label style={fieldLabel}>Override Komisi (%)</label>
                    <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={form.data.commission_value}
                        onChange={(e) => form.setData('commission_value', e.target.value)}
                        style={fieldInput}
                        aria-invalid={!!errors.commission_value}
                    />
                    <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>Kosongkan untuk memakai persentase komisi default.</div>
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

function LeadsTab({ leads }: { leads: Paginated<LeadRow> }) {
    const setStatus = (lead: LeadRow, status: string) => {
        router.put(ReferralController.updateLeadStatus(lead.id).url, { status }, { preserveScroll: true });
    };

    const columns: DataTableColumn<LeadRow>[] = [
        {
            key: 'company_name',
            header: 'Perusahaan',
            sortable: false,
            render: (lead) => (
                <div>
                    <div style={{ fontWeight: 600, color: C.text }}>{lead.company_name}</div>
                    {lead.tenant_name && <div style={{ fontSize: 12, color: C.green }}>→ {lead.tenant_name}</div>}
                </div>
            ),
        },
        {
            key: 'contact_name',
            header: 'Kontak',
            sortable: false,
            render: (lead) => (
                <div style={{ color: C.muted }}>
                    {lead.contact_name}
                    <div style={{ fontSize: 12 }}>
                        {lead.email} · {lead.phone}
                    </div>
                </div>
            ),
        },
        {
            key: 'partner_code',
            header: 'Mitra',
            sortable: false,
            render: (lead) => lead.partner_code ?? <span style={{ color: C.faint }}>—</span>,
        },
        {
            key: 'status',
            header: 'Status',
            sortable: false,
            render: (lead) => {
                const st = LEAD_STATUS_BADGE[lead.status] ?? LEAD_STATUS_BADGE.new;

                return lead.status === 'converted' ? (
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
                );
            },
        },
        {
            key: 'actions',
            header: '',
            sortable: false,
            align: 'right',
            render: (lead) => {
                const canConvert = lead.status !== 'converted' && lead.status !== 'lost';

                return (
                    canConvert && (
                        <Link
                            href={TenantController.create({ query: { referral_lead_id: lead.id } }).url}
                            style={{ ...btnP, height: 30, padding: '0 10px', fontSize: 12, textDecoration: 'none' }}
                        >
                            Jadikan Klien
                        </Link>
                    )
                );
            },
        },
    ];

    return (
        <DataTable<LeadRow>
            columns={columns}
            rows={leads.data}
            meta={leads.meta}
            filters={{ search: leads.search }}
            paramPrefix="leads_"
            searchPlaceholder="Cari perusahaan, kontak, atau kode mitra…"
            rowKey={(lead) => lead.id}
            emptyState={<EmptyState icon={Users} title="Belum ada lead" description="Lead yang masuk lewat link referral mitra akan muncul di sini." />}
        />
    );
}

/* ---------- Konversi tab ---------- */

function KonversiTab({ conversions }: { conversions: Paginated<ConversionRow> }) {
    const columns: DataTableColumn<ConversionRow>[] = [
        { key: 'partner_name', header: 'Mitra', sortable: false, render: (c) => <span style={{ fontWeight: 600 }}>{c.partner_name}</span> },
        { key: 'tenant_name', header: 'Klien', sortable: false },
        { key: 'base_amount', header: 'Nilai Invoice', sortable: false, render: (c) => rp(c.base_amount) },
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
            render: (c) => {
                const st = CONVERSION_STATUS_BADGE[c.status] ?? CONVERSION_STATUS_BADGE.pending;

                return <Badge label={st.label} tone={st.tone} />;
            },
        },
        { key: 'hold_until', header: 'Tersedia Pada', sortable: false, render: (c) => c.hold_until ?? '—' },
    ];

    return (
        <DataTable<ConversionRow>
            columns={columns}
            rows={conversions.data}
            meta={conversions.meta}
            filters={{ search: conversions.search }}
            paramPrefix="konversi_"
            searchPlaceholder="Cari nama mitra atau klien…"
            rowKey={(c) => c.id}
            emptyState={<EmptyState icon={Coins} title="Belum ada konversi" description="Konversi tercatat begitu lead mitra jadi klien dan invoice pertamanya lunas." />}
        />
    );
}

/* ---------- Penarikan tab ---------- */

function PenarikanTab({ withdrawals }: { withdrawals: Paginated<WithdrawalRow> }) {
    const [paying, setPaying] = useState<WithdrawalRow | null>(null);
    const [rejecting, setRejecting] = useState<WithdrawalRow | null>(null);

    const approve = (w: WithdrawalRow) => {
        router.post(ReferralController.approveWithdrawal(w.id).url, {}, { preserveScroll: true });
    };

    const columns: DataTableColumn<WithdrawalRow>[] = [
        { key: 'partner_name', header: 'Mitra', sortable: false, render: (w) => <span style={{ fontWeight: 600 }}>{w.partner_name}</span> },
        { key: 'amount', header: 'Nominal', sortable: false, render: (w) => <span style={{ fontWeight: 600 }}>{rp(w.amount)}</span> },
        {
            key: 'bank_name',
            header: 'Rekening Tujuan',
            sortable: false,
            render: (w) => (
                <div style={{ color: C.muted }}>
                    {w.bank_name} · {w.bank_account_number}
                    <div style={{ fontSize: 12 }}>a.n. {w.bank_account_holder}</div>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: false,
            render: (w) => {
                const st = WITHDRAWAL_STATUS_BADGE[w.status] ?? WITHDRAWAL_STATUS_BADGE.pending;

                return (
                    <>
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
                    </>
                );
            },
        },
        {
            key: 'actions',
            header: '',
            sortable: false,
            align: 'right',
            render: (w) => (
                <div style={{ display: 'inline-flex', gap: 6, whiteSpace: 'nowrap' }}>
                    {w.status === 'pending' && (
                        <>
                            <button style={{ ...btnSave, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => approve(w)}>
                                Setujui
                            </button>
                            <button style={{ ...btnDanger, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => setRejecting(w)}>
                                Tolak
                            </button>
                        </>
                    )}
                    {w.status === 'approved' && (
                        <>
                            <button style={{ ...btnP, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => setPaying(w)}>
                                Bayar
                            </button>
                            <button style={{ ...btnDanger, height: 30, padding: '0 10px', fontSize: 12 }} onClick={() => setRejecting(w)}>
                                Tolak
                            </button>
                        </>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <DataTable<WithdrawalRow>
                columns={columns}
                rows={withdrawals.data}
                meta={withdrawals.meta}
                filters={{ search: withdrawals.search }}
                paramPrefix="penarikan_"
                searchPlaceholder="Cari nama mitra, status, atau bank…"
                rowKey={(w) => w.id}
                emptyState={<EmptyState icon={Wallet} title="Belum ada penarikan" description="Pengajuan pencairan saldo komisi dari mitra akan muncul di sini." />}
            />

            <PayWithdrawalDialog withdrawal={paying} onClose={() => setPaying(null)} />
            <RejectWithdrawalDialog withdrawal={rejecting} onClose={() => setRejecting(null)} />
        </div>
    );
}

function RejectWithdrawalDialog({ withdrawal, onClose }: { withdrawal: WithdrawalRow | null; onClose: () => void }) {
    const form = useForm<{ admin_note: string }>({ admin_note: '' });

    useEffect(() => {
        if (withdrawal) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [withdrawal?.id]);

    const submit = () => {
        if (!withdrawal) {
            return;
        }

        form.post(ReferralController.rejectWithdrawal(withdrawal.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <FormDialog
            open={withdrawal !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Tolak Penarikan"
            description={withdrawal ? `${rp(withdrawal.amount)} milik ${withdrawal.partner_name} — saldo dikembalikan ke saldo tersedia mitra.` : undefined}
            submitLabel="Tolak Penarikan"
            onSubmit={submit}
            processing={form.processing}
        >
            <div>
                <label style={fieldLabel}>
                    Alasan Penolakan <span style={{ color: C.red }}>*</span>
                </label>
                <textarea
                    value={form.data.admin_note}
                    onChange={(e) => form.setData('admin_note', e.target.value)}
                    rows={3}
                    placeholder="Contoh: Data rekening tidak sesuai nama pemilik."
                    style={fieldInput}
                    autoFocus
                />
                {form.errors.admin_note && <div style={fieldError}>{form.errors.admin_note}</div>}
            </div>
        </FormDialog>
    );
}

function PayWithdrawalDialog({ withdrawal, onClose }: { withdrawal: WithdrawalRow | null; onClose: () => void }) {
    const form = useForm<{ proof: File | null; admin_note: string }>({ proof: null, admin_note: '' });

    // Fresh form each time a different withdrawal is opened for payment.
    useEffect(() => {
        if (withdrawal) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [withdrawal?.id]);

    const submit = () => {
        if (!withdrawal) {
            return;
        }

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
        <FormDialog
            open={withdrawal !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Bayar Penarikan"
            description={withdrawal ? `${rp(withdrawal.amount)} ke ${withdrawal.partner_name}` : undefined}
            submitLabel="Tandai Lunas"
            onSubmit={submit}
            processing={form.processing}
        >
            <ProofUpload
                file={form.data.proof}
                error={form.errors.proof}
                onPick={(file) => form.setData('proof', file)}
                onClear={() => form.setData('proof', null)}
            />
            <div>
                <label style={fieldLabel}>Catatan (opsional)</label>
                <textarea
                    value={form.data.admin_note}
                    onChange={(e) => form.setData('admin_note', e.target.value)}
                    rows={2}
                    style={fieldInput}
                />
            </div>
        </FormDialog>
    );
}

/**
 * Drag-and-drop proof-of-transfer picker: an image renders a real thumbnail,
 * a PDF gets a document badge with its filename — either way it reads at a
 * glance, unlike a bare `<input type="file">`.
 */
function ProofUpload({
    file,
    error,
    onPick,
    onClear,
}: {
    file: File | null;
    error?: string;
    onPick: (file: File) => void;
    onClear: () => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        if (!file || !file.type.startsWith('image/')) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const handleFiles = (files: FileList | null) => {
        if (files && files[0]) {
            onPick(files[0]);
        }
    };

    const isPdf = file?.type === 'application/pdf';

    return (
        <div>
            <label style={fieldLabel}>
                Bukti Transfer <span style={{ color: C.red }}>*</span>
            </label>
            <div
                onClick={() => !file && inputRef.current?.click()}
                onDragOver={(event: DragEvent<HTMLDivElement>) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event: DragEvent<HTMLDivElement>) => {
                    event.preventDefault();
                    setDragging(false);
                    handleFiles(event.dataTransfer.files);
                }}
                style={{
                    border: `1.5px dashed ${dragging ? C.primary : error ? C.red : C.border}`,
                    background: dragging ? 'rgba(47,84,201,.04)' : C.surface,
                    borderRadius: 12,
                    padding: file ? 12 : '28px 16px',
                    textAlign: 'center',
                    cursor: file ? 'default' : 'pointer',
                    transition: '.15s',
                }}
            >
                {!file && (
                    <>
                        <AIcon name="upload-cloud" size={26} color={C.primary} style={{ margin: '0 auto 8px' }} />
                        <div style={{ fontSize: 13, color: C.text, fontWeight: 500 }}>
                            Tarik file ke sini atau <span style={{ color: C.primary }}>pilih file</span>
                        </div>
                        <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>JPG, PNG, atau PDF · maks 5MB</div>
                    </>
                )}
                {file && preview && (
                    <img
                        src={preview}
                        alt="Preview bukti transfer"
                        style={{
                            maxWidth: '100%',
                            maxHeight: 220,
                            borderRadius: 8,
                            margin: '0 auto',
                            display: 'block',
                            objectFit: 'contain',
                        }}
                    />
                )}
                {file && isPdf && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, textAlign: 'left', padding: '8px 10px' }}>
                        <div
                            style={{
                                width: 40,
                                height: 40,
                                borderRadius: 8,
                                background: 'rgba(220,38,38,.1)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flex: 'none',
                            }}
                        >
                            <AIcon name="file-text" size={20} color={C.red} />
                        </div>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: 13, fontWeight: 600, color: C.text, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                {file.name}
                            </div>
                            <div style={{ fontSize: 11.5, color: C.faint }}>{(file.size / 1024).toFixed(0)} KB</div>
                        </div>
                    </div>
                )}
            </div>
            <input
                ref={inputRef}
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                onChange={(event) => {
                    handleFiles(event.target.files);
                    event.target.value = '';
                }}
                style={{ display: 'none' }}
            />
            {file && (
                <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            height: 32,
                            padding: '0 12px',
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            borderRadius: 8,
                            fontSize: 12.5,
                            fontWeight: 500,
                            color: C.text,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="repeat" size={13} />
                        Ganti
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            onClear();

                            if (inputRef.current) {
                                inputRef.current.value = '';
                            }
                        }}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            height: 32,
                            padding: '0 12px',
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            borderRadius: 8,
                            fontSize: 12.5,
                            fontWeight: 500,
                            color: C.red,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="trash-2" size={13} />
                        Hapus
                    </button>
                </div>
            )}
            {error && <div style={fieldError}>{error}</div>}
        </div>
    );
}

/* ---------- Pengaturan tab ---------- */

function PengaturanTab({ settings }: { settings: Settings }) {
    const form = useForm({
        percent_rate: String(settings.percent_rate),
        hold_days: String(settings.hold_days),
        min_withdrawal_amount: String(settings.min_withdrawal_amount),
        withdrawal_enabled: settings.withdrawal_enabled,
        leads_tab_enabled: settings.leads_tab_enabled,
        komisi_tab_enabled: settings.komisi_tab_enabled,
        rekening_tab_enabled: settings.rekening_tab_enabled,
        klien_tab_enabled: settings.klien_tab_enabled,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(ReferralController.updateSettings().url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} style={{ ...card, padding: '20px 22px', maxWidth: 560, display: 'grid', gap: 16 }}>
            <div>
                <label style={fieldLabel}>Komisi per Konversi (%)</label>
                <input
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={form.data.percent_rate}
                    onChange={(e) => form.setData('percent_rate', e.target.value)}
                    style={fieldInput}
                />
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                    Komisi dihitung dari nilai invoice pertama yang lunas. Contoh: invoice Rp1.000.000 dengan rate{' '}
                    {form.data.percent_rate || 0}% menghasilkan {rp(Number(form.data.percent_rate || 0) * 10000)}.
                </div>
                {form.errors.percent_rate && <div style={fieldError}>{form.errors.percent_rate}</div>}
            </div>

            <div>
                <label style={fieldLabel}>Masa Tahan Komisi (hari)</label>
                <input value={form.data.hold_days} onChange={(e) => form.setData('hold_days', e.target.value)} style={fieldInput} />
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                    Komisi baru bisa ditarik setelah masa tahan ini lewat, sebagai jaga-jaga bila invoice dibatalkan.
                </div>
            </div>

            <div>
                <label style={fieldLabel}>Minimal Penarikan (Rp)</label>
                <RupiahInput
                    value={form.data.min_withdrawal_amount}
                    onChange={(digits) => form.setData('min_withdrawal_amount', digits)}
                    invalid={!!form.errors.min_withdrawal_amount}
                />
                {form.errors.min_withdrawal_amount && <div style={fieldError}>{form.errors.min_withdrawal_amount}</div>}
            </div>

            <div style={{ borderTop: `1px solid ${C.line}`, paddingTop: 16 }}>
                <label style={fieldLabel}>Menu Portal Mitra</label>
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: -2, marginBottom: 10 }}>
                    Dashboard selalu tampil (memuat link referral mitra). Menu lain bisa dimatikan sementara — request lewat menu yang mati tetap ditolak di server.
                </div>
                <div style={{ display: 'grid', gap: 10 }}>
                    <MenuToggle
                        checked={form.data.leads_tab_enabled}
                        onChange={(checked) => form.setData('leads_tab_enabled', checked)}
                        label="Tampilkan menu Leads"
                    />
                    <MenuToggle
                        checked={form.data.komisi_tab_enabled}
                        onChange={(checked) => form.setData('komisi_tab_enabled', checked)}
                        label="Tampilkan menu Komisi"
                    />
                    <MenuToggle
                        checked={form.data.withdrawal_enabled}
                        onChange={(checked) => form.setData('withdrawal_enabled', checked)}
                        label="Tampilkan menu Penarikan"
                    />
                    <MenuToggle
                        checked={form.data.rekening_tab_enabled}
                        onChange={(checked) => form.setData('rekening_tab_enabled', checked)}
                        label="Tampilkan menu Rekening"
                    />
                </div>
            </div>

            <div style={{ borderTop: `1px solid ${C.line}`, paddingTop: 16 }}>
                <label style={fieldLabel}>Kontrol Fitur Klien</label>
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: -2, marginBottom: 10 }}>
                    Kalau aktif, mitra bisa lihat perusahaan yang mereka referensikan lewat menu &ldquo;Klien&rdquo; di portalnya, dan menyalakan/mematikan modul apa saja yang klien itu dapat — hanya untuk tenant yang dia referensikan sendiri.
                </div>
                <MenuToggle
                    checked={form.data.klien_tab_enabled}
                    onChange={(checked) => form.setData('klien_tab_enabled', checked)}
                    label="Izinkan mitra atur fitur kliennya"
                />
            </div>

            <div>
                <button type="submit" style={btnSave} disabled={form.processing}>
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    );
}

function MenuToggle({ checked, onChange, label }: { checked: boolean; onChange: (checked: boolean) => void; label: string }) {
    return (
        <label style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 14, color: C.text, cursor: 'pointer' }}>
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            {label}
        </label>
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
