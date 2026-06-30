import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import SalaryStructureController from '@/actions/App/Http/Controllers/Avana/SalaryStructureController';
import { ActionBtn, AIcon, btnOut, btnP, C, card, rp, RupiahInput, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    KpiRow,
    PageHeader,
    withError,
} from './components';
import { emptyGradeForm } from './types';
import type { GradeFormData, GradeRow, SalaryStructureIndexProps } from './types';
import type { FlashProps } from './types';

const cellStyle = { padding: '13px 16px', fontSize: 13, color: C.text } as const;

export default function SalaryStructureIndex({ grades, kpis }: SalaryStructureIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<GradeRow | null>(null);
    const [confirm, setConfirm] = useState<GradeRow | null>(null);

    const form = useForm<GradeFormData>({ ...emptyGradeForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyGradeForm });
        setModalOpen(true);
    };

    const openEdit = (grade: GradeRow) => {
        setEditing(grade);
        form.clearErrors();
        form.setData({
            grade_code: grade.grade_code,
            grade_name: grade.grade_name,
            level: String(grade.level),
            min_salary: String(grade.min_salary),
            mid_salary: String(grade.mid_salary),
            max_salary: String(grade.max_salary),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing) {
            form.submit(SalaryStructureController.update(editing.id), {
                onSuccess: () => closeModal(),
            });
        } else {
            form.submit(SalaryStructureController.store(), {
                onSuccess: () => closeModal(),
            });
        }
    };

    const deleteGrade = () => {
        if (!confirm) {
            return;
        }

        router.delete(SalaryStructureController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Struktur & Skala Upah" />
            <div style={{ padding: '28px 32px' }}>
                <PageHeader
                    crumb="Payroll"
                    title="Struktur & Skala Upah"
                    subtitle="Kelola grade dan rentang gaji per level jabatan."
                    actions={
                        <button onClick={openCreate} style={btnP}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Grade
                        </button>
                    }
                />

                <KpiRow
                    items={[
                        {
                            label: 'Jumlah Grade',
                            value: kpis.total_grades,
                            icon: 'layers',
                            color: C.primary,
                        },
                        {
                            label: 'Upah Terendah',
                            value: rp(kpis.lowest_salary),
                            icon: 'trending-down',
                            color: C.sky,
                        },
                        {
                            label: 'Upah Tertinggi',
                            value: rp(kpis.highest_salary),
                            icon: 'trending-up',
                            color: C.green,
                        },
                    ]}
                />

                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 880 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Kode</th>
                                    <th style={thCell}>Nama Grade</th>
                                    <th style={thCell}>Level</th>
                                    <th style={thCell}>Min</th>
                                    <th style={thCell}>Mid</th>
                                    <th style={thCell}>Max</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                            padding: '12px 18px',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {grades.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            colSpan={7}
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon name="layers" size={28} color={C.faint} />
                                                <div>Belum ada grade upah.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {grades.map((grade) => (
                                    <tr key={grade.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {grade.grade_code}
                                        </td>
                                        <td style={cellStyle}>{grade.grade_name}</td>
                                        <td style={cellStyle}>{grade.level}</td>
                                        <td style={cellStyle}>{rp(grade.min_salary)}</td>
                                        <td style={cellStyle}>{rp(grade.mid_salary)}</td>
                                        <td style={cellStyle}>{rp(grade.max_salary)}</td>
                                        <td style={{ padding: '13px 18px', textAlign: 'right' }}>
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                    justifyContent: 'flex-end',
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Ubah"
                                                    variant="primary"
                                                    onClick={() => openEdit(grade)}
                                                />
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() => setConfirm(grade)}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Create / edit modal */}
            {modalOpen && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 80,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={closeModal}
                        style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }}
                    />
                    <form
                        onSubmit={submit}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 460,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                            {editing ? 'Ubah Grade' : 'Tambah Grade'}
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Kode <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.grade_code}
                                    onChange={(event) =>
                                        form.setData('grade_code', event.target.value)
                                    }
                                    placeholder="G1"
                                    style={withError(inputStyle, !!form.errors.grade_code)}
                                />
                                <FieldError message={form.errors.grade_code} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Level <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={form.data.level}
                                    onChange={(event) => form.setData('level', event.target.value)}
                                    placeholder="1"
                                    style={withError(inputStyle, !!form.errors.level)}
                                />
                                <FieldError message={form.errors.level} />
                            </div>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Nama Grade <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.grade_name}
                                onChange={(event) => form.setData('grade_name', event.target.value)}
                                placeholder="Staff"
                                style={withError(inputStyle, !!form.errors.grade_name)}
                            />
                            <FieldError message={form.errors.grade_name} />
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Upah Minimum <span style={{ color: C.red }}>*</span>
                            </label>
                            <RupiahInput
                                value={form.data.min_salary}
                                onChange={(raw) => form.setData('min_salary', raw)}
                                invalid={!!form.errors.min_salary}
                            />
                            <FieldError message={form.errors.min_salary} />
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Upah Tengah <span style={{ color: C.red }}>*</span>
                            </label>
                            <RupiahInput
                                value={form.data.mid_salary}
                                onChange={(raw) => form.setData('mid_salary', raw)}
                                invalid={!!form.errors.mid_salary}
                            />
                            <FieldError message={form.errors.mid_salary} />
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Upah Maksimum <span style={{ color: C.red }}>*</span>
                            </label>
                            <RupiahInput
                                value={form.data.max_salary}
                                onChange={(raw) => form.setData('max_salary', raw)}
                                invalid={!!form.errors.max_salary}
                            />
                            <FieldError message={form.errors.max_salary} />
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
                            <button
                                type="button"
                                onClick={closeModal}
                                style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}
                            >
                                Batal
                            </button>
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
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete */}
            {confirm && (
                <ConfirmModal
                    title="Hapus grade?"
                    body={
                        <>
                            Grade{' '}
                            <strong style={{ color: C.text }}>{confirm.grade_name}</strong> akan
                            dihapus.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteGrade}
                />
            )}
        </>
    );
}
