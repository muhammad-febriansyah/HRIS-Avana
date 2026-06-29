import { Head, Link, router } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { toast } from 'sonner';
import { AIcon, C, card } from '@/lib/avana';

const labelStyle: CSSProperties = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7 };

const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    outline: 'none',
    transition: '.15s',
};

const selectStyle: CSSProperties = { ...inputStyle, color: C.muted, background: '#fff', cursor: 'pointer' };

const dateStyle: CSSProperties = { ...inputStyle, color: C.muted };

const req = <span style={{ color: C.red }}>*</span>;

function SectionHeader({ icon, title, desc }: { icon: string; title: string; desc?: string }) {
    return (
        <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.line}` }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                <AIcon name={icon} size={18} color={C.primary} />
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>{title}</div>
            </div>
            {desc ? <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3, marginLeft: 27 }}>{desc}</div> : null}
        </div>
    );
}

export default function AvanaKaryawanForm() {
    function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.visit('/avana/karyawan');
        setTimeout(() => toast.success('Tersimpan', { description: 'Data karyawan berhasil disimpan' }), 200);
    }

    return (
        <>
            <Head title="Tambah Karyawan" />
            <div style={{ padding: '28px 32px', maxWidth: 880 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                    <Link href="/avana/karyawan" style={{ color: C.faint, textDecoration: 'none', cursor: 'pointer' }}>
                        Karyawan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Karyawan</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 24px', letterSpacing: '-.01em' }}>Tambah Karyawan Baru</h1>

                <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                    {/* Data Personal */}
                    <div style={card}>
                        <SectionHeader icon="user" title="Data Personal" desc="Identitas dasar karyawan sesuai KTP." />
                        <div className="avn-2col" style={{ padding: '20px 22px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px' }}>
                            <div>
                                <label style={labelStyle}>Nama Lengkap {req}</label>
                                <input placeholder="Masukkan nama sesuai KTP" style={inputStyle} />
                            </div>
                            <div>
                                <label style={labelStyle}>NIK (KTP) {req}</label>
                                <input
                                    placeholder="16 digit NIK"
                                    style={{ ...inputStyle, border: `1px solid ${C.red}`, boxShadow: '0 0 0 3px rgba(220,38,38,.08)', transition: undefined }}
                                />
                                <div style={{ fontSize: 12, color: C.red, marginTop: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
                                    <AIcon name="circle-alert" size={13} color={C.red} />
                                    NIK harus 16 digit angka.
                                </div>
                            </div>
                            <div>
                                <label style={labelStyle}>Email {req}</label>
                                <input type="email" placeholder="nama@perusahaan.co.id" style={inputStyle} />
                            </div>
                            <div>
                                <label style={labelStyle}>No. Telepon {req}</label>
                                <input placeholder="08xx-xxxx-xxxx" style={inputStyle} />
                            </div>
                            <div>
                                <label style={labelStyle}>Tanggal Lahir {req}</label>
                                <input type="date" style={dateStyle} />
                            </div>
                            <div>
                                <label style={labelStyle}>Jenis Kelamin {req}</label>
                                <select style={selectStyle}>
                                    <option value="">Pilih jenis kelamin</option>
                                    <option>Laki-laki</option>
                                    <option>Perempuan</option>
                                </select>
                            </div>
                            <div style={{ gridColumn: '1/-1' }}>
                                <label style={labelStyle}>Alamat Domisili</label>
                                <textarea
                                    placeholder="Alamat lengkap tempat tinggal"
                                    rows={2}
                                    style={{ ...inputStyle, height: undefined, padding: '11px 13px', resize: 'vertical' }}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Kepegawaian */}
                    <div style={card}>
                        <SectionHeader icon="briefcase" title="Kepegawaian" desc="Posisi & detail kontrak kerja." />
                        <div className="avn-2col" style={{ padding: '20px 22px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px' }}>
                            <div>
                                <label style={labelStyle}>Departemen {req}</label>
                                <select style={selectStyle}>
                                    <option value="">Pilih departemen</option>
                                    <option>Engineering</option>
                                    <option>Finance</option>
                                    <option>Sales</option>
                                    <option>Human Resources</option>
                                </select>
                            </div>
                            <div>
                                <label style={labelStyle}>Jabatan {req}</label>
                                <input placeholder="cth. Software Engineer" style={inputStyle} />
                            </div>
                            <div>
                                <label style={labelStyle}>Status Karyawan {req}</label>
                                <select style={selectStyle}>
                                    <option value="">Pilih status</option>
                                    <option>Tetap</option>
                                    <option>Kontrak</option>
                                    <option>Probation</option>
                                </select>
                            </div>
                            <div>
                                <label style={labelStyle}>Tanggal Masuk {req}</label>
                                <input type="date" style={dateStyle} />
                            </div>
                            <div>
                                <label style={labelStyle}>Cabang {req}</label>
                                <select style={selectStyle}>
                                    <option value="">Pilih cabang</option>
                                    <option>Jakarta Pusat</option>
                                    <option>Bandung</option>
                                    <option>Surabaya</option>
                                </select>
                            </div>
                            <div>
                                <label style={labelStyle}>Atasan Langsung</label>
                                <input placeholder="Cari nama atasan…" style={inputStyle} />
                            </div>
                        </div>
                    </div>

                    {/* Pajak & BPJS + Rekening & Gaji */}
                    <div className="avn-2col" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
                        <div style={card}>
                            <SectionHeader icon="receipt" title="Pajak & BPJS" />
                            <div style={{ padding: '20px 22px', display: 'flex', flexDirection: 'column', gap: 16 }}>
                                <div>
                                    <label style={labelStyle}>NPWP</label>
                                    <input placeholder="00.000.000.0-000.000" style={inputStyle} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Status PTKP {req}</label>
                                    <select style={selectStyle}>
                                        <option value="">Pilih PTKP</option>
                                        <option>TK/0</option>
                                        <option>K/0</option>
                                        <option>K/1</option>
                                        <option>K/2</option>
                                    </select>
                                </div>
                                <div>
                                    <label style={labelStyle}>No. BPJS Ketenagakerjaan</label>
                                    <input placeholder="Nomor kepesertaan" style={inputStyle} />
                                </div>
                            </div>
                        </div>
                        <div style={card}>
                            <SectionHeader icon="landmark" title="Rekening & Gaji" />
                            <div style={{ padding: '20px 22px', display: 'flex', flexDirection: 'column', gap: 16 }}>
                                <div>
                                    <label style={labelStyle}>Bank {req}</label>
                                    <select style={selectStyle}>
                                        <option value="">Pilih bank</option>
                                        <option>BCA</option>
                                        <option>Mandiri</option>
                                        <option>BNI</option>
                                        <option>BRI</option>
                                    </select>
                                </div>
                                <div>
                                    <label style={labelStyle}>No. Rekening {req}</label>
                                    <input placeholder="Nomor rekening" style={inputStyle} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Gaji Pokok {req}</label>
                                    <div style={{ position: 'relative' }}>
                                        <span style={{ position: 'absolute', left: 13, top: '50%', transform: 'translateY(-50%)', fontSize: 13.5, color: C.muted, fontWeight: 500 }}>Rp</span>
                                        <input
                                            placeholder="0"
                                            style={{ ...inputStyle, padding: '0 13px 0 38px', textAlign: 'right', fontVariantNumeric: 'tabular-nums' }}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Footer */}
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, padding: '4px 0 8px', position: 'sticky', bottom: 0 }}>
                        <Link
                            href="/avana/karyawan"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                                height: 42,
                                padding: '0 18px',
                                background: '#fff',
                                color: C.text,
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13.5,
                                fontWeight: 500,
                                cursor: 'pointer',
                                textDecoration: 'none',
                                transition: '.15s',
                            }}
                        >
                            <AIcon name="x" size={16} />
                            Batal
                        </Link>
                        <button
                            type="submit"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                                height: 42,
                                padding: '0 20px',
                                background: C.primary,
                                color: '#fff',
                                border: 'none',
                                borderRadius: 8,
                                fontSize: 13.5,
                                fontWeight: 600,
                                cursor: 'pointer',
                                transition: '.15s',
                            }}
                        >
                            <AIcon name="save" size={16} color="#fff" />
                            Simpan Karyawan
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
