import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, employees, statusBadge, thCell } from '@/lib/avana';

export default function AvanaKaryawan() {
    const [openMenu, setOpenMenu] = useState<number | null>(null);
    const [confirm, setConfirm] = useState<{ name: string } | null>(null);

    return (
        <>
            <Head title="Karyawan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Karyawan</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Karyawan</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>1.248 karyawan aktif di seluruh cabang</div>
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

                {/* Table card */}
                <div style={{ background: '#fff', border: `1px solid ${C.border}`, borderRadius: 12, boxShadow: '0 1px 2px rgba(15,23,42,.04)', overflow: 'hidden' }}>
                    {/* Filter bar */}
                    <div style={{ padding: '16px 18px', borderBottom: `1px solid ${C.border}`, display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
                        <div style={{ position: 'relative', flex: 1, minWidth: 220, maxWidth: 320 }}>
                            <AIcon name="search" size={16} color={C.faint} style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)' }} />
                            <input
                                placeholder="Cari nama atau email…"
                                style={{ width: '100%', height: 38, padding: '0 12px 0 36px', background: C.surface, border: '1px solid transparent', borderRadius: 8, fontSize: 13, outline: 'none', transition: '.15s' }}
                            />
                        </div>
                        <select style={{ height: 38, padding: '0 12px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13, color: C.muted, background: '#fff', cursor: 'pointer', outline: 'none' }}>
                            <option>Semua Status</option>
                            <option>Tetap</option>
                            <option>Kontrak</option>
                            <option>Probation</option>
                        </select>
                        <select style={{ height: 38, padding: '0 12px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13, color: C.muted, background: '#fff', cursor: 'pointer', outline: 'none' }}>
                            <option>Semua Departemen</option>
                            <option>Engineering</option>
                            <option>Finance</option>
                            <option>Sales</option>
                        </select>
                        <select style={{ height: 38, padding: '0 12px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13, color: C.muted, background: '#fff', cursor: 'pointer', outline: 'none' }}>
                            <option>Semua Cabang</option>
                            <option>Jakarta Pusat</option>
                            <option>Bandung</option>
                            <option>Surabaya</option>
                        </select>
                        <div style={{ flex: 1 }} />
                        <button style={{ height: 38, padding: '0 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13, color: C.muted, background: '#fff', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 7, transition: '.15s' }}>
                            <AIcon name="sliders-horizontal" size={15} />
                            Kolom
                        </button>
                    </div>

                    {/* Table */}
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 840 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={{ width: 44, padding: '12px 0 12px 18px', textAlign: 'left' }}>
                                        <input type="checkbox" style={{ width: 15, height: 15, accentColor: C.primary }} />
                                    </th>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Departemen</th>
                                    <th style={thCell}>Jabatan</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>Tgl Masuk</th>
                                    <th style={{ ...thCell, padding: '12px 18px', textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.map((e) => {
                                    const badge = statusBadge(e.status);

                                    return (
                                        <tr key={e.no} style={{ borderTop: `1px solid ${C.line}`, transition: 'background .15s' }}>
                                            <td style={{ padding: '13px 0 13px 18px' }}>
                                                <input type="checkbox" style={{ width: 15, height: 15, accentColor: C.primary }} />
                                            </td>
                                            <td style={{ padding: '13px 16px' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
                                                    <div style={{ width: 36, height: 36, borderRadius: '50%', flex: 'none', background: e.av, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12.5, fontWeight: 600 }}>{e.ini}</div>
                                                    <div style={{ minWidth: 0 }}>
                                                        <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy }}>{e.nama}</div>
                                                        <div style={{ fontSize: 12, color: C.faint }}>{e.email}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style={{ padding: '13px 16px', fontSize: 13, color: C.text }}>{e.dept}</td>
                                            <td style={{ padding: '13px 16px', fontSize: 13, color: C.muted }}>{e.jab}</td>
                                            <td style={{ padding: '13px 16px' }}>
                                                <span style={{ display: 'inline-block', padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: badge.color, background: badge.bg }}>{badge.label}</span>
                                            </td>
                                            <td style={{ padding: '13px 16px', fontSize: 13, color: C.muted }}>{e.masuk}</td>
                                            <td style={{ padding: '13px 18px', textAlign: 'right' }}>
                                                <div style={{ position: 'relative', display: 'inline-block' }}>
                                                    <button
                                                        onClick={() => setOpenMenu((prev) => (prev === e.no ? null : e.no))}
                                                        style={{ width: 32, height: 32, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, cursor: 'pointer', color: C.muted, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', transition: '.15s' }}
                                                    >
                                                        <AIcon name="ellipsis-vertical" size={16} />
                                                    </button>
                                                    <div style={{ display: openMenu === e.no ? 'block' : 'none', position: 'absolute', right: 0, top: 38, width: 148, background: '#fff', border: `1px solid ${C.border}`, borderRadius: 10, boxShadow: '0 8px 24px rgba(15,23,42,.12)', zIndex: 20, padding: 5, textAlign: 'left' }}>
                                                        <Link
                                                            href={'/avana/karyawan/' + e.no}
                                                            style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 9, padding: '8px 10px', border: 'none', background: 'none', borderRadius: 7, fontSize: 13, color: C.text, cursor: 'pointer', transition: '.12s', textDecoration: 'none' }}
                                                        >
                                                            <AIcon name="eye" size={15} color={C.muted} />
                                                            Lihat
                                                        </Link>
                                                        <Link
                                                            href="/avana/karyawan/create"
                                                            style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 9, padding: '8px 10px', border: 'none', background: 'none', borderRadius: 7, fontSize: 13, color: C.text, cursor: 'pointer', transition: '.12s', textDecoration: 'none' }}
                                                        >
                                                            <AIcon name="pencil" size={15} color={C.muted} />
                                                            Ubah
                                                        </Link>
                                                        <button
                                                            onClick={() => {
                                                                setConfirm({ name: e.nama });
                                                                setOpenMenu(null);
                                                            }}
                                                            style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 9, padding: '8px 10px', border: 'none', background: 'none', borderRadius: 7, fontSize: 13, color: C.red, cursor: 'pointer', transition: '.12s' }}
                                                        >
                                                            <AIcon name="trash-2" size={15} color={C.red} />
                                                            Hapus
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination footer */}
                    <div style={{ padding: '14px 18px', borderTop: `1px solid ${C.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
                        <div style={{ fontSize: 13, color: C.muted }}>
                            Menampilkan <span style={{ color: C.text, fontWeight: 500 }}>1–10</span> dari <span style={{ color: C.text, fontWeight: 500 }}>1.248</span> karyawan
                        </div>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <button style={{ height: 34, minWidth: 34, padding: '0 10px', border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, fontSize: 13, color: C.faint, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                <AIcon name="chevron-left" size={15} />
                            </button>
                            <button style={{ height: 34, minWidth: 34, border: 'none', background: C.primary, borderRadius: 8, fontSize: 13, color: '#fff', fontWeight: 600, cursor: 'pointer' }}>1</button>
                            <button style={{ height: 34, minWidth: 34, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, fontSize: 13, color: C.text, cursor: 'pointer' }}>2</button>
                            <button style={{ height: 34, minWidth: 34, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, fontSize: 13, color: C.text, cursor: 'pointer' }}>3</button>
                            <span style={{ color: C.faint, padding: '0 4px' }}>…</span>
                            <button style={{ height: 34, minWidth: 34, border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, fontSize: 13, color: C.text, cursor: 'pointer' }}>125</button>
                            <button style={{ height: 34, minWidth: 34, padding: '0 10px', border: `1px solid ${C.border}`, background: '#fff', borderRadius: 8, fontSize: 13, color: C.text, cursor: 'pointer', display: 'inline-flex', alignItems: 'center' }}>
                                <AIcon name="chevron-right" size={15} />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Confirm delete modal */}
            {confirm && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={() => setConfirm(null)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26, animation: 'toastIn .2s ease' }}>
                        <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(220,38,38,.1)', color: C.red, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                            <AIcon name="trash-2" size={22} color={C.red} />
                        </div>
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>Hapus karyawan?</div>
                        <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>
                            Data <strong style={{ color: C.text }}>{confirm.name}</strong> akan dihapus permanen beserta seluruh riwayatnya. Tindakan ini tidak dapat dibatalkan.
                        </div>
                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button onClick={() => setConfirm(null)} style={{ flex: 1, height: 44, background: '#fff', color: C.text, border: `1px solid ${C.border}`, borderRadius: 9, fontSize: 14, fontWeight: 500, cursor: 'pointer', transition: '.15s' }}>
                                Batal
                            </button>
                            <button
                                onClick={() => {
                                    const name = confirm.name;
                                    setConfirm(null);
                                    toast.success('Terhapus', { description: name + ' telah dihapus' });
                                }}
                                style={{ flex: 1, height: 44, background: C.red, color: '#fff', border: 'none', borderRadius: 9, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, transition: '.15s' }}
                            >
                                <AIcon name="trash-2" size={16} />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
