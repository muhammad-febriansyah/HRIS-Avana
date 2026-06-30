import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import TalentController from '@/actions/App/Http/Controllers/Avana/TalentController';
import { AIcon, btnP, C, card } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    KpiCard,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import {
    emptyAssessmentForm,
    LEVEL_RANK,
    levelLabel,
    type AssessmentChip,
    type AssessmentFormData,
    type TalentIndexProps,
    type FlashProps,
} from './types';

/** Tint a 9-box cell by its combined performance + potential score (0-4). */
function cellTint(score: number): { bg: string; border: string } {
    if (score >= 3) {
        return { bg: 'rgba(22,163,74,.08)', border: 'rgba(22,163,74,.28)' };
    }
    if (score === 2) {
        return { bg: 'rgba(47,84,201,.06)', border: 'rgba(47,84,201,.22)' };
    }
    if (score === 1) {
        return { bg: 'rgba(217,119,6,.07)', border: 'rgba(217,119,6,.25)' };
    }
    return { bg: 'rgba(220,38,38,.07)', border: 'rgba(220,38,38,.25)' };
}

/** Short 9-box label per quadrant, keyed by performance + potential ranks. */
const BOX_LABELS: Record<string, string> = {
    '0-0': 'Risiko',
    '1-0': 'Pekerja Efektif',
    '2-0': 'Spesialis',
    '0-1': 'Dilema',
    '1-1': 'Inti',
    '2-1': 'Berkembang',
    '0-2': 'Teka-teki',
    '1-2': 'Potensi Tinggi',
    '2-2': 'Bintang',
};

export default function TalentIndex({
    assessments,
    successors,
    employees,
    levels,
    kpis,
}: TalentIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [editingId, setEditingId] = useState<number | null>(null);
    const [confirm, setConfirm] = useState<AssessmentChip | null>(null);

    const form = useForm<AssessmentFormData>({ ...emptyAssessmentForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const resetForm = () => {
        setEditingId(null);
        form.setData({ ...emptyAssessmentForm });
        form.clearErrors();
    };

    const editChip = (chip: AssessmentChip) => {
        form.clearErrors();
        setEditingId(chip.id);
        form.setData({
            employee_id: String(chip.employee_id),
            performance_level: chip.performance_level,
            potential_level: chip.potential_level,
            successor_for: chip.successor_for ?? '',
            note: chip.note ?? '',
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const action = editingId
            ? TalentController.update(editingId)
            : TalentController.store();

        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });
    };

    const remove = () => {
        if (!confirm) {
            return;
        }

        router.delete(TalentController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirm(null);
                if (editingId === confirm.id) {
                    resetForm();
                }
            },
        });
    };

    // Potential rows high → low; performance cols low → high.
    const potentialRows = ['high', 'medium', 'low'];
    const performanceCols = ['low', 'medium', 'high'];

    return (
        <>
            <Head title="Talenta & Suksesi" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span>Manajemen</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Talenta &amp; Suksesi</span>
                    </div>
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Talenta &amp; Suksesi
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Petakan talenta pada matriks 9-box &amp; kelola calon penerus.
                    </div>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    <KpiCard label="Dinilai" value={kpis.assessed} icon="users" color={C.primary} />
                    <KpiCard label="Bintang" value={kpis.stars} icon="star" color={C.green} />
                    <KpiCard
                        label="Risiko"
                        value={kpis.risks}
                        icon="triangle-alert"
                        color={C.red}
                    />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1.7fr) minmax(280px, 1fr)',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* 9-box grid */}
                    <div style={{ ...card, padding: 18 }}>
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 14,
                            }}
                        >
                            Matriks 9-Box
                        </div>
                        <div style={{ display: 'flex', gap: 8 }}>
                            {/* Vertical axis label */}
                            <div
                                style={{
                                    writingMode: 'vertical-rl',
                                    transform: 'rotate(180deg)',
                                    textAlign: 'center',
                                    fontSize: 11,
                                    fontWeight: 700,
                                    letterSpacing: '.08em',
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                    paddingBottom: 26,
                                }}
                            >
                                Potensi →
                            </div>
                            <div style={{ flex: 1 }}>
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: 'repeat(3, 1fr)',
                                        gap: 8,
                                    }}
                                >
                                    {potentialRows.map((potential) =>
                                        performanceCols.map((performance) => {
                                            const chips = assessments.filter(
                                                (a) =>
                                                    a.potential_level === potential &&
                                                    a.performance_level === performance,
                                            );
                                            const score =
                                                LEVEL_RANK[potential] + LEVEL_RANK[performance];
                                            const tint = cellTint(score);
                                            const key = `${LEVEL_RANK[performance]}-${LEVEL_RANK[potential]}`;

                                            return (
                                                <div
                                                    key={`${potential}-${performance}`}
                                                    style={{
                                                        minHeight: 120,
                                                        border: `1px solid ${tint.border}`,
                                                        background: tint.bg,
                                                        borderRadius: 10,
                                                        padding: 10,
                                                        display: 'flex',
                                                        flexDirection: 'column',
                                                        gap: 6,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 10.5,
                                                            fontWeight: 700,
                                                            letterSpacing: '.03em',
                                                            textTransform: 'uppercase',
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {BOX_LABELS[key]}
                                                    </div>
                                                    {chips.map((chip) => (
                                                        <button
                                                            key={chip.id}
                                                            onClick={() => editChip(chip)}
                                                            title="Klik untuk menilai ulang"
                                                            style={{
                                                                textAlign: 'left',
                                                                border: `1px solid ${C.border}`,
                                                                background: '#fff',
                                                                borderRadius: 7,
                                                                padding: '5px 8px',
                                                                fontSize: 12,
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                                cursor: 'pointer',
                                                                whiteSpace: 'nowrap',
                                                                overflow: 'hidden',
                                                                textOverflow: 'ellipsis',
                                                            }}
                                                        >
                                                            {chip.employee ?? '—'}
                                                        </button>
                                                    ))}
                                                </div>
                                            );
                                        }),
                                    )}
                                </div>
                                <div
                                    style={{
                                        textAlign: 'center',
                                        fontSize: 11,
                                        fontWeight: 700,
                                        letterSpacing: '.08em',
                                        color: C.faint,
                                        textTransform: 'uppercase',
                                        marginTop: 10,
                                    }}
                                >
                                    Kinerja →
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Side form + successors */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                        <form onSubmit={submit} style={{ ...card, padding: 18 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: 14,
                                }}
                            >
                                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                                    {editingId ? 'Ubah Penilaian' : 'Nilai Talenta'}
                                </div>
                                {editingId && (
                                    <button
                                        type="button"
                                        onClick={resetForm}
                                        style={{
                                            border: 'none',
                                            background: 'transparent',
                                            color: C.muted,
                                            fontSize: 12.5,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        Batal
                                    </button>
                                )}
                            </div>

                            <div
                                style={{ display: 'flex', flexDirection: 'column', gap: 14 }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Karyawan <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <select
                                        value={form.data.employee_id}
                                        disabled={!!editingId}
                                        onChange={(event) =>
                                            form.setData('employee_id', event.target.value)
                                        }
                                        style={{
                                            ...withError(selectStyle, !!form.errors.employee_id),
                                            opacity: editingId ? 0.7 : 1,
                                            cursor: editingId ? 'not-allowed' : 'pointer',
                                        }}
                                    >
                                        <option value="">Pilih karyawan</option>
                                        {employees.map((employee) => (
                                            <option key={employee.id} value={String(employee.id)}>
                                                {employee.name}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={form.errors.employee_id} />
                                </div>

                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 14,
                                    }}
                                >
                                    <div>
                                        <label style={fieldLabelStyle}>Kinerja</label>
                                        <select
                                            value={form.data.performance_level}
                                            onChange={(event) =>
                                                form.setData(
                                                    'performance_level',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                selectStyle,
                                                !!form.errors.performance_level,
                                            )}
                                        >
                                            {levels.map((level) => (
                                                <option key={level.value} value={level.value}>
                                                    {level.label}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError message={form.errors.performance_level} />
                                    </div>
                                    <div>
                                        <label style={fieldLabelStyle}>Potensi</label>
                                        <select
                                            value={form.data.potential_level}
                                            onChange={(event) =>
                                                form.setData(
                                                    'potential_level',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                selectStyle,
                                                !!form.errors.potential_level,
                                            )}
                                        >
                                            {levels.map((level) => (
                                                <option key={level.value} value={level.value}>
                                                    {level.label}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError message={form.errors.potential_level} />
                                    </div>
                                </div>

                                <div>
                                    <label style={fieldLabelStyle}>Calon Penerus Untuk</label>
                                    <input
                                        type="text"
                                        value={form.data.successor_for}
                                        onChange={(event) =>
                                            form.setData('successor_for', event.target.value)
                                        }
                                        placeholder="cth. Manajer Operasional"
                                        style={withError(inputStyle, !!form.errors.successor_for)}
                                    />
                                    <FieldError message={form.errors.successor_for} />
                                </div>

                                <div>
                                    <label style={fieldLabelStyle}>Catatan</label>
                                    <textarea
                                        value={form.data.note}
                                        onChange={(event) =>
                                            form.setData('note', event.target.value)
                                        }
                                        placeholder="Catatan penilaian (opsional)"
                                        style={withError(textareaStyle, !!form.errors.note)}
                                    />
                                    <FieldError message={form.errors.note} />
                                </div>
                            </div>

                            <div style={{ display: 'flex', gap: 10, marginTop: 18 }}>
                                {editingId && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            const chip = assessments.find(
                                                (a) => a.id === editingId,
                                            );
                                            if (chip) {
                                                setConfirm(chip);
                                            }
                                        }}
                                        style={{
                                            height: 44,
                                            padding: '0 14px',
                                            background: 'rgba(220,38,38,.07)',
                                            color: C.red,
                                            border: '1px solid rgba(220,38,38,.35)',
                                            borderRadius: 8,
                                            fontSize: 13,
                                            fontWeight: 600,
                                            cursor: 'pointer',
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 6,
                                        }}
                                    >
                                        <AIcon name="trash-2" size={15} color={C.red} />
                                    </button>
                                )}
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    style={{
                                        ...btnP,
                                        flex: 1,
                                        height: 44,
                                        justifyContent: 'center',
                                        opacity: form.processing ? 0.7 : 1,
                                        cursor: form.processing ? 'not-allowed' : 'pointer',
                                    }}
                                >
                                    <AIcon name="check" size={16} color="#fff" />
                                    Simpan Penilaian
                                </button>
                            </div>
                        </form>

                        {/* Successors list */}
                        <div style={{ ...card, padding: 18 }}>
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginBottom: 12,
                                }}
                            >
                                Calon Penerus
                            </div>
                            {successors.length === 0 && (
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: C.faint,
                                        textAlign: 'center',
                                        padding: '18px 0',
                                    }}
                                >
                                    Belum ada calon penerus.
                                </div>
                            )}
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                {successors.map((chip) => (
                                    <button
                                        key={chip.id}
                                        onClick={() => editChip(chip)}
                                        style={{
                                            textAlign: 'left',
                                            border: `1px solid ${C.border}`,
                                            background: '#fff',
                                            borderRadius: 9,
                                            padding: '10px 12px',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <div
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {chip.employee ?? '—'}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.muted,
                                                marginTop: 2,
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 5,
                                            }}
                                        >
                                            <AIcon
                                                name="arrow-up-right"
                                                size={12}
                                                color={C.faint}
                                            />
                                            {chip.successor_for}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11,
                                                color: C.faint,
                                                marginTop: 4,
                                            }}
                                        >
                                            Kinerja {levelLabel(chip.performance_level)} · Potensi{' '}
                                            {levelLabel(chip.potential_level)}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {confirm && (
                <ConfirmModal
                    title="Hapus penilaian?"
                    body={
                        <>
                            Penilaian untuk{' '}
                            <strong style={{ color: C.text }}>{confirm.employee}</strong> akan
                            dihapus dari matriks.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={remove}
                />
            )}
        </>
    );
}
