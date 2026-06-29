import { Head } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { AIcon, C, card, leaveBalances, leaveList } from '@/lib/avana';

export default function AvanaCuti() {
    function submitLeave(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        toast.success('Pengajuan terkirim', { description: 'Menunggu persetujuan atasan' });
    }

    return (
        <>
            <Head title="Cuti & Lembur" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Cuti &amp; Lembur</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Cuti &amp; Lembur</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Ajukan cuti dan kelola persetujuan tim Anda</div>
                    </div>
                </div>

                {/* Saldo */}
                <div className="avn-3col" style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 16, marginBottom: 18 }}>
                    {leaveBalances.map((b, i) => (
                        <div key={i} style={{ ...card, padding: '18px 20px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                    <div style={{ width: 38, height: 38, borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', background: b.color + '1a', color: b.color }}>
                                        <AIcon name={b.icon} size={19} color={b.color} />
                                    </div>
                                    <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy }}>{b.jenis}</div>
                                </div>
                            </div>
                            <div style={{ marginTop: 14, display: 'flex', alignItems: 'baseline', gap: 6 }}>
                                <span style={{ fontSize: 28, fontWeight: 700, color: C.navy }}>{b.sisa}</span>
                                <span style={{ fontSize: 13, color: C.faint }}>/ {b.total} hari tersisa</span>
                            </div>
                            <div style={{ height: 7, background: C.line, borderRadius: 4, marginTop: 10, overflow: 'hidden' }}>
                                <div style={{ height: '100%', width: b.pct, background: b.color, borderRadius: 4 }} />
                            </div>
                        </div>
                    ))}
                </div>

                <div className="avn-abs" style={{ display: 'grid', gridTemplateColumns: '380px 1fr', gap: 18, alignItems: 'start' }}>
                    {/* Form pengajuan */}
                    <div style={card}>
                        <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Ajukan Cuti</div>
                            <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3 }}>Lengkapi formulir di bawah ini.</div>
                        </div>
                        <form onSubmit={submitLeave} style={{ padding: '20px 22px', display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <div>
                                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7 }}>
                                    Jenis Cuti <span style={{ color: C.red }}>*</span>
                                </label>
                                <select style={{ width: '100%', height: 42, padding: '0 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.muted, background: '#fff', outline: 'none', cursor: 'pointer' }}>
                                    <option value="">Pilih jenis cuti</option>
                                    <option>Cuti Tahunan</option>
                                    <option>Cuti Sakit</option>
                                    <option>Cuti Penting</option>
                                </select>
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                <div>
                                    <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7 }}>
                                        Mulai <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input type="date" style={{ width: '100%', height: 42, padding: '0 11px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 12.5, color: C.muted, outline: 'none' }} />
                                </div>
                                <div>
                                    <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7 }}>
                                        Selesai <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input type="date" style={{ width: '100%', height: 42, padding: '0 11px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 12.5, color: C.muted, outline: 'none' }} />
                                </div>
                            </div>
                            <div style={{ background: C.surface, borderRadius: 8, padding: '11px 13px', display: 'flex', alignItems: 'center', gap: 9, fontSize: 12.5, color: C.muted }}>
                                <AIcon name="info" size={15} color={C.primary} />
                                Total&nbsp;<span style={{ color: C.text, fontWeight: 600 }}>3 hari kerja</span>&nbsp;· sisa saldo 8 hari
                            </div>
                            <div>
                                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7 }}>
                                    Alasan <span style={{ color: C.red }}>*</span>
                                </label>
                                <textarea placeholder="Tuliskan alasan pengajuan cuti" rows={3} style={{ width: '100%', padding: '11px 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, outline: 'none', resize: 'vertical' }} />
                            </div>
                            <div>
                                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7 }}>Lampiran</label>
                                <div style={{ border: '1.5px dashed #D5DCEA', borderRadius: 9, padding: 18, textAlign: 'center', cursor: 'pointer', transition: '.15s' }}>
                                    <AIcon name="upload-cloud" size={22} color={C.faint} />
                                    <div style={{ fontSize: 12.5, color: C.muted, marginTop: 6 }}>
                                        Klik untuk unggah <span style={{ color: C.primary, fontWeight: 500 }}>surat keterangan</span>
                                    </div>
                                    <div style={{ fontSize: 11, color: C.faint, marginTop: 2 }}>PDF / JPG · maks 5 MB</div>
                                </div>
                            </div>
                            <button type="submit" style={{ width: '100%', height: 44, background: C.primary, color: '#fff', border: 'none', borderRadius: 8, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, transition: '.15s' }}>
                                <AIcon name="send" size={16} color="#fff" />
                                Kirim Pengajuan
                            </button>
                        </form>
                    </div>

                    {/* List + approval */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div style={{ padding: '16px 18px', borderBottom: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Pengajuan Tim</div>
                            <div style={{ display: 'flex', gap: 4, background: C.surface, padding: 3, borderRadius: 8 }}>
                                <span style={{ fontSize: 12.5, fontWeight: 600, color: '#fff', background: C.primary, padding: '5px 12px', borderRadius: 6 }}>Menunggu</span>
                                <span style={{ fontSize: 12.5, fontWeight: 500, color: C.muted, padding: '5px 12px', cursor: 'pointer' }}>Semua</span>
                            </div>
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 640 }}>
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={{ padding: '11px 18px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Karyawan</th>
                                        <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Jenis</th>
                                        <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Tanggal</th>
                                        <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Status</th>
                                        <th style={{ padding: '11px 18px', textAlign: 'right', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {leaveList.map((l, i) => (
                                        <tr key={i} style={{ borderTop: `1px solid ${C.line}` }}>
                                            <td style={{ padding: '12px 18px' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                    <div style={{ width: 32, height: 32, borderRadius: '50%', flex: 'none', background: l.av, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11.5, fontWeight: 600 }}>{l.ini}</div>
                                                    <div>
                                                        <div style={{ fontSize: 13, fontWeight: 500, color: C.text }}>{l.nama}</div>
                                                        <div style={{ fontSize: 11.5, color: C.faint }}>{l.durasi}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style={{ padding: '12px 16px', fontSize: 13, color: C.muted }}>{l.jenis}</td>
                                            <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>
                                                {l.mulai} – {l.akhir}
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: l.badge.color, background: l.badge.bg }}>{l.badge.label}</span>
                                            </td>
                                            <td style={{ padding: '12px 18px', textAlign: 'right' }}>
                                                <div style={{ display: 'inline-flex', gap: 6 }}>
                                                    <button onClick={() => toast.success('Disetujui', { description: 'Cuti ' + l.nama + ' disetujui' })} style={{ display: 'inline-flex', alignItems: 'center', gap: 5, height: 30, padding: '0 11px', border: 'none', borderRadius: 7, background: 'rgba(22,163,74,.1)', color: C.green, fontSize: 12, fontWeight: 600, cursor: 'pointer', transition: '.15s' }}>
                                                        <AIcon name="check" size={14} color={C.green} />
                                                        Setujui
                                                    </button>
                                                    <button onClick={() => toast.error('Ditolak', { description: 'Cuti ' + l.nama + ' ditolak' })} style={{ width: 30, height: 30, border: 'none', borderRadius: 7, background: 'rgba(220,38,38,.08)', color: C.red, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', transition: '.15s' }}>
                                                        <AIcon name="x" size={14} color={C.red} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
