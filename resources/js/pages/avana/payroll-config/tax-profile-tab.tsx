import { useForm } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    ActionBtn,
    AIcon,
    btnOut,
    btnP,
    C,
    card,
    rp,
    RupiahInput,
} from '@/lib/avana';
import type { Option, TaxProfileRow } from './types';

const th: CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '11px 16px',
    borderBottom: `1px solid ${C.line}`,
};
const td: CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '13px 16px',
    borderBottom: `1px solid ${C.line}`,
};
const label: CSSProperties = {
    fontSize: 13,
    fontWeight: 600,
    color: C.text,
    display: 'block',
    marginBottom: 6,
};
const input: CSSProperties = {
    width: '100%',
    padding: '10px 12px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 14,
    outline: 'none',
    color: C.text,
    background: '#fff',
};

interface FormData {
    employee_id: string;
    tax_subject: string;
    ptkp_status: string;
    wage_basis: string;
    daily_wage: string;
    npwp: string;
    nik: string;
    [key: string]: string;
}

export default function TaxProfileTab({
    taxProfiles,
    taxSubjects,
    ptkpStatuses,
}: {
    taxProfiles: TaxProfileRow[];
    taxSubjects: Option[];
    ptkpStatuses: string[];
}) {
    const [editing, setEditing] = useState<TaxProfileRow | null>(null);
    const [search, setSearch] = useState('');

    const subjectLabel = useMemo(() => {
        const map: Record<string, string> = {};
        taxSubjects.forEach((s) => {
            map[s.value] = s.label;
        });

        return map;
    }, [taxSubjects]);

    const form = useForm<FormData>({
        employee_id: '',
        tax_subject: 'pegawai_tetap',
        ptkp_status: '',
        wage_basis: 'monthly',
        daily_wage: '',
        npwp: '',
        nik: '',
    });

    const open = (row: TaxProfileRow) => {
        setEditing(row);
        form.setData({
            employee_id: String(row.employee_id),
            tax_subject: row.tax_subject || 'pegawai_tetap',
            ptkp_status: row.ptkp_status ?? '',
            wage_basis: row.wage_basis || 'monthly',
            daily_wage: row.daily_wage != null ? String(row.daily_wage) : '',
            npwp: row.npwp ?? '',
            nik: row.nik ?? '',
        });
        form.clearErrors();
    };

    const submit = () => {
        form.post('/avana/payroll/konfigurasi/tax-profile', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Profil pajak karyawan disimpan');
                setEditing(null);
            },
            onError: () => toast.error('Periksa kembali isian'),
        });
    };

    const rows = taxProfiles.filter((r) => {
        const q = search.trim().toLowerCase();

        if (!q) {
            return true;
        }

        return (
            r.name.toLowerCase().includes(q) ||
            (r.employee_number ?? '').toLowerCase().includes(q)
        );
    });

    const isDaily = form.data.wage_basis === 'daily';

    return (
        <>
            <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        flexWrap: 'wrap',
                        padding: '16px 18px',
                        borderBottom: `1px solid ${C.line}`,
                    }}
                >
                    <div
                        style={{ fontSize: 15, fontWeight: 600, color: C.navy }}
                    >
                        Subjek Pajak & PTKP per Karyawan
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            padding: '6px 10px',
                            minWidth: 240,
                        }}
                    >
                        <AIcon name="search" size={15} color={C.faint} />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari karyawan…"
                            style={{
                                border: 'none',
                                outline: 'none',
                                fontSize: 13,
                                flex: 1,
                                color: C.text,
                                background: 'transparent',
                            }}
                        />
                    </div>
                </div>

                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            minWidth: 860,
                        }}
                    >
                        <thead>
                            <tr>
                                {[
                                    'Karyawan',
                                    'Subjek Pajak',
                                    'PTKP',
                                    'Basis Upah',
                                    'NPWP',
                                    'Aksi',
                                ].map((h) => (
                                    <th key={h} style={th}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        style={{
                                            ...td,
                                            textAlign: 'center',
                                            color: C.faint,
                                            padding: '40px 16px',
                                        }}
                                    >
                                        Tidak ada karyawan.
                                    </td>
                                </tr>
                            )}
                            {rows.map((r) => (
                                <tr key={r.employee_id}>
                                    <td style={td}>
                                        <div
                                            style={{
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {r.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.faint,
                                            }}
                                        >
                                            {r.employee_number ?? '—'}
                                        </div>
                                    </td>
                                    <td style={td}>
                                        {subjectLabel[r.tax_subject] ??
                                            r.tax_subject}
                                    </td>
                                    <td style={td}>{r.ptkp_status ?? '—'}</td>
                                    <td style={td}>
                                        {r.wage_basis === 'daily'
                                            ? `Harian${r.daily_wage ? ` · ${rp(r.daily_wage)}` : ''}`
                                            : 'Bulanan'}
                                    </td>
                                    <td style={{ ...td, color: C.muted }}>
                                        {r.npwp ?? '—'}
                                    </td>
                                    <td style={td}>
                                        <ActionBtn
                                            icon="pencil"
                                            label="Ubah"
                                            variant="primary"
                                            onClick={() => open(r)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {editing && (
                <div
                    onClick={() => setEditing(null)}
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(15,23,42,.45)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 50,
                        padding: 20,
                    }}
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            ...card,
                            width: 520,
                            maxWidth: '100%',
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            padding: 26,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 700,
                                color: C.navy,
                                marginBottom: 2,
                            }}
                        >
                            Profil Pajak — {editing.name}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Subjek pajak menentukan skema pemotongan PPh 21 (TER
                            Bulanan / TER Harian / Pasal 17).
                        </div>

                        <div style={{ marginBottom: 14 }}>
                            <label style={label}>Subjek Pajak</label>
                            <select
                                style={input}
                                value={form.data.tax_subject}
                                onChange={(e) =>
                                    form.setData('tax_subject', e.target.value)
                                }
                            >
                                {taxSubjects.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 12,
                                marginBottom: 14,
                            }}
                        >
                            <div>
                                <label style={label}>Status PTKP</label>
                                <select
                                    style={input}
                                    value={form.data.ptkp_status}
                                    onChange={(e) =>
                                        form.setData(
                                            'ptkp_status',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">—</option>
                                    {ptkpStatuses.map((p) => (
                                        <option key={p} value={p}>
                                            {p}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label style={label}>Basis Upah</label>
                                <select
                                    style={input}
                                    value={form.data.wage_basis}
                                    onChange={(e) =>
                                        form.setData(
                                            'wage_basis',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="monthly">Bulanan</option>
                                    <option value="daily">Harian</option>
                                </select>
                            </div>
                        </div>

                        {isDaily && (
                            <div style={{ marginBottom: 14 }}>
                                <label style={label}>Upah Harian (Rp)</label>
                                <RupiahInput
                                    value={form.data.daily_wage}
                                    onChange={(raw) =>
                                        form.setData('daily_wage', raw)
                                    }
                                    style={input}
                                />
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: C.faint,
                                        marginTop: 4,
                                    }}
                                >
                                    ≤ Rp2.500.000/hari → TER Harian; di atas itu
                                    → 50% × Pasal 17.
                                </div>
                            </div>
                        )}

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 12,
                                marginBottom: 22,
                            }}
                        >
                            <div>
                                <label style={label}>NPWP</label>
                                <input
                                    style={input}
                                    placeholder="00.000.000.0-000.000"
                                    value={form.data.npwp}
                                    onChange={(e) =>
                                        form.setData('npwp', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label style={label}>NIK</label>
                                <input
                                    style={input}
                                    placeholder="16 digit NIK"
                                    value={form.data.nik}
                                    onChange={(e) =>
                                        form.setData('nik', e.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                            }}
                        >
                            <button
                                style={btnOut}
                                onClick={() => setEditing(null)}
                            >
                                <AIcon name="x" size={15} color={C.text} />
                                Batal
                            </button>
                            <button
                                style={{ ...btnP, background: C.green }}
                                disabled={form.processing}
                                onClick={submit}
                            >
                                <AIcon name="save" size={15} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
