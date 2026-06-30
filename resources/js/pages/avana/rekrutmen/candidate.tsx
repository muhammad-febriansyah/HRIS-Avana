import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import { StageBadge } from './components';
import { fieldLabelStyle, inputStyle, selectStyle, textareaStyle } from './components';
import { BACKGROUND_TYPE_OPTIONS } from './types';
import type {
    BackgroundCheck,
    CandidateProps,
    FlashProps,
    MedicalCheck,
    SelectOption,
} from './types';

const sectionTitle: CSSProperties = {
    fontSize: 12,
    fontWeight: 600,
    color: C.faint,
    letterSpacing: '.05em',
    textTransform: 'uppercase',
    marginBottom: 14,
};

const cardPad: CSSProperties = { ...card, padding: 22 };

function StatusPill({ status }: { status: string }) {
    const map: Record<string, [string, string, string]> = {
        passed: [C.green, 'rgba(22,163,74,.1)', 'Lulus'],
        clear: [C.green, 'rgba(22,163,74,.1)', 'Bersih'],
        pending: [C.amber, 'rgba(217,119,6,.1)', 'Menunggu'],
        requested: [C.muted, 'rgba(107,114,128,.12)', 'Diminta'],
        failed: [C.red, 'rgba(220,38,38,.1)', 'Gagal'],
        flagged: [C.red, 'rgba(220,38,38,.1)', 'Bermasalah'],
    };
    const [color, bg, label] = map[status] ?? [C.muted, 'rgba(107,114,128,.12)', status];

    return (
        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color, background: bg }}>
            {label}
        </span>
    );
}

function InfoRow({ icon, label, value }: { icon: string; label: string; value: React.ReactNode }) {
    return (
        <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start', marginBottom: 14 }}>
            <div
                style={{
                    width: 34,
                    height: 34,
                    borderRadius: 9,
                    background: C.surface,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                }}
            >
                <AIcon name={icon} size={15} color={C.muted} />
            </div>
            <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 11.5, color: C.faint, marginBottom: 2 }}>{label}</div>
                <div style={{ fontSize: 13.5, color: C.text, fontWeight: 500, wordBreak: 'break-word' }}>
                    {value || <span style={{ color: C.faint, fontWeight: 400 }}>—</span>}
                </div>
            </div>
        </div>
    );
}

function fmtDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

export default function Candidate({ applicant, stages }: CandidateProps) {
    const { flash } = usePage<FlashProps>().props;

    const [editProfile, setEditProfile] = useState(false);
    const [panel, setPanel] = useState<null | 'interview' | 'offer' | 'medical' | 'background' | 'blacklist'>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const stageLabel = (value: string): string =>
        stages.find((option: SelectOption) => option.value === value)?.label ?? value;

    const profileForm = useForm({
        name: applicant.name,
        email: applicant.email,
        phone: applicant.phone ?? '',
        position: applicant.position ?? '',
        source: applicant.source ?? '',
        linkedin_url: applicant.linkedin_url ?? '',
        portfolio_url: applicant.portfolio_url ?? '',
        notes: applicant.notes ?? '',
    });
    const cvForm = useForm<{ cv: File | null }>({ cv: null });
    const interviewForm = useForm({ interview_at: applicant.interview_at?.slice(0, 16) ?? '' });
    const offerForm = useForm({ offer_note: applicant.offer_note ?? '' });
    const blacklistForm = useForm({ blacklisted: true, blacklist_reason: '' });
    const medicalForm = useForm<{
        title: string;
        status: string;
        checked_at: string;
        notes: string;
        document: File | null;
    }>({ title: '', status: 'pending', checked_at: '', notes: '', document: null });
    const backgroundForm = useForm<{
        check_type: string;
        status: string;
        requested_at: string;
        notes: string;
        document: File | null;
    }>({ check_type: 'employment', status: 'requested', requested_at: '', notes: '', document: null });

    const initials = applicant.name
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    return (
        <>
            <Head title={`Kandidat — ${applicant.name}`} />
            <div style={{ padding: '28px 32px', maxWidth: 1200 }}>
                {/* breadcrumb */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                    <Link href={RecruitmentController.index()} style={{ color: C.faint, textDecoration: 'none' }}>
                        Rekrutmen
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Detail Kandidat</span>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24 }}>
                    <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>
                        Detail Kandidat
                    </h1>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        {applicant.blacklisted ? (
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '4px 11px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: C.red, background: 'rgba(220,38,38,.1)' }}>
                                <AIcon name="ban" size={13} color={C.red} />
                                Blacklist
                            </span>
                        ) : null}
                        <StageBadge stage={applicant.stage} label={stageLabel(applicant.stage)} />
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,2fr) minmax(0,1fr)', gap: 20, alignItems: 'start' }}>
                    {/* ---------------- LEFT COLUMN ---------------- */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        {/* profile */}
                        <div style={cardPad}>
                            <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
                                {applicant.photo_url ? (
                                    <img
                                        src={applicant.photo_url}
                                        alt={applicant.name}
                                        style={{ width: 64, height: 64, borderRadius: 16, objectFit: 'cover' }}
                                    />
                                ) : (
                                    <div
                                        style={{
                                            width: 64,
                                            height: 64,
                                            borderRadius: 16,
                                            background: 'rgba(47,84,201,.1)',
                                            color: C.primary,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: 22,
                                            fontWeight: 700,
                                        }}
                                    >
                                        {initials}
                                    </div>
                                )}
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 19, fontWeight: 600, color: C.navy }}>{applicant.name}</div>
                                    <div style={{ fontSize: 13, color: C.muted, marginTop: 3 }}>
                                        {applicant.position ?? applicant.job_title ?? 'Pelamar'}
                                    </div>
                                </div>
                                <button
                                    onClick={() => setEditProfile((value) => !value)}
                                    style={{ ...btnOut, height: 36 }}
                                >
                                    <AIcon name="pencil" size={14} />
                                    {editProfile ? 'Tutup' : 'Edit'}
                                </button>
                            </div>

                            {editProfile ? (
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        profileForm.submit(RecruitmentController.updateApplicant(applicant.id), {
                                            onSuccess: () => setEditProfile(false),
                                        });
                                    }}
                                    style={{ marginTop: 20, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}
                                >
                                    {(
                                        [
                                            ['name', 'Nama', 'text'],
                                            ['email', 'Email', 'email'],
                                            ['phone', 'Telepon', 'text'],
                                            ['position', 'Posisi', 'text'],
                                            ['linkedin_url', 'LinkedIn URL', 'text'],
                                            ['portfolio_url', 'Portfolio URL', 'text'],
                                            ['source', 'Sumber', 'text'],
                                        ] as const
                                    ).map(([key, label, type]) => (
                                        <div key={key}>
                                            <label style={fieldLabelStyle}>{label}</label>
                                            <input
                                                type={type}
                                                value={profileForm.data[key]}
                                                onChange={(event) => profileForm.setData(key, event.target.value)}
                                                style={inputStyle}
                                            />
                                        </div>
                                    ))}
                                    <div style={{ gridColumn: '1 / -1', display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
                                        <button type="submit" disabled={profileForm.processing} style={btnP}>
                                            <AIcon name="check" size={15} />
                                            Simpan
                                        </button>
                                    </div>
                                </form>
                            ) : null}
                        </div>

                        {/* contact + social */}
                        <div style={cardPad}>
                            <div style={sectionTitle}>Informasi Kontak</div>
                            <InfoRow icon="mail" label="Email" value={applicant.email} />
                            <InfoRow icon="phone" label="Telepon" value={applicant.phone} />
                            <InfoRow icon="map-pin" label="Sumber" value={applicant.source} />

                            <div style={{ ...sectionTitle, marginTop: 22 }}>Tautan Sosial</div>
                            <InfoRow
                                icon="linkedin"
                                label="LinkedIn"
                                value={
                                    applicant.linkedin_url ? (
                                        <a href={applicant.linkedin_url} target="_blank" rel="noreferrer" style={{ color: C.primary }}>
                                            Lihat Profil
                                        </a>
                                    ) : null
                                }
                            />
                            <InfoRow
                                icon="globe"
                                label="Portfolio"
                                value={
                                    applicant.portfolio_url ? (
                                        <a href={applicant.portfolio_url} target="_blank" rel="noreferrer" style={{ color: C.primary }}>
                                            Buka Tautan
                                        </a>
                                    ) : null
                                }
                            />
                        </div>

                        {/* medical checkup */}
                        <div style={cardPad}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                                <div>
                                    <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Pemeriksaan Kesehatan</div>
                                    <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>
                                        Dokumen verifikasi kesehatan & status
                                    </div>
                                </div>
                                <button onClick={() => setPanel((p) => (p === 'medical' ? null : 'medical'))} style={{ ...btnP, height: 36, background: C.green }}>
                                    <AIcon name="plus" size={14} />
                                    Tambah
                                </button>
                            </div>

                            {panel === 'medical' ? (
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        medicalForm.submit(RecruitmentController.storeMedicalCheck(applicant.id), {
                                            forceFormData: true,
                                            onSuccess: () => {
                                                medicalForm.reset();
                                                setPanel(null);
                                            },
                                        });
                                    }}
                                    style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 16, padding: 16, background: C.surface, borderRadius: 10 }}
                                >
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label style={fieldLabelStyle}>Judul Pemeriksaan</label>
                                        <input value={medicalForm.data.title} onChange={(e) => medicalForm.setData('title', e.target.value)} style={inputStyle} placeholder="Medical Check Up Umum" />
                                    </div>
                                    <div>
                                        <label style={fieldLabelStyle}>Status</label>
                                        <select value={medicalForm.data.status} onChange={(e) => medicalForm.setData('status', e.target.value)} style={selectStyle}>
                                            <option value="pending">Menunggu</option>
                                            <option value="passed">Lulus</option>
                                            <option value="failed">Gagal</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style={fieldLabelStyle}>Tanggal</label>
                                        <input type="date" value={medicalForm.data.checked_at} onChange={(e) => medicalForm.setData('checked_at', e.target.value)} style={inputStyle} />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label style={fieldLabelStyle}>Dokumen (PDF/Gambar)</label>
                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => medicalForm.setData('document', e.target.files?.[0] ?? null)} style={inputStyle} />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1', display: 'flex', justifyContent: 'flex-end' }}>
                                        <button type="submit" disabled={medicalForm.processing} style={btnP}>Simpan</button>
                                    </div>
                                </form>
                            ) : null}

                            {applicant.medical_checks.length === 0 ? (
                                <EmptyState icon="stethoscope" text="Belum ada dokumen pemeriksaan kesehatan" />
                            ) : (
                                applicant.medical_checks.map((check: MedicalCheck) => (
                                    <RecordRow
                                        key={check.id}
                                        title={check.title}
                                        sub={fmtDate(check.checked_at)}
                                        status={check.status}
                                        fileUrl={check.file_url}
                                        notes={check.notes}
                                    />
                                ))
                            )}
                        </div>

                        {/* background checks */}
                        <div style={cardPad}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                                <div>
                                    <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Pemeriksaan Latar Belakang</div>
                                    <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>Verifikasi & rekam jejak</div>
                                </div>
                                <button onClick={() => setPanel((p) => (p === 'background' ? null : 'background'))} style={{ ...btnP, height: 36, background: C.green }}>
                                    <AIcon name="plus" size={14} />
                                    Minta
                                </button>
                            </div>

                            {panel === 'background' ? (
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        backgroundForm.submit(RecruitmentController.storeBackgroundCheck(applicant.id), {
                                            forceFormData: true,
                                            onSuccess: () => {
                                                backgroundForm.reset();
                                                setPanel(null);
                                            },
                                        });
                                    }}
                                    style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 16, padding: 16, background: C.surface, borderRadius: 10 }}
                                >
                                    <div>
                                        <label style={fieldLabelStyle}>Jenis</label>
                                        <select value={backgroundForm.data.check_type} onChange={(e) => backgroundForm.setData('check_type', e.target.value)} style={selectStyle}>
                                            {BACKGROUND_TYPE_OPTIONS.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label style={fieldLabelStyle}>Status</label>
                                        <select value={backgroundForm.data.status} onChange={(e) => backgroundForm.setData('status', e.target.value)} style={selectStyle}>
                                            <option value="requested">Diminta</option>
                                            <option value="clear">Bersih</option>
                                            <option value="flagged">Bermasalah</option>
                                        </select>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label style={fieldLabelStyle}>Catatan</label>
                                        <textarea value={backgroundForm.data.notes} onChange={(e) => backgroundForm.setData('notes', e.target.value)} style={textareaStyle} />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label style={fieldLabelStyle}>Dokumen (opsional)</label>
                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => backgroundForm.setData('document', e.target.files?.[0] ?? null)} style={inputStyle} />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1', display: 'flex', justifyContent: 'flex-end' }}>
                                        <button type="submit" disabled={backgroundForm.processing} style={btnP}>Simpan</button>
                                    </div>
                                </form>
                            ) : null}

                            {applicant.background_checks.length === 0 ? (
                                <EmptyState icon="shield-check" text="Belum ada informasi pemeriksaan latar belakang" />
                            ) : (
                                applicant.background_checks.map((check: BackgroundCheck) => (
                                    <RecordRow
                                        key={check.id}
                                        title={BACKGROUND_TYPE_OPTIONS.find((o) => o.value === check.check_type)?.label ?? check.check_type}
                                        sub={fmtDate(check.requested_at)}
                                        status={check.status}
                                        fileUrl={check.file_url}
                                        notes={check.notes}
                                    />
                                ))
                            )}
                        </div>
                    </div>

                    {/* ---------------- RIGHT COLUMN ---------------- */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        {/* application details */}
                        <div style={cardPad}>
                            <div style={sectionTitle}>Detail Lamaran</div>
                            <InfoRow icon="calendar" label="Melamar pada" value={fmtDate(applicant.applied_date)} />
                            <InfoRow icon="briefcase" label="Posisi" value={applicant.job_title} />
                            <InfoRow icon="file-badge" label="Tipe" value={applicant.employment_type} />
                            {applicant.interview_at ? (
                                <InfoRow icon="calendar-clock" label="Jadwal Wawancara" value={fmtDate(applicant.interview_at)} />
                            ) : null}
                            {applicant.offered_at ? (
                                <InfoRow icon="badge-check" label="Penawaran" value={fmtDate(applicant.offered_at)} />
                            ) : null}
                        </div>

                        {/* quick actions */}
                        <div style={cardPad}>
                            <div style={sectionTitle}>Aksi Cepat</div>

                            {applicant.cv_url ? (
                                <a href={applicant.cv_url} target="_blank" rel="noreferrer" style={{ ...btnOut, width: '100%', justifyContent: 'center', marginBottom: 10, textDecoration: 'none' }}>
                                    <AIcon name="download" size={15} />
                                    Unduh CV
                                </a>
                            ) : null}

                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    cvForm.submit(RecruitmentController.uploadCv(applicant.id), { forceFormData: true, onSuccess: () => cvForm.reset() });
                                }}
                                style={{ marginBottom: 10 }}
                            >
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx"
                                    onChange={(e) => cvForm.setData('cv', e.target.files?.[0] ?? null)}
                                    style={{ ...inputStyle, paddingTop: 9, marginBottom: 8 }}
                                />
                                <button type="submit" disabled={!cvForm.data.cv || cvForm.processing} style={{ ...btnOut, width: '100%', justifyContent: 'center' }}>
                                    <AIcon name="upload" size={15} />
                                    Unggah CV
                                </button>
                            </form>

                            <button onClick={() => setPanel((p) => (p === 'interview' ? null : 'interview'))} style={{ ...btnOut, width: '100%', justifyContent: 'center', marginBottom: 10 }}>
                                <AIcon name="calendar-clock" size={15} />
                                Jadwalkan Wawancara
                            </button>
                            {panel === 'interview' ? (
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        interviewForm.submit(RecruitmentController.scheduleInterview(applicant.id), { onSuccess: () => setPanel(null) });
                                    }}
                                    style={{ marginBottom: 10, padding: 14, background: C.surface, borderRadius: 10 }}
                                >
                                    <label style={fieldLabelStyle}>Tanggal & Jam</label>
                                    <input type="datetime-local" value={interviewForm.data.interview_at} onChange={(e) => interviewForm.setData('interview_at', e.target.value)} style={{ ...inputStyle, marginBottom: 10 }} />
                                    <button type="submit" disabled={interviewForm.processing} style={{ ...btnP, width: '100%', justifyContent: 'center' }}>Simpan Jadwal</button>
                                </form>
                            ) : null}

                            <button onClick={() => setPanel((p) => (p === 'offer' ? null : 'offer'))} style={{ ...btnP, width: '100%', justifyContent: 'center' }}>
                                <AIcon name="badge-check" size={15} />
                                Buat Penawaran
                            </button>
                            {panel === 'offer' ? (
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        offerForm.submit(RecruitmentController.makeOffer(applicant.id), { onSuccess: () => setPanel(null) });
                                    }}
                                    style={{ marginTop: 10, padding: 14, background: C.surface, borderRadius: 10 }}
                                >
                                    <label style={fieldLabelStyle}>Catatan Penawaran</label>
                                    <textarea value={offerForm.data.offer_note} onChange={(e) => offerForm.setData('offer_note', e.target.value)} style={{ ...textareaStyle, marginBottom: 10 }} />
                                    <button type="submit" disabled={offerForm.processing} style={{ ...btnP, width: '100%', justifyContent: 'center', background: C.green }}>Kirim Penawaran</button>
                                </form>
                            ) : null}

                            <div style={{ borderTop: `1px solid ${C.line}`, margin: '14px 0' }} />

                            {applicant.blacklisted ? (
                                <>
                                    {applicant.blacklist_reason ? (
                                        <div style={{ fontSize: 12, color: C.muted, marginBottom: 8, lineHeight: 1.5 }}>
                                            Alasan: {applicant.blacklist_reason}
                                        </div>
                                    ) : null}
                                    <button
                                        onClick={() => router.post(RecruitmentController.toggleBlacklist(applicant.id).url, { blacklisted: false }, { preserveScroll: true })}
                                        style={{ ...btnOut, width: '100%', justifyContent: 'center', color: C.green, borderColor: C.green }}
                                    >
                                        <AIcon name="rotate-ccw" size={15} color={C.green} />
                                        Keluarkan dari Blacklist
                                    </button>
                                </>
                            ) : (
                                <>
                                    <button onClick={() => setPanel((p) => (p === 'blacklist' ? null : 'blacklist'))} style={{ ...btnOut, width: '100%', justifyContent: 'center', color: C.red, borderColor: 'rgba(220,38,38,.4)' }}>
                                        <AIcon name="ban" size={15} color={C.red} />
                                        Masukkan Blacklist
                                    </button>
                                    {panel === 'blacklist' ? (
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                blacklistForm.submit(RecruitmentController.toggleBlacklist(applicant.id), { onSuccess: () => setPanel(null) });
                                            }}
                                            style={{ marginTop: 10, padding: 14, background: C.surface, borderRadius: 10 }}
                                        >
                                            <label style={fieldLabelStyle}>Alasan Blacklist</label>
                                            <textarea value={blacklistForm.data.blacklist_reason} onChange={(e) => blacklistForm.setData('blacklist_reason', e.target.value)} style={{ ...textareaStyle, marginBottom: 10 }} />
                                            <button type="submit" disabled={blacklistForm.processing} style={{ ...btnP, width: '100%', justifyContent: 'center', background: C.red }}>Konfirmasi Blacklist</button>
                                        </form>
                                    ) : null}
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function EmptyState({ icon, text }: { icon: string; text: string }) {
    return (
        <div style={{ textAlign: 'center', padding: '28px 0', color: C.faint }}>
            <AIcon name={icon} size={26} color={C.border} />
            <div style={{ fontSize: 13, marginTop: 8 }}>{text}</div>
        </div>
    );
}

function RecordRow({ title, sub, status, fileUrl, notes }: { title: string; sub: string; status: string; fileUrl: string | null; notes: string | null }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 0', borderTop: `1px solid ${C.line}` }}>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text }}>{title}</div>
                <div style={{ fontSize: 12, color: C.faint, marginTop: 2 }}>
                    {sub}
                    {notes ? ` · ${notes}` : ''}
                </div>
            </div>
            <StatusPill status={status} />
            {fileUrl ? (
                <a href={fileUrl} target="_blank" rel="noreferrer" style={{ color: C.primary, display: 'inline-flex' }}>
                    <AIcon name="file-text" size={16} />
                </a>
            ) : null}
        </div>
    );
}
