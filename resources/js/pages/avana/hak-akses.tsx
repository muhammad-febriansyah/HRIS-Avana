import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnP, C, card, permHeaders, permRows, roles } from '@/lib/avana';

export default function AvanaHakAkses() {
    const [matrix, setMatrix] = useState<boolean[][]>(() => permRows.map((r) => r.perms));

    const toggleCell = (rowIdx: number, colIdx: number) => {
        setMatrix((prev) => prev.map((row, ri) => (ri === rowIdx ? row.map((on, ci) => (ci === colIdx ? !on : on)) : row)));
        toast.info('Hak akses diperbarui', { description: permRows[rowIdx].modul + ' · ' + permHeaders[colIdx] });
    };

    return (
        <>
            <Head title="Hak Akses" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Pengaturan</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Hak Akses</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Hak Akses & Peran</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Atur izin tiap peran terhadap modul & aksi</div>
                    </div>
                    <button onClick={() => toast.success('Role dibuat', { description: 'Role kustom baru ditambahkan' })} style={btnP}>
                        <AIcon name="plus" size={16} />
                        Buat Role Kustom
                    </button>
                </div>

                {/* Roles */}
                <div className="avn-kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 14, marginBottom: 18 }}>
                    {roles.map((r, i) => (
                        <div key={i} style={{ ...card, padding: '16px 18px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <div style={{ width: 36, height: 36, borderRadius: 9, display: 'flex', alignItems: 'center', justifyContent: 'center', background: r.color + '1a', color: r.color }}>
                                    <AIcon name="shield" size={18} color={r.color} />
                                </div>
                                <div style={{ fontSize: 14, fontWeight: 600, color: C.navy }}>{r.name}</div>
                            </div>
                            <div style={{ fontSize: 12, color: C.muted, marginTop: 10, lineHeight: 1.45, minHeight: 34 }}>{r.desc}</div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: C.faint, marginTop: 8, paddingTop: 10, borderTop: `1px solid ${C.line}` }}>
                                <AIcon name="users" size={14} />
                                {r.users} pengguna
                            </div>
                        </div>
                    ))}
                </div>

                {/* Permission matrix */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ padding: '16px 18px', borderBottom: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Matriks Izin · Modul × Peran</div>
                        <div style={{ fontSize: 12, color: C.faint, display: 'flex', alignItems: 'center', gap: 6 }}>
                            <AIcon name="info" size={14} />
                            Klik sel untuk mengubah izin
                        </div>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 620 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={{ padding: '13px 18px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: C.faint, textTransform: 'uppercase' }}>Modul / Menu</th>
                                    {permHeaders.map((h, i) => (
                                        <th key={i} style={{ padding: '13px 16px', textAlign: 'center', fontSize: 11.5, fontWeight: 600, color: C.muted, textTransform: 'uppercase', whiteSpace: 'nowrap' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {permRows.map((row, rowIdx) => (
                                    <tr key={rowIdx} style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td style={{ padding: '13px 18px', fontSize: 13.5, fontWeight: 500, color: C.text }}>{row.modul}</td>
                                        {matrix[rowIdx].map((on, colIdx) => (
                                            <td key={colIdx} style={{ padding: '11px 16px', textAlign: 'center' }}>
                                                <button
                                                    onClick={() => toggleCell(rowIdx, colIdx)}
                                                    style={{
                                                        width: 30,
                                                        height: 30,
                                                        borderRadius: 8,
                                                        border: 'none',
                                                        cursor: 'pointer',
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        transition: '.15s',
                                                        background: on ? 'rgba(22,163,74,.12)' : C.line,
                                                        color: on ? C.green : C.faint,
                                                    }}
                                                >
                                                    <AIcon name={on ? 'check' : 'minus'} size={15} color={on ? C.green : C.faint} />
                                                </button>
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
