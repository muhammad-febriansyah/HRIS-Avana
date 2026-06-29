import { Head } from '@inertiajs/react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, card, dedTot, grossTot, netTot, payPeriods, rp, slipDed, slipEarn } from '@/lib/avana';

export default function AvanaPayroll() {
    return (
        <>
            <Head title="Payroll" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Payroll</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Payroll</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Kelola penggajian &amp; slip gaji karyawan</div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button onClick={() => toast.info('Menyiapkan export', { description: 'File akan diunduh sebentar lagi' })} style={btnOut}>
                            <AIcon name="download" size={16} />
                            Export
                        </button>
                        <button onClick={() => toast.info('Menjalankan payroll', { description: 'Menghitung 1.248 karyawan…' })} style={btnP}>
                            <AIcon name="play" size={16} />
                            Jalankan Payroll
                        </button>
                    </div>
                </div>

                {/* Run summary */}
                <div style={{ ...card, marginBottom: 18, overflow: 'hidden' }}>
                    <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.line}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 10 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
                            <div style={{ width: 40, height: 40, borderRadius: 10, background: 'rgba(47,84,201,.1)', color: C.primary, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <AIcon name="calendar" size={20} color={C.primary} />
                            </div>
                            <div>
                                <div style={{ fontSize: 16, fontWeight: 600, color: C.navy }}>Periode Juni 2026</div>
                                <div style={{ fontSize: 12.5, color: C.muted }}>Tanggal bayar 25 Jun 2026 · 1.248 karyawan</div>
                            </div>
                        </div>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '5px 12px', borderRadius: 100, fontSize: 12, fontWeight: 600, color: C.muted, background: 'rgba(107,114,128,.12)' }}>
                            <AIcon name="circle-dot" size={13} />
                            Draft
                        </span>
                    </div>
                    <div className="avn-kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 0 }}>
                        <div style={{ padding: '20px 22px', borderRight: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 12.5, color: C.muted }}>Total Gross</div>
                            <div style={{ fontSize: 21, fontWeight: 700, color: C.navy, marginTop: 5, fontVariantNumeric: 'tabular-nums' }}>Rp 5.120.000.000</div>
                        </div>
                        <div style={{ padding: '20px 22px', borderRight: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 12.5, color: C.muted }}>Total Potongan</div>
                            <div style={{ fontSize: 21, fontWeight: 700, color: C.amber, marginTop: 5, fontVariantNumeric: 'tabular-nums' }}>Rp 186.000.000</div>
                        </div>
                        <div style={{ padding: '20px 22px', borderRight: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 12.5, color: C.muted }}>Total Pajak (PPh 21)</div>
                            <div style={{ fontSize: 21, fontWeight: 700, color: C.red, marginTop: 5, fontVariantNumeric: 'tabular-nums' }}>Rp 114.000.000</div>
                        </div>
                        <div style={{ padding: '20px 22px', background: '#FAFBFE' }}>
                            <div style={{ fontSize: 12.5, color: C.muted }}>Total Net (Take Home)</div>
                            <div style={{ fontSize: 21, fontWeight: 700, color: C.green, marginTop: 5, fontVariantNumeric: 'tabular-nums' }}>Rp 4.820.000.000</div>
                        </div>
                    </div>
                </div>

                <div className="avn-2col" style={{ display: 'grid', gridTemplateColumns: '1.25fr 1fr', gap: 18, alignItems: 'start' }}>
                    {/* Periods */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div style={{ padding: '16px 18px', borderBottom: `1px solid ${C.border}`, fontSize: 15, fontWeight: 600, color: C.navy }}>Riwayat Periode</div>
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 480 }}>
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={{ padding: '11px 18px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Periode</th>
                                        <th style={{ padding: '11px 16px', textAlign: 'right', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Net</th>
                                        <th style={{ padding: '11px 16px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Status</th>
                                        <th style={{ padding: '11px 18px', textAlign: 'right', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payPeriods.map((p) => (
                                        <tr key={p.periode} style={{ borderTop: `1px solid ${C.line}`, transition: '.15s' }}>
                                            <td style={{ padding: '13px 18px' }}>
                                                <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy }}>{p.periode}</div>
                                                <div style={{ fontSize: 11.5, color: C.faint }}>{p.karyawan} karyawan</div>
                                            </td>
                                            <td style={{ padding: '13px 16px', textAlign: 'right', fontSize: 13, color: C.text, fontVariantNumeric: 'tabular-nums' }}>{p.netR}</td>
                                            <td style={{ padding: '13px 16px' }}>
                                                <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: p.badge.color, background: p.badge.bg }}>{p.badge.label}</span>
                                            </td>
                                            <td style={{ padding: '13px 18px', textAlign: 'right' }}>
                                                <button style={{ width: 30, height: 30, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 7, cursor: 'pointer', color: C.muted, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
                                                    <AIcon name="chevron-right" size={16} />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Slip gaji detail */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.line}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
                                <div style={{ width: 38, height: 38, borderRadius: '50%', background: C.primary, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 600 }}>PA</div>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: C.navy }}>Putri Anjani</div>
                                    <div style={{ fontSize: 11.5, color: C.faint }}>HR Manager · Juni 2026</div>
                                </div>
                            </div>
                            <button onClick={() => toast.info('Menyiapkan export', { description: 'File akan diunduh sebentar lagi' })} style={{ width: 34, height: 34, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, cursor: 'pointer', color: C.primary, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
                                <AIcon name="printer" size={16} color={C.primary} />
                            </button>
                        </div>
                        <div style={{ padding: '18px 22px' }}>
                            <div style={{ fontSize: 11.5, fontWeight: 600, color: C.green, textTransform: 'uppercase', letterSpacing: '.04em', marginBottom: 8 }}>Pendapatan</div>
                            {slipEarn.map((e) => (
                                <div key={e.k} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '7px 0' }}>
                                    <span style={{ fontSize: 13, color: C.muted }}>{e.k}</span>
                                    <span style={{ fontSize: 13, color: C.text, fontVariantNumeric: 'tabular-nums' }}>{e.v}</span>
                                </div>
                            ))}
                            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderTop: `1px solid ${C.line}`, marginTop: 4 }}>
                                <span style={{ fontSize: 13, fontWeight: 600, color: C.text }}>Total Pendapatan</span>
                                <span style={{ fontSize: 13, fontWeight: 600, color: C.green, fontVariantNumeric: 'tabular-nums' }}>{rp(grossTot)}</span>
                            </div>

                            <div style={{ fontSize: 11.5, fontWeight: 600, color: C.red, textTransform: 'uppercase', letterSpacing: '.04em', margin: '14px 0 8px' }}>Potongan</div>
                            {slipDed.map((d) => (
                                <div key={d.k} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '7px 0' }}>
                                    <span style={{ fontSize: 13, color: C.muted }}>{d.k}</span>
                                    <span style={{ fontSize: 13, color: C.text, fontVariantNumeric: 'tabular-nums' }}>- {d.v}</span>
                                </div>
                            ))}
                            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderTop: `1px solid ${C.line}`, marginTop: 4 }}>
                                <span style={{ fontSize: 13, fontWeight: 600, color: C.text }}>Total Potongan</span>
                                <span style={{ fontSize: 13, fontWeight: 600, color: C.red, fontVariantNumeric: 'tabular-nums' }}>- {rp(dedTot)}</span>
                            </div>

                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: 'linear-gradient(120deg,#0E1A3A,#2F54C9)', borderRadius: 10, padding: '15px 18px', marginTop: 16 }}>
                                <span style={{ fontSize: 13.5, fontWeight: 600, color: 'rgba(255,255,255,.85)' }}>Gaji Bersih (Take Home Pay)</span>
                                <span style={{ fontSize: 19, fontWeight: 700, color: '#fff', fontVariantNumeric: 'tabular-nums' }}>{rp(netTot)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
