import { Head, Link } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { toast } from 'sonner';
import { AIcon, C, leaveBalances, netTot, rp } from '@/lib/avana';

const quickBtn: CSSProperties = {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-start',
    gap: 12,
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 12,
    padding: 18,
    cursor: 'pointer',
    textAlign: 'left',
    transition: '.15s',
    boxShadow: '0 1px 2px rgba(15,23,42,.04)',
    textDecoration: 'none',
};

const quickIcon = (bg: string, color: string): CSSProperties => ({
    width: 42,
    height: 42,
    borderRadius: 11,
    background: bg,
    color,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
});

const quickLabel: CSSProperties = { fontSize: 13.5, fontWeight: 600, color: C.navy };

const essCard: CSSProperties = {
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 12,
    boxShadow: '0 1px 2px rgba(15,23,42,.04)',
    padding: '20px 22px',
};

const notifications = [
    { icon: 'check-check', bg: 'rgba(22,163,74,.1)', color: C.green, time: '2 jam lalu', body: <>Pengajuan cuti tahunan Anda <strong>disetujui</strong></> },
    { icon: 'wallet', bg: 'rgba(47,84,201,.1)', color: C.primary, time: 'Kemarin', body: <>Slip gaji Mei 2026 telah terbit</> },
    { icon: 'bell', bg: 'rgba(217,119,6,.1)', color: C.amber, time: '3 hari lalu', body: <>Lengkapi data NPWP Anda sebelum 30 Jun</> },
];

export default function AvanaEss() {
    return (
        <>
            <Head title="Self-Service" />
            <div style={{ padding: '28px 32px', maxWidth: 1040 }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                        <span>Beranda</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Self-Service</span>
                    </div>
                    <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Halo, Rina 👋</h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Portal pribadi Anda — Selasa, 23 Jun 2026</div>
                </div>

                {/* Quick actions */}
                <div className="avn-kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12, marginBottom: 18 }}>
                    <button
                        onClick={() => toast.success('Clock-in berhasil', { description: 'Tercatat 07:54 · Kantor Jakarta Pusat' })}
                        style={quickBtn}
                    >
                        <div style={quickIcon('rgba(47,84,201,.1)', C.primary)}>
                            <AIcon name="fingerprint" size={21} color={C.primary} />
                        </div>
                        <span style={quickLabel}>Clock-in</span>
                    </button>
                    <Link href="/avana/cuti" style={quickBtn}>
                        <div style={quickIcon('rgba(22,163,74,.1)', C.green)}>
                            <AIcon name="palmtree" size={21} color={C.green} />
                        </div>
                        <span style={quickLabel}>Ajukan Cuti</span>
                    </Link>
                    <button
                        onClick={() => toast.info('Form klaim', { description: 'Membuka formulir reimbursement…' })}
                        style={quickBtn}
                    >
                        <div style={quickIcon('rgba(217,119,6,.1)', C.amber)}>
                            <AIcon name="receipt" size={21} color={C.amber} />
                        </div>
                        <span style={quickLabel}>Klaim Reimburse</span>
                    </button>
                    <Link href="/avana/payroll" style={quickBtn}>
                        <div style={quickIcon('rgba(110,155,230,.18)', C.primary)}>
                            <AIcon name="file-text" size={21} color={C.primary} />
                        </div>
                        <span style={quickLabel}>Slip Gaji</span>
                    </Link>
                </div>

                <div className="avn-2col" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18, alignItems: 'start' }}>
                    {/* Left: clock + payslip */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                        <div style={essCard}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
                                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Kehadiran Hari Ini</div>
                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, color: C.green, background: 'rgba(22,163,74,.1)', padding: '4px 11px', borderRadius: 100 }}>
                                    <span style={{ width: 7, height: 7, borderRadius: '50%', background: C.green }} />
                                    Sudah Clock-in
                                </span>
                            </div>
                            <div style={{ display: 'flex', gap: 0 }}>
                                <div style={{ flex: 1, textAlign: 'center', padding: 12, background: C.surface, borderRadius: '10px 0 0 10px' }}>
                                    <div style={{ fontSize: 12, color: C.muted }}>Masuk</div>
                                    <div style={{ fontSize: 22, fontWeight: 700, color: C.navy, marginTop: 3, fontVariantNumeric: 'tabular-nums' }}>07:54</div>
                                </div>
                                <div style={{ flex: 1, textAlign: 'center', padding: 12, background: C.surface, borderLeft: `1px solid ${C.border}`, borderRadius: '0 10px 10px 0' }}>
                                    <div style={{ fontSize: 12, color: C.muted }}>Keluar</div>
                                    <div style={{ fontSize: 22, fontWeight: 700, color: C.faint, marginTop: 3, fontVariantNumeric: 'tabular-nums' }}>--:--</div>
                                </div>
                            </div>
                        </div>
                        <div style={essCard}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 }}>
                                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Slip Gaji Terbaru</div>
                                <Link href="/avana/payroll" style={{ fontSize: 12.5, color: C.primary, textDecoration: 'none', fontWeight: 500 }}>Lihat detail</Link>
                            </div>
                            <div style={{ fontSize: 12.5, color: C.faint, marginBottom: 14 }}>Periode Mei 2026 · dibayar 25 Mei</div>
                            <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between' }}>
                                <div>
                                    <div style={{ fontSize: 12, color: C.muted }}>Gaji Bersih</div>
                                    <div style={{ fontSize: 26, fontWeight: 700, color: C.navy, marginTop: 2, fontVariantNumeric: 'tabular-nums' }}>{rp(netTot)}</div>
                                </div>
                                <button
                                    onClick={() => toast.info('Menyiapkan export', { description: 'Slip gaji akan diunduh sebentar lagi' })}
                                    style={{ display: 'inline-flex', alignItems: 'center', gap: 7, height: 38, padding: '0 14px', background: C.surface, color: C.primary, border: 'none', borderRadius: 8, fontSize: 13, fontWeight: 600, cursor: 'pointer', transition: '.15s' }}
                                >
                                    <AIcon name="download" size={15} color={C.primary} />
                                    Unduh
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Right: leave balance + notifications */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                        <div style={essCard}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 14 }}>Saldo Cuti</div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                {leaveBalances.map((b) => (
                                    <div key={b.jenis}>
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 7 }}>
                                            <span style={{ fontSize: 13, color: C.text }}>{b.jenis}</span>
                                            <span style={{ fontSize: 13, color: C.muted }}>
                                                <span style={{ color: C.navy, fontWeight: 600 }}>{b.sisa}</span> / {b.total} hari
                                            </span>
                                        </div>
                                        <div style={{ height: 7, background: C.line, borderRadius: 4, overflow: 'hidden' }}>
                                            <div style={{ height: '100%', width: b.pct, background: b.color, borderRadius: 4 }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, boxShadow: '0 1px 2px rgba(15,23,42,.04)' }}>
                            <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.line}`, fontSize: 15, fontWeight: 600, color: C.navy }}>Notifikasi</div>
                            <div style={{ padding: '6px 22px 14px' }}>
                                {notifications.map((n, i) => (
                                    <div key={i} style={{ display: 'flex', gap: 12, padding: '11px 0', borderBottom: i < notifications.length - 1 ? '1px solid #F5F7FB' : 'none' }}>
                                        <div style={{ width: 32, height: 32, borderRadius: 8, flex: 'none', background: n.bg, color: n.color, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <AIcon name={n.icon} size={16} color={n.color} />
                                        </div>
                                        <div>
                                            <div style={{ fontSize: 13, color: C.text }}>{n.body}</div>
                                            <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>{n.time}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
