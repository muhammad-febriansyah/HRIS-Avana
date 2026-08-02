import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { CSSProperties } from 'react';
import { toast } from 'sonner';
import { AIcon, btnSave, C, card } from '@/lib/avana';
import type { CompanyProfile, TimezoneOption } from './types';

const label: CSSProperties = {
    fontSize: 12.5,
    fontWeight: 600,
    color: C.text,
    marginBottom: 6,
    display: 'block',
};

const input: CSSProperties = {
    width: '100%',
    height: 40,
    padding: '0 12px',
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
};

/**
 * Single-record editor for the tenant's company profile (identity + contact).
 * Posts to `PUT /avana/perusahaan/profile`; the logo lives on the appearance
 * page, so this form only covers the legal and contact fields.
 */
export function ProfileForm({
    company,
    timezones,
}: {
    company: CompanyProfile;
    timezones: TimezoneOption[];
}) {
    const [form, setForm] = useState<CompanyProfile>({ ...company });
    const [saving, setSaving] = useState(false);

    const set = (key: keyof CompanyProfile, value: string) =>
        setForm((prev) => ({ ...prev, [key]: value }));

    const save = () => {
        if (!form.name.trim()) {
            toast.error('Nama perusahaan wajib diisi.');

            return;
        }

        setSaving(true);
        router.put(
            '/avana/perusahaan/profile',
            { ...form },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] || 'Gagal menyimpan profil.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    const field = (
        key: keyof CompanyProfile,
        text: string,
        opts?: { type?: string; placeholder?: string },
    ) => (
        <div>
            <label style={label}>{text}</label>
            <input
                style={input}
                type={opts?.type ?? 'text'}
                placeholder={opts?.placeholder}
                value={form[key] ?? ''}
                onChange={(event) => set(key, event.target.value)}
            />
        </div>
    );

    return (
        <div style={{ ...card, padding: 24, maxWidth: 760 }}>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 4,
                }}
            >
                Profil Perusahaan
            </div>
            <div style={{ fontSize: 12.5, color: C.muted, marginBottom: 20 }}>
                Identitas legal dan kontak perusahaan. Logo diatur di menu
                Tampilan &amp; Tema.
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(2, 1fr)',
                    gap: 16,
                }}
            >
                {field('name', 'Nama Perusahaan', {
                    placeholder: 'PT Nusantara Jaya',
                })}
                {field('legal_name', 'Nama Badan Hukum', {
                    placeholder: 'PT / CV …',
                })}
                {field('npwp', 'NPWP', {
                    placeholder: '00.000.000.0-000.000',
                })}
                {field('email', 'Email', {
                    type: 'email',
                    placeholder: 'info@perusahaan.co.id',
                })}
                {field('phone', 'Telepon', {
                    placeholder: '(021) 1234-5678',
                })}

                <div>
                    <label style={label}>Zona Waktu</label>
                    <select
                        value={form.timezone}
                        onChange={(event) => set('timezone', event.target.value)}
                        style={{ ...input, cursor: 'pointer' }}
                    >
                        {timezones.map((zone) => (
                            <option key={zone.value} value={zone.value}>
                                {zone.label}
                            </option>
                        ))}
                    </select>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: C.faint,
                            marginTop: 5,
                        }}
                    >
                        Jam absensi, keterlambatan dan laporan dibaca pada zona
                        ini. Cabang yang punya zona sendiri memakai zonanya.
                    </div>
                </div>
            </div>

            <div style={{ marginTop: 16 }}>
                <label style={label}>Alamat</label>
                <textarea
                    style={{
                        ...input,
                        height: 84,
                        padding: '10px 12px',
                        resize: 'vertical',
                    }}
                    placeholder="cth. Jl. Merdeka No. 10, Jakarta Pusat 10110"
                    value={form.address ?? ''}
                    onChange={(event) => set('address', event.target.value)}
                />
            </div>

            <div style={{ marginTop: 22, display: 'flex' }}>
                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    style={{
                        ...btnSave,
                        opacity: saving ? 0.6 : 1,
                    }}
                >
                    <AIcon name="check" size={16} color="#fff" />
                    {saving ? 'Menyimpan…' : 'Simpan Profil'}
                </button>
            </div>
        </div>
    );
}
