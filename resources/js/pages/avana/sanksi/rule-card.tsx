import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import AttendancePenaltyController from '@/actions/App/Http/Controllers/Avana/AttendancePenaltyController';
import { ActionBtn, AIcon, btnP, C, rp, RupiahInput, thCell } from '@/lib/avana';
import { fieldLabelStyle, inputStyle } from './components';
import type { PenaltyRule } from './types';

/**
 * The tenant's own late-penalty table — "terlambat 10–30 menit: Rp20.000".
 * Every company writes these bands differently, so they are edited here rather
 * than hard-coded, and "Buat dari Absensi" applies whatever is listed.
 */
export function RuleCard({ rules }: { rules: PenaltyRule[] }) {
    const [editing, setEditing] = useState<PenaltyRule | null>(null);

    const form = useForm({
        id: '' as string | number,
        min_minutes: '',
        max_minutes: '',
        penalty_type: 'deduction',
        amount: '',
        is_active: true,
    });

    const startNew = () => {
        setEditing(null);
        form.setData({
            id: '',
            min_minutes: '',
            max_minutes: '',
            penalty_type: 'deduction',
            amount: '',
            is_active: true,
        });
        form.clearErrors();
    };

    const startEdit = (rule: PenaltyRule) => {
        setEditing(rule);
        form.setData({
            id: rule.id,
            min_minutes: String(rule.min_minutes),
            max_minutes:
                rule.max_minutes === null ? '' : String(rule.max_minutes),
            penalty_type: rule.penalty_type,
            amount: String(rule.amount),
            is_active: rule.is_active,
        });
        form.clearErrors();
    };

    const submit = () => {
        router.post(
            AttendancePenaltyController.storeRule().url,
            {
                id: form.data.id === '' ? null : Number(form.data.id),
                min_minutes: Number(form.data.min_minutes || 0),
                // Blank = the open-ended last tier.
                max_minutes:
                    form.data.max_minutes === ''
                        ? null
                        : Number(form.data.max_minutes),
                penalty_type: form.data.penalty_type,
                amount: Number(form.data.amount || 0),
                is_active: form.data.is_active,
            },
            {
                preserveScroll: true,
                onSuccess: startNew,
                onError: (errors) =>
                    toast.error(
                        errors.max_minutes ??
                            errors.min_minutes ??
                            errors.amount ??
                            'Periksa isian aturan denda',
                    ),
            },
        );
    };

    const remove = (rule: PenaltyRule) =>
        router.delete(AttendancePenaltyController.destroyRule(rule.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                if (editing?.id === rule.id) {
                    startNew();
                }
            },
        });

    const band = (rule: PenaltyRule) =>
        rule.max_minutes === null
            ? `> ${rule.min_minutes} menit`
            : `${rule.min_minutes}–${rule.max_minutes} menit`;

    return (
        <div
            style={{
                background: '#fff',
                border: `1px solid ${C.line}`,
                borderRadius: 12,
                padding: 20,
                marginBottom: 18,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-start',
                    justifyContent: 'space-between',
                    gap: 12,
                    flexWrap: 'wrap',
                    marginBottom: 14,
                }}
            >
                <div>
                    <div
                        style={{
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Aturan Denda Keterlambatan
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.muted,
                            marginTop: 3,
                            maxWidth: 640,
                        }}
                    >
                        Dipakai tombol “Buat dari Absensi”. Menit dihitung dari
                        jam masuk shift; toleransi diatur per shift di
                        Pengaturan Perusahaan. Rentang dibaca sebagai “lebih
                        dari menit awal sampai dengan menit akhir”.
                    </div>
                </div>
            </div>

            <div style={{ overflowX: 'auto', marginBottom: 16 }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr>
                            <th style={thCell}>Rentang Terlambat</th>
                            <th style={thCell}>Jenis</th>
                            <th style={{ ...thCell, textAlign: 'right' }}>
                                Nominal
                            </th>
                            <th style={thCell}>Status</th>
                            <th style={thCell} />
                        </tr>
                    </thead>
                    <tbody>
                        {rules.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    style={{
                                        padding: '22px 12px',
                                        textAlign: 'center',
                                        color: C.faint,
                                        fontSize: 13,
                                    }}
                                >
                                    Belum ada aturan. Tambahkan di bawah, mis.
                                    10–30 menit Rp20.000.
                                </td>
                            </tr>
                        )}
                        {rules.map((rule) => (
                            <tr key={rule.id}>
                                <td
                                    style={{
                                        padding: '10px 12px',
                                        fontSize: 13.5,
                                        color: C.navy,
                                        fontWeight: 600,
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    {band(rule)}
                                </td>
                                <td
                                    style={{
                                        padding: '10px 12px',
                                        fontSize: 13,
                                        color: C.muted,
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    {rule.penalty_type === 'deduction'
                                        ? 'Potong gaji'
                                        : 'Peringatan'}
                                </td>
                                <td
                                    style={{
                                        padding: '10px 12px',
                                        fontSize: 13.5,
                                        color: C.text,
                                        textAlign: 'right',
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    {rule.penalty_type === 'deduction'
                                        ? rp(rule.amount)
                                        : '—'}
                                </td>
                                <td
                                    style={{
                                        padding: '10px 12px',
                                        fontSize: 13,
                                        color: rule.is_active
                                            ? C.green
                                            : C.faint,
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    {rule.is_active ? 'Aktif' : 'Nonaktif'}
                                </td>
                                <td
                                    style={{
                                        padding: '10px 12px',
                                        borderTop: `1px solid ${C.line}`,
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            gap: 8,
                                        }}
                                    >
                                        <ActionBtn
                                            icon="pencil"
                                            label="Ubah"
                                            variant="primary"
                                            onClick={() => startEdit(rule)}
                                        />
                                        <ActionBtn
                                            icon="trash-2"
                                            label="Hapus"
                                            variant="danger"
                                            onClick={() => remove(rule)}
                                        />
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns:
                        'repeat(auto-fit, minmax(150px, 1fr)) auto',
                    gap: 12,
                    alignItems: 'end',
                }}
            >
                <div>
                    <span style={fieldLabelStyle}>
                        Terlambat lebih dari (menit)
                    </span>
                    <input
                        style={inputStyle}
                        type="number"
                        min="0"
                        value={form.data.min_minutes}
                        onChange={(event) =>
                            form.setData('min_minutes', event.target.value)
                        }
                        placeholder="10"
                    />
                </div>
                <div>
                    <span style={fieldLabelStyle}>Sampai (menit)</span>
                    <input
                        style={inputStyle}
                        type="number"
                        min="1"
                        value={form.data.max_minutes}
                        onChange={(event) =>
                            form.setData('max_minutes', event.target.value)
                        }
                        placeholder="30 — kosongkan = seterusnya"
                    />
                </div>
                <div>
                    <span style={fieldLabelStyle}>Jenis</span>
                    <select
                        style={inputStyle}
                        value={form.data.penalty_type}
                        onChange={(event) =>
                            form.setData('penalty_type', event.target.value)
                        }
                    >
                        <option value="deduction">Potong gaji</option>
                        <option value="warning">Peringatan saja</option>
                    </select>
                </div>
                <div>
                    <span style={fieldLabelStyle}>Nominal (Rp)</span>
                    <RupiahInput
                        disabled={form.data.penalty_type !== 'deduction'}
                        value={form.data.amount}
                        onChange={(raw) => form.setData('amount', raw)}
                        placeholder="20.000"
                    />
                </div>
                <button style={btnP} onClick={submit}>
                    <AIcon
                        name={editing ? 'save' : 'plus'}
                        size={15}
                        color="#fff"
                    />
                    {editing ? 'Simpan Perubahan' : 'Tambah Aturan'}
                </button>
            </div>

            {editing && (
                <button
                    onClick={startNew}
                    style={{
                        marginTop: 10,
                        border: 'none',
                        background: 'transparent',
                        color: C.muted,
                        fontSize: 12.5,
                        cursor: 'pointer',
                        padding: 0,
                    }}
                >
                    Batal mengubah {band(editing)}
                </button>
            )}

            <div style={{ fontSize: 12.5, color: C.faint, marginTop: 12 }}>
                Denda bertipe “potong gaji” otomatis masuk potongan payroll pada
                periode yang memuat tanggalnya.
            </div>
        </div>
    );
}
