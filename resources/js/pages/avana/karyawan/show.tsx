import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { AIcon, btnOut, btnP, C, card, employees, statusBadge } from '@/lib/avana';

const empTabs = [
    { id: 'pribadi', label: 'Data Pribadi', icon: 'user' },
    { id: 'pegawai', label: 'Kepegawaian', icon: 'briefcase' },
    { id: 'dokumen', label: 'Dokumen', icon: 'folder' },
    { id: 'cuti', label: 'Cuti', icon: 'palmtree' },
    { id: 'payrolltab', label: 'Payroll', icon: 'wallet' },
] as const;

const fieldLabel: React.CSSProperties = { fontSize: 12, color: C.faint };
const fieldValue: React.CSSProperties = { fontSize: 14, color: C.text, marginTop: 3 };
const thCell: React.CSSProperties = { padding: '12px 18px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' };

export default function AvanaKaryawanShow({ id }: { id: number }) {
    const emp = employees.find((e) => e.no === id) ?? employees[0];
    const badge = statusBadge(emp.status);
    const [activeTab, setActiveTab] = useState<string>('pribadi');

    return (
        <>
            <Head title={emp.nama} />
            <div style={{ padding: '28px 32px', maxWidth: 1000 }}>
                {/* Breadcrumb */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 18 }}>
                    <Link href="/avana/karyawan" style={{ cursor: 'pointer', color: C.faint, textDecoration: 'none' }}>
                        Karyawan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{emp.nama}</span>
                </div>

                {/* Profile header card */}
                <div style={{ ...card, overflow: 'hidden', marginBottom: 18 }}>
                    <div style={{ height: 84, background: 'linear-gradient(120deg,#0E1A3A,#2F54C9)' }} />
                    <div style={{ padding: '0 26px 22px', display: 'flex', alignItems: 'flex-end', gap: 18, flexWrap: 'wrap', marginTop: -38 }}>
                        <div
                            style={{
                                width: 84,
                                height: 84,
                                borderRadius: 20,
                                background: emp.av,
                                color: '#fff',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: 30,
                                fontWeight: 600,
                                border: '4px solid #fff',
                                flex: 'none',
                            }}
                        >
                            {emp.ini}
                        </div>
                        <div style={{ flex: 1, minWidth: 200, paddingBottom: 2 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                <h1 style={{ fontSize: 21, fontWeight: 600, color: C.navy, margin: 0 }}>{emp.nama}</h1>
                                <span style={{ display: 'inline-block', padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: badge.color, background: badge.bg }}>
                                    {badge.label}
                                </span>
                            </div>
                            <div style={{ fontSize: 13.5, color: C.muted, marginTop: 3 }}>
                                {emp.jab} · {emp.dept}
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 10, paddingBottom: 4 }}>
                            <button style={btnOut}>
                                <AIcon name="message-square" size={16} />
                                Pesan
                            </button>
                            <Link href="/avana/karyawan/create" style={{ ...btnP, textDecoration: 'none' }}>
                                <AIcon name="pencil" size={16} />
                                Ubah Data
                            </Link>
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 0, padding: '0 26px', borderTop: `1px solid ${C.line}`, flexWrap: 'wrap' }}>
                        <div style={{ padding: '14px 26px 14px 0', borderRight: `1px solid ${C.line}`, paddingRight: 26 }}>
                            <div style={{ fontSize: 11.5, color: C.faint }}>ID Karyawan</div>
                            <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text, marginTop: 2 }}>EMP-00{emp.no}</div>
                        </div>
                        <div style={{ padding: '14px 26px' }}>
                            <div style={{ fontSize: 11.5, color: C.faint }}>Email</div>
                            <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text, marginTop: 2 }}>{emp.email}</div>
                        </div>
                        <div style={{ padding: '14px 26px', borderLeft: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 11.5, color: C.faint }}>Cabang</div>
                            <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text, marginTop: 2 }}>{emp.cabang}</div>
                        </div>
                        <div style={{ padding: '14px 26px', borderLeft: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 11.5, color: C.faint }}>Tgl Masuk</div>
                            <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text, marginTop: 2 }}>{emp.masuk}</div>
                        </div>
                    </div>
                </div>

                {/* Tab bar */}
                <div style={{ borderBottom: `1px solid ${C.border}`, marginBottom: 20, display: 'flex', overflowX: 'auto' }}>
                    {empTabs.map((t) => {
                        const active = activeTab === t.id;

                        return (
                            <button
                                key={t.id}
                                onClick={() => setActiveTab(t.id)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '12px 4px',
                                    marginRight: 26,
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    color: active ? C.primary : C.muted,
                                    border: 'none',
                                    borderBottom: active ? `2px solid ${C.primary}` : '2px solid transparent',
                                    cursor: 'pointer',
                                    background: 'none',
                                    whiteSpace: 'nowrap',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name={t.icon} size={15} />
                                {t.label}
                            </button>
                        );
                    })}
                </div>

                {/* Data Pribadi */}
                {activeTab === 'pribadi' && (
                    <div style={card}>
                        <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Data Pribadi</div>
                        </div>
                        <div className="avn-2col" style={{ padding: '8px 22px 18px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 0 }}>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                                <div style={fieldLabel}>NIK (KTP)</div>
                                <div style={{ ...fieldValue, display: 'flex', alignItems: 'center', gap: 8 }}>
                                    3174•••••••• 0008
                                    <AIcon name="eye-off" size={15} color={C.faint} />
                                </div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB', paddingLeft: 24 }}>
                                <div style={fieldLabel}>Tempat, Tgl Lahir</div>
                                <div style={fieldValue}>Jakarta, 14 Mar 1992</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                                <div style={fieldLabel}>Jenis Kelamin</div>
                                <div style={fieldValue}>Perempuan</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB', paddingLeft: 24 }}>
                                <div style={fieldLabel}>Agama</div>
                                <div style={fieldValue}>Islam</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                                <div style={fieldLabel}>No. Telepon</div>
                                <div style={fieldValue}>0812-3456-7890</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB', paddingLeft: 24 }}>
                                <div style={fieldLabel}>Status Pernikahan</div>
                                <div style={fieldValue}>Menikah (K/1)</div>
                            </div>
                            <div style={{ padding: '14px 0', gridColumn: '1/-1' }}>
                                <div style={fieldLabel}>Alamat Domisili</div>
                                <div style={fieldValue}>Jl. Kemang Raya No. 24, Jakarta Selatan, DKI Jakarta 12730</div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Kepegawaian */}
                {activeTab === 'pegawai' && (
                    <div style={card}>
                        <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.line}` }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Informasi Kepegawaian</div>
                        </div>
                        <div className="avn-2col" style={{ padding: '8px 22px 18px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 0 }}>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                                <div style={fieldLabel}>Status Karyawan</div>
                                <div style={fieldValue}>{emp.status}</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB', paddingLeft: 24 }}>
                                <div style={fieldLabel}>Departemen</div>
                                <div style={fieldValue}>{emp.dept}</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                                <div style={fieldLabel}>Jabatan</div>
                                <div style={fieldValue}>{emp.jab}</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB', paddingLeft: 24 }}>
                                <div style={fieldLabel}>Atasan Langsung</div>
                                <div style={fieldValue}>Rina Anggraeni</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                                <div style={fieldLabel}>Cabang</div>
                                <div style={fieldValue}>{emp.cabang}</div>
                            </div>
                            <div style={{ padding: '14px 0', borderBottom: '1px solid #F5F7FB', paddingLeft: 24 }}>
                                <div style={fieldLabel}>Tgl Bergabung</div>
                                <div style={fieldValue}>{emp.masuk}</div>
                            </div>
                            <div style={{ padding: '14px 0' }}>
                                <div style={fieldLabel}>NPWP</div>
                                <div style={{ ...fieldValue, display: 'flex', alignItems: 'center', gap: 8 }}>
                                    09.•••.•••.•-008
                                    <AIcon name="eye-off" size={15} color={C.faint} />
                                </div>
                            </div>
                            <div style={{ padding: '14px 0', paddingLeft: 24 }}>
                                <div style={fieldLabel}>No. Rekening (BCA)</div>
                                <div style={{ ...fieldValue, display: 'flex', alignItems: 'center', gap: 8 }}>
                                    •••• •••• 4821
                                    <AIcon name="eye-off" size={15} color={C.faint} />
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Dokumen */}
                {activeTab === 'dokumen' && (
                    <div style={{ ...card, padding: '14px 22px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <div style={{ width: 38, height: 38, borderRadius: 9, background: 'rgba(220,38,38,.08)', color: C.red, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name="file-text" size={18} color={C.red} />
                                </div>
                                <div>
                                    <div style={{ fontSize: 13.5, fontWeight: 500, color: C.text }}>Kontrak Kerja 2026.pdf</div>
                                    <div style={{ fontSize: 12, color: C.faint }}>1,2 MB · diunggah 12 Jan 2026</div>
                                </div>
                            </div>
                            <button style={{ width: 34, height: 34, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, cursor: 'pointer', color: C.muted }}>
                                <AIcon name="download" size={16} color={C.muted} />
                            </button>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 0', borderBottom: '1px solid #F5F7FB' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <div style={{ width: 38, height: 38, borderRadius: 9, background: 'rgba(47,84,201,.1)', color: C.primary, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name="image" size={18} color={C.primary} />
                                </div>
                                <div>
                                    <div style={{ fontSize: 13.5, fontWeight: 500, color: C.text }}>Scan KTP.jpg</div>
                                    <div style={{ fontSize: 12, color: C.faint }}>820 KB · diunggah 10 Jan 2026</div>
                                </div>
                            </div>
                            <button style={{ width: 34, height: 34, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, cursor: 'pointer', color: C.muted }}>
                                <AIcon name="download" size={16} color={C.muted} />
                            </button>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 0' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <div style={{ width: 38, height: 38, borderRadius: 9, background: 'rgba(22,163,74,.1)', color: C.green, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name="file-text" size={18} color={C.green} />
                                </div>
                                <div>
                                    <div style={{ fontSize: 13.5, fontWeight: 500, color: C.text }}>Ijazah S1.pdf</div>
                                    <div style={{ fontSize: 12, color: C.faint }}>2,4 MB · diunggah 10 Jan 2026</div>
                                </div>
                            </div>
                            <button style={{ width: 34, height: 34, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, cursor: 'pointer', color: C.muted }}>
                                <AIcon name="download" size={16} color={C.muted} />
                            </button>
                        </div>
                    </div>
                )}

                {/* Cuti */}
                {activeTab === 'cuti' && (
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Jenis</th>
                                    <th style={{ ...thCell, padding: '12px 16px' }}>Tanggal</th>
                                    <th style={{ ...thCell, padding: '12px 16px' }}>Durasi</th>
                                    <th style={thCell}>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                    <td style={{ padding: '13px 18px', fontSize: 13, color: C.text }}>Cuti Tahunan</td>
                                    <td style={{ padding: '13px 16px', fontSize: 13, color: C.muted }}>12–14 Mar 2026</td>
                                    <td style={{ padding: '13px 16px', fontSize: 13, color: C.muted }}>3 hari</td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: C.green, background: 'rgba(22,163,74,.1)' }}>Disetujui</span>
                                    </td>
                                </tr>
                                <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                    <td style={{ padding: '13px 18px', fontSize: 13, color: C.text }}>Cuti Sakit</td>
                                    <td style={{ padding: '13px 16px', fontSize: 13, color: C.muted }}>05 Feb 2026</td>
                                    <td style={{ padding: '13px 16px', fontSize: 13, color: C.muted }}>1 hari</td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: C.green, background: 'rgba(22,163,74,.1)' }}>Disetujui</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Payroll */}
                {activeTab === 'payrolltab' && (
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Periode</th>
                                    <th style={{ ...thCell, padding: '12px 16px', textAlign: 'right' }}>Gaji Bersih</th>
                                    <th style={thCell}>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                    <td style={{ padding: '13px 18px', fontSize: 13, color: C.text }}>Mei 2026</td>
                                    <td style={{ padding: '13px 16px', fontSize: 13, color: C.text, textAlign: 'right', fontVariantNumeric: 'tabular-nums' }}>Rp 11.323.000</td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: C.green, background: 'rgba(22,163,74,.1)' }}>Dibayar</span>
                                    </td>
                                </tr>
                                <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                    <td style={{ padding: '13px 18px', fontSize: 13, color: C.text }}>April 2026</td>
                                    <td style={{ padding: '13px 16px', fontSize: 13, color: C.text, textAlign: 'right', fontVariantNumeric: 'tabular-nums' }}>Rp 11.323.000</td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: C.green, background: 'rgba(22,163,74,.1)' }}>Dibayar</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
