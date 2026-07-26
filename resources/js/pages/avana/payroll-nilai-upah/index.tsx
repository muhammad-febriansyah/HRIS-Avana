import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import SalaryGradeStepController from '@/actions/App/Http/Controllers/Avana/SalaryGradeStepController';
import { AIcon, ActionBtn, C, card, RupiahInput } from '@/lib/avana';

interface Grade {
    id: number;
    grade_code: string;
    grade_name: string;
    level: number;
    min_salary: number;
    max_salary: number;
}

interface Step {
    id: number;
    salary_grade_id: number;
    masa_kerja: number;
    amount: number;
    note: string | null;
}

interface Props {
    grades: Grade[];
    steps: Step[];
}

const rupiah = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

const input: React.CSSProperties = {
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    width: '100%',
    background: '#fff',
};
const th: React.CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '10px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '10px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const primaryBtn: React.CSSProperties = {
    padding: '9px 16px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};

export default function NilaiUpah({ grades, steps }: Props) {
    const form = useForm({
        salary_grade_id: grades[0]?.id ? String(grades[0].id) : '',
        masa_kerja: '0',
        amount: '',
        note: '',
    });

    const submit = () => {
        form.post(SalaryGradeStepController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Nilai Upah disimpan');
                form.reset('masa_kerja', 'amount', 'note');
                form.setData('masa_kerja', '0');
            },
            onError: () => toast.error('Periksa isian'),
        });
    };

    const del = (id: number) =>
        router.delete(SalaryGradeStepController.destroy(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Nilai Upah dihapus'),
        });

    return (
        <>
            <Head title="Nilai Upah" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 7,
                    }}
                >
                    <span>Payroll</span>
                    <AIcon name="chevron-right" size={13} />
                    <span>Setting Komponen</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Nilai Upah</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Nilai Upah
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Nominal upah pokok per grade/golongan dan step masa kerja
                    (skala upah). Struktur &amp; Skala Upah mengatur rentang
                    grade; di sini nominal per step-nya diisi.
                </div>

                {grades.length === 0 ? (
                    <div
                        style={{
                            ...card,
                            padding: 20,
                            fontSize: 13.5,
                            color: C.faint,
                        }}
                    >
                        Belum ada grade upah. Buat dulu di menu Struktur &amp;
                        Skala Upah.
                    </div>
                ) : (
                    <>
                        <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                            <div
                                style={{
                                    fontSize: 14,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginBottom: 14,
                                }}
                            >
                                Tambah / Ubah Nilai
                            </div>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        '1.6fr 110px 1.4fr 1.6fr auto',
                                    gap: 12,
                                    alignItems: 'center',
                                }}
                            >
                                <select
                                    style={input}
                                    value={form.data.salary_grade_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'salary_grade_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {grades.map((g) => (
                                        <option key={g.id} value={g.id}>
                                            {g.grade_code} — {g.grade_name}
                                        </option>
                                    ))}
                                </select>
                                <input
                                    style={input}
                                    type="number"
                                    min={0}
                                    placeholder="Masa kerja (thn)"
                                    value={form.data.masa_kerja}
                                    onChange={(e) =>
                                        form.setData(
                                            'masa_kerja',
                                            e.target.value,
                                        )
                                    }
                                />
                                <RupiahInput
                                    value={form.data.amount}
                                    onChange={(raw) =>
                                        form.setData('amount', raw)
                                    }
                                    placeholder="Nominal upah"
                                    style={{ height: 38 }}
                                />
                                <input
                                    style={input}
                                    placeholder="Keterangan (opsional)"
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                />
                                <button
                                    style={primaryBtn}
                                    disabled={form.processing}
                                    onClick={submit}
                                >
                                    Simpan
                                </button>
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            {grades.map((g) => {
                                const rows = steps
                                    .filter((s) => s.salary_grade_id === g.id)
                                    .sort(
                                        (a, b) => a.masa_kerja - b.masa_kerja,
                                    );

                                return (
                                    <div
                                        key={g.id}
                                        style={{
                                            ...card,
                                            padding: 0,
                                            overflow: 'hidden',
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'space-between',
                                                padding: '12px 16px',
                                                borderBottom: `1px solid ${C.line}`,
                                                background: '#F8FAFC',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {g.grade_code} — {g.grade_name}{' '}
                                                <span
                                                    style={{
                                                        color: C.faint,
                                                        fontWeight: 400,
                                                    }}
                                                >
                                                    (Level {g.level})
                                                </span>
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                }}
                                            >
                                                Band {rupiah(g.min_salary)} –{' '}
                                                {rupiah(g.max_salary)}
                                            </div>
                                        </div>
                                        <table
                                            style={{
                                                width: '100%',
                                                borderCollapse: 'collapse',
                                            }}
                                        >
                                            <thead>
                                                <tr>
                                                    <th style={th}>
                                                        Masa Kerja
                                                    </th>
                                                    <th style={th}>Nominal</th>
                                                    <th style={th}>
                                                        Keterangan
                                                    </th>
                                                    <th style={th} />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {rows.length === 0 ? (
                                                    <tr>
                                                        <td
                                                            colSpan={4}
                                                            style={{
                                                                ...td,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            Belum ada step.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    rows.map((s) => (
                                                        <tr key={s.id}>
                                                            <td style={td}>
                                                                {s.masa_kerja}{' '}
                                                                thn
                                                            </td>
                                                            <td
                                                                style={{
                                                                    ...td,
                                                                    fontWeight: 600,
                                                                    color: C.navy,
                                                                }}
                                                            >
                                                                {rupiah(
                                                                    s.amount,
                                                                )}
                                                            </td>
                                                            <td
                                                                style={{
                                                                    ...td,
                                                                    color: C.muted,
                                                                }}
                                                            >
                                                                {s.note ?? '—'}
                                                            </td>
                                                            <td
                                                                style={{
                                                                    ...td,
                                                                    textAlign:
                                                                        'right',
                                                                }}
                                                            >
                                                                <ActionBtn
                                                                    icon="trash-2"
                                                                    label="Hapus"
                                                                    variant="danger"
                                                                    onClick={() =>
                                                                        del(
                                                                            s.id,
                                                                        )
                                                                    }
                                                                />
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
