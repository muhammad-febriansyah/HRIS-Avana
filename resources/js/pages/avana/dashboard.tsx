import { Head, Link } from '@inertiajs/react';
import { toast } from 'sonner';
import { activities, AIcon, approvals, btnOut, btnP, C, card, kpis } from '@/lib/avana';

function HeadcountChart() {
    const hc = [1180, 1192, 1205, 1218, 1231, 1248];
    const hcl = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'];
    const minH = 1160,
        maxH = 1260,
        W = 420,
        H = 150,
        pad = 8;
    const pts = hc.map((v, i) => {
        const x = pad + i * ((W - pad * 2) / (hc.length - 1));
        const y = H - 12 - ((v - minH) / (maxH - minH)) * (H - 40);

        return [x, y] as const;
    });
    const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const area = line + ` L${pts[pts.length - 1][0].toFixed(1)} ${H - 12} L${pts[0][0].toFixed(1)} ${H - 12} Z`;

    return (
        <svg viewBox={`0 0 ${W} ${H}`} style={{ width: '100%', height: 170, overflow: 'visible' }}>
            <defs>
                <linearGradient id="hcg" x1={0} y1={0} x2={0} y2={1}>
                    <stop offset="0%" stopColor="#2F54C9" stopOpacity={0.18} />
                    <stop offset="100%" stopColor="#2F54C9" stopOpacity={0} />
                </linearGradient>
            </defs>
            <path d={area} fill="url(#hcg)" />
            <path d={line} fill="none" stroke="#2F54C9" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
            {pts.map((p, i) => (
                <circle key={i} cx={p[0]} cy={p[1]} r={3.5} fill="#fff" stroke="#2F54C9" strokeWidth={2} />
            ))}
            {hcl.map((l, i) => (
                <text key={'t' + i} x={pts[i][0]} y={H + 4} fontSize={11} fill="#9CA3AF" textAnchor="middle" fontFamily="Poppins">
                    {l}
                </text>
            ))}
        </svg>
    );
}

function AttendanceChart() {
    const att = [96, 94, 97, 93, 89];
    const attl = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];

    return (
        <div style={{ display: 'flex', gap: 16, alignItems: 'flex-end', height: 200, paddingTop: 10 }}>
            {att.map((v, i) => (
                <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 9, height: '100%', justifyContent: 'flex-end' }}>
                    <div style={{ fontSize: 12, fontWeight: 600, color: C.navy }}>{v}%</div>
                    <div style={{ width: '100%', maxWidth: 34, height: v * 1.5, background: i === 4 ? C.amber : 'linear-gradient(180deg,#6E9BE6,#2F54C9)', borderRadius: '7px 7px 0 0' }} />
                    <div style={{ fontSize: 11.5, color: C.faint }}>{attl[i]}</div>
                </div>
            ))}
        </div>
    );
}

export default function AvanaDashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 24 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Dashboard</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Selamat datang kembali, Rina 👋</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Ringkasan aktivitas HR — Selasa, 23 Jun 2026</div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button onClick={() => toast.info('Menyiapkan export', { description: 'File akan diunduh sebentar lagi' })} style={btnOut}>
                            <AIcon name="download" size={16} />
                            Export
                        </button>
                        <Link href="/avana/karyawan/create" style={{ ...btnP, textDecoration: 'none' }}>
                            <AIcon name="plus" size={16} />
                            Tambah Karyawan
                        </Link>
                    </div>
                </div>

                {/* KPI */}
                <div className="avn-kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 16, marginBottom: 20 }}>
                    {kpis.map((k, i) => (
                        <div key={i} style={{ ...card, padding: '18px 20px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <div style={{ width: 40, height: 40, borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', background: k.iconBg, color: k.iconColor }}>
                                    <AIcon name={k.icon} size={20} color={k.iconColor} />
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 3, fontSize: 12, fontWeight: 600, color: k.deltaColor }}>
                                    <AIcon name={k.deltaIcon} size={13} color={k.deltaColor} />
                                    {k.delta}
                                </div>
                            </div>
                            <div style={{ fontSize: 27, fontWeight: 700, color: C.navy, marginTop: 14, letterSpacing: '-.01em' }}>{k.value}</div>
                            <div style={{ fontSize: 13, color: C.muted, marginTop: 2 }}>{k.label}</div>
                        </div>
                    ))}
                </div>

                {/* Charts row */}
                <div className="avn-2col" style={{ display: 'grid', gridTemplateColumns: '1.6fr 1fr', gap: 16, marginBottom: 20 }}>
                    <div style={card}>
                        <div style={{ padding: '18px 20px', borderBottom: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div>
                                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Tren Headcount</div>
                                <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>6 bulan terakhir</div>
                            </div>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12, color: C.muted }}>
                                <span style={{ width: 9, height: 9, borderRadius: '50%', background: C.primary }} />
                                Total
                            </span>
                        </div>
                        <div style={{ padding: 20 }}>
                            <HeadcountChart />
                        </div>
                    </div>
                    <div style={card}>
                        <div style={{ padding: '18px 20px', borderBottom: `1px solid ${C.border}` }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Kehadiran Minggu Ini</div>
                            <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>Persentase hadir per hari</div>
                        </div>
                        <div style={{ padding: 20 }}>
                            <AttendanceChart />
                        </div>
                    </div>
                </div>

                {/* Activity + Approvals */}
                <div className="avn-2col" style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 16 }}>
                    <div style={card}>
                        <div style={{ padding: '18px 20px', borderBottom: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Aktivitas Terbaru</div>
                            <a href="#" style={{ fontSize: 12.5, color: C.primary, textDecoration: 'none', fontWeight: 500 }}>Lihat semua</a>
                        </div>
                        <div style={{ padding: '6px 20px 14px' }}>
                            {activities.map((a, i) => (
                                <div key={i} style={{ display: 'flex', gap: 13, padding: '12px 0', borderBottom: `1px solid ${C.line}` }}>
                                    <div style={{ width: 34, height: 34, borderRadius: 9, flex: 'none', display: 'flex', alignItems: 'center', justifyContent: 'center', background: a.bg, color: a.color }}>
                                        <AIcon name={a.icon} size={16} color={a.color} />
                                    </div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 13.5, color: C.text }}>{a.text}</div>
                                        <div style={{ fontSize: 12, color: C.faint, marginTop: 2 }}>{a.time}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div style={card}>
                        <div style={{ padding: '18px 20px', borderBottom: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Menunggu Persetujuan</div>
                            <span style={{ background: 'rgba(217,119,6,.1)', color: C.amber, fontSize: 11.5, fontWeight: 600, padding: '3px 9px', borderRadius: 100 }}>4 baru</span>
                        </div>
                        <div style={{ padding: '6px 20px 16px' }}>
                            {approvals.map((ap, i) => (
                                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 0', borderBottom: `1px solid ${C.line}` }}>
                                    <div style={{ width: 34, height: 34, borderRadius: '50%', flex: 'none', background: ap.avBg, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12.5, fontWeight: 600 }}>{ap.ini}</div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 13, fontWeight: 500, color: C.text, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{ap.name}</div>
                                        <div style={{ fontSize: 12, color: C.faint }}>{ap.type}</div>
                                    </div>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button onClick={() => toast.success('Disetujui', { description: `${ap.name} — pengajuan disetujui` })} style={{ width: 30, height: 30, border: 'none', borderRadius: 7, background: 'rgba(22,163,74,.1)', color: C.green, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <AIcon name="check" size={15} color={C.green} />
                                        </button>
                                        <button onClick={() => toast.error('Ditolak', { description: `${ap.name} — pengajuan ditolak` })} style={{ width: 30, height: 30, border: 'none', borderRadius: 7, background: 'rgba(220,38,38,.08)', color: C.red, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <AIcon name="x" size={15} color={C.red} />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
