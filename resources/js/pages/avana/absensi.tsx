import { Head } from '@inertiajs/react';
import { toast } from 'sonner';
import { AIcon, attRows, attStatusColor, btnOut, C } from '@/lib/avana';

export default function AvanaAbsensi() {
    return (
        <>
            <Head title="Absensi" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Absensi</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Absensi</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Selasa, 23 Jun 2026 · Periode harian</div>
                    </div>
                    <button onClick={() => toast.info('Menyiapkan export', { description: 'File akan diunduh sebentar lagi' })} style={btnOut}>
                        <AIcon name="download" size={16} />
                        Export Rekap
                    </button>
                </div>

                <div className="avn-abs" style={{ display: 'grid', gridTemplateColumns: '340px 1fr', gap: 18, alignItems: 'start' }}>
                    {/* Clock panel */}
                    <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, boxShadow: '0 1px 2px rgba(15,23,42,.04)', overflow: 'hidden' }}>
                        <div style={{ position: 'relative', height: 170, background: 'linear-gradient(135deg,#eef2fb,#dde6f7)', overflow: 'hidden' }}>
                            <div
                                style={{
                                    position: 'absolute',
                                    inset: 0,
                                    backgroundImage:
                                        'linear-gradient(rgba(47,84,201,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(47,84,201,.07) 1px,transparent 1px)',
                                    backgroundSize: '26px 26px',
                                }}
                            />
                            <div style={{ position: 'absolute', top: 42, left: 60, width: 120, height: 14, background: 'rgba(110,155,230,.45)', borderRadius: 7, transform: 'rotate(-18deg)' }} />
                            <div style={{ position: 'absolute', top: 96, left: 130, width: 160, height: 12, background: 'rgba(110,155,230,.35)', borderRadius: 6, transform: 'rotate(8deg)' }} />
                            <div style={{ position: 'absolute', top: 62, left: '50%', transform: 'translateX(-50%)', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                <div
                                    style={{
                                        width: 42,
                                        height: 42,
                                        borderRadius: '50% 50% 50% 0',
                                        background: C.red,
                                        transform: 'rotate(-45deg)',
                                        boxShadow: '0 4px 10px rgba(220,38,38,.35)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <div style={{ width: 14, height: 14, borderRadius: '50%', background: '#fff', transform: 'rotate(45deg)' }} />
                                </div>
                            </div>
                            <div
                                style={{
                                    position: 'absolute',
                                    bottom: 12,
                                    left: 12,
                                    right: 12,
                                    background: 'rgba(255,255,255,.92)',
                                    borderRadius: 8,
                                    padding: '8px 11px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <AIcon name="map-pin" size={15} color={C.primary} />
                                <span style={{ fontSize: 12, color: C.text }}>Kantor Pusat — Jl. Sudirman Kav. 52, Jakarta</span>
                            </div>
                        </div>
                        <div style={{ padding: 22, textAlign: 'center' }}>
                            <div style={{ fontSize: 13, color: C.muted }}>Waktu saat ini</div>
                            <div style={{ fontSize: 38, fontWeight: 700, color: C.navy, letterSpacing: '-.02em', fontVariantNumeric: 'tabular-nums', marginTop: 2 }}>
                                07:54<span style={{ fontSize: 18, color: C.faint }}>:21</span>
                            </div>
                            <div
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    background: 'rgba(22,163,74,.1)',
                                    color: C.green,
                                    fontSize: 12,
                                    fontWeight: 600,
                                    padding: '4px 11px',
                                    borderRadius: 100,
                                    marginTop: 10,
                                }}
                            >
                                <span style={{ width: 7, height: 7, borderRadius: '50%', background: C.green }} />
                                Dalam radius kantor
                            </div>
                            <button
                                onClick={() => toast.success('Clock-in berhasil', { description: 'Tercatat 07:54 · Kantor Jakarta Pusat' })}
                                style={{
                                    width: '100%',
                                    height: 54,
                                    marginTop: 18,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 10,
                                    fontSize: 15.5,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 9,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="fingerprint" size={21} />
                                Clock In Sekarang
                            </button>
                            <button
                                style={{
                                    width: '100%',
                                    height: 46,
                                    marginTop: 10,
                                    background: '#fff',
                                    color: C.red,
                                    border: '1px solid #F1D5D5',
                                    borderRadius: 10,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="log-out" size={18} />
                                Clock Out
                            </button>
                        </div>
                    </div>

                    {/* Rekap */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div className="avn-kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12 }}>
                            <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, padding: '15px 16px' }}>
                                <div style={{ fontSize: 12.5, color: C.muted }}>Hadir</div>
                                <div style={{ fontSize: 22, fontWeight: 700, color: C.green, marginTop: 3 }}>1.156</div>
                            </div>
                            <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, padding: '15px 16px' }}>
                                <div style={{ fontSize: 12.5, color: C.muted }}>Terlambat</div>
                                <div style={{ fontSize: 22, fontWeight: 700, color: C.amber, marginTop: 3 }}>42</div>
                            </div>
                            <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, padding: '15px 16px' }}>
                                <div style={{ fontSize: 12.5, color: C.muted }}>Cuti / Izin</div>
                                <div style={{ fontSize: 22, fontWeight: 700, color: C.primary, marginTop: 3 }}>38</div>
                            </div>
                            <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, padding: '15px 16px' }}>
                                <div style={{ fontSize: 12.5, color: C.muted }}>Alpa</div>
                                <div style={{ fontSize: 22, fontWeight: 700, color: C.red, marginTop: 3 }}>12</div>
                            </div>
                        </div>

                        <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, boxShadow: '0 1px 2px rgba(15,23,42,.04)', overflow: 'hidden' }}>
                            <div style={{ padding: '16px 18px', borderBottom: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Rekap Kehadiran Harian</div>
                                <div style={{ position: 'relative' }}>
                                    <span style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', display: 'flex' }}>
                                        <AIcon name="search" size={15} color={C.faint} />
                                    </span>
                                    <input
                                        placeholder="Cari karyawan…"
                                        style={{ height: 36, width: 180, padding: '0 12px 0 34px', background: C.surface, border: '1px solid transparent', borderRadius: 8, fontSize: 13, outline: 'none' }}
                                    />
                                </div>
                            </div>
                            <div style={{ overflowX: 'auto' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 620 }}>
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={{ padding: '11px 18px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Karyawan</th>
                                            <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Shift</th>
                                            <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Masuk</th>
                                            <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Keluar</th>
                                            <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Telat</th>
                                            <th style={{ padding: '11px 18px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {attRows.map((r, i) => {
                                            const [col, bg] = attStatusColor(r.status);

                                            return (
                                                <tr key={i} style={{ borderTop: `1px solid ${C.line}` }}>
                                                    <td style={{ padding: '12px 18px' }}>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                            <div
                                                                style={{
                                                                    width: 32,
                                                                    height: 32,
                                                                    borderRadius: '50%',
                                                                    flex: 'none',
                                                                    background: r.av,
                                                                    color: '#fff',
                                                                    display: 'flex',
                                                                    alignItems: 'center',
                                                                    justifyContent: 'center',
                                                                    fontSize: 11.5,
                                                                    fontWeight: 600,
                                                                }}
                                                            >
                                                                {r.ini}
                                                            </div>
                                                            <span style={{ fontSize: 13, fontWeight: 500, color: C.text }}>{r.nama}</span>
                                                        </div>
                                                    </td>
                                                    <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.muted }}>{r.shift}</td>
                                                    <td style={{ padding: '12px 16px', fontSize: 13, color: C.text, fontVariantNumeric: 'tabular-nums' }}>{r.masuk}</td>
                                                    <td style={{ padding: '12px 16px', fontSize: 13, color: C.text, fontVariantNumeric: 'tabular-nums' }}>{r.keluar}</td>
                                                    <td style={{ padding: '12px 16px', fontSize: 13, color: C.muted }}>{r.telat}</td>
                                                    <td style={{ padding: '12px 18px' }}>
                                                        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: col, background: bg }}>{r.status}</span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
