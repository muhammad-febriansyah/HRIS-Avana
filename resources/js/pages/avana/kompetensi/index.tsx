import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CompetencyController from '@/actions/App/Http/Controllers/Avana/CompetencyController';
import { AIcon, ActionBtn, btnP, C, card, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    KpiCard,
    ModalShell,
    textareaStyle,
    withError,
} from './components';
import {
    emptyCompetencyForm,
    LEVEL_OPTIONS,
    type CompetencyFormData,
    type CompetencyRow,
    type FlashProps,
    type KompetensiIndexProps,
} from './types';

/** Background tint for a level chip in the matrix (1 = faint, 5 = strong). */
function levelTint(level: number): string {
    const alpha = 0.08 + level * 0.07;
    return `rgba(47,84,201,${alpha.toFixed(2)})`;
}

export default function KompetensiIndex({
    competencies,
    employees,
    matrix,
    kpis,
}: KompetensiIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<CompetencyRow | null>(null);
    const [confirm, setConfirm] = useState<CompetencyRow | null>(null);

    const form = useForm<CompetencyFormData>({ ...emptyCompetencyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openCreate = () => {
        form.clearErrors();
        form.setData({ ...emptyCompetencyForm });
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (competency: CompetencyRow) => {
        form.clearErrors();
        form.setData({
            name: competency.name,
            category: competency.category ?? '',
            description: competency.description ?? '',
        });
        setEditing(competency);
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

        const action = editing
            ? CompetencyController.update(editing.id)
            : CompetencyController.store();

        form.submit(action, {
            onSuccess: () => closeModal(),
        });
    };

    const remove = () => {
        if (!confirm) {
            return;
        }

        router.delete(CompetencyController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    /** Persist a single matrix cell's level via the assess endpoint. */
    const assess = (employeeId: number, competencyId: number, level: string) => {
        if (level === '') {
            return;
        }

        router.post(
            CompetencyController.assess().url,
            {
                employee_id: employeeId,
                competency_id: competencyId,
                level: Number(level),
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Kompetensi" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
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
                            <span style={{ color: C.muted }}>Kompetensi</span>
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
                            Kerangka Kompetensi
                        </h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                            Kelola kamus kompetensi &amp; nilai level karyawan.
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Kompetensi
                    </button>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    <KpiCard
                        label="Jumlah Kompetensi"
                        value={kpis.total_competencies}
                        icon="list-checks"
                        color={C.primary}
                    />
                    <KpiCard
                        label="Rata-rata Level"
                        value={kpis.average_level.toFixed(2)}
                        icon="gauge"
                        color={C.amber}
                    />
                </div>

                {/* Competency master table */}
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 12 }}>
                    Kamus Kompetensi
                </div>
                <div style={{ ...card, overflow: 'hidden', marginBottom: 28 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{ width: '100%', borderCollapse: 'collapse', minWidth: 720 }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Nama</th>
                                    <th style={thCell}>Kategori</th>
                                    <th style={thCell}>Deskripsi</th>
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
                                {competencies.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            colSpan={4}
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
                                                <AIcon
                                                    name="list-checks"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada kompetensi.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {competencies.map((competency) => (
                                    <tr
                                        key={competency.id}
                                        style={{ borderTop: `1px solid ${C.line}` }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {competency.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {competency.category ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.muted,
                                                maxWidth: 320,
                                            }}
                                        >
                                            {competency.description ?? '—'}
                                        </td>
                                        <td style={{ padding: '13px 18px', textAlign: 'right' }}>
                                            <div style={{ display: 'inline-flex', gap: 6 }}>
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Ubah"
                                                    variant="neutral"
                                                    onClick={() => openEdit(competency)}
                                                />
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() => setConfirm(competency)}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Assessment matrix */}
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 12 }}>
                    Matriks Penilaian
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    {competencies.length === 0 || employees.length === 0 ? (
                        <div
                            style={{
                                padding: '48px 18px',
                                textAlign: 'center',
                                fontSize: 13.5,
                                color: C.muted,
                            }}
                        >
                            Tambahkan kompetensi &amp; karyawan untuk mulai menilai.
                        </div>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ borderCollapse: 'collapse', minWidth: 600 }}>
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th
                                            style={{
                                                ...thCell,
                                                position: 'sticky',
                                                left: 0,
                                                background: '#FAFBFD',
                                                minWidth: 200,
                                            }}
                                        >
                                            Karyawan
                                        </th>
                                        {competencies.map((competency) => (
                                            <th
                                                key={competency.id}
                                                style={{ ...thCell, textAlign: 'center', minWidth: 130 }}
                                            >
                                                {competency.name}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees.map((employee) => (
                                        <tr
                                            key={employee.id}
                                            style={{ borderTop: `1px solid ${C.line}` }}
                                        >
                                            <td
                                                style={{
                                                    padding: '11px 16px',
                                                    fontSize: 13,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    position: 'sticky',
                                                    left: 0,
                                                    background: '#fff',
                                                }}
                                            >
                                                {employee.name}
                                            </td>
                                            {competencies.map((competency) => {
                                                const key = `${employee.id}-${competency.id}`;
                                                const level = matrix[key];

                                                return (
                                                    <td
                                                        key={competency.id}
                                                        style={{
                                                            padding: '8px 10px',
                                                            textAlign: 'center',
                                                        }}
                                                    >
                                                        <select
                                                            value={level ?? ''}
                                                            onChange={(event) =>
                                                                assess(
                                                                    employee.id,
                                                                    competency.id,
                                                                    event.target.value,
                                                                )
                                                            }
                                                            style={{
                                                                width: 64,
                                                                height: 34,
                                                                borderRadius: 7,
                                                                border: `1px solid ${C.border}`,
                                                                background: level
                                                                    ? levelTint(level)
                                                                    : '#fff',
                                                                color: level ? C.navy : C.faint,
                                                                fontSize: 13,
                                                                fontWeight: 600,
                                                                cursor: 'pointer',
                                                                textAlign: 'center',
                                                            }}
                                                        >
                                                            <option value="">—</option>
                                                            {LEVEL_OPTIONS.map((option) => (
                                                                <option
                                                                    key={option.value}
                                                                    value={option.value}
                                                                >
                                                                    {option.value}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Add / edit competency modal */}
            {modalOpen && (
                <ModalShell
                    title={editing ? 'Ubah Kompetensi' : 'Tambah Kompetensi'}
                    subtitle="Definisikan kompetensi yang dinilai."
                    onClose={closeModal}
                    onSubmit={submit}
                    processing={form.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Nama <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            placeholder="cth. Komunikasi"
                            style={withError(inputStyle, !!form.errors.name)}
                        />
                        <FieldError message={form.errors.name} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Kategori</label>
                        <input
                            type="text"
                            value={form.data.category}
                            onChange={(event) => form.setData('category', event.target.value)}
                            placeholder="cth. Soft Skill, Teknis"
                            style={withError(inputStyle, !!form.errors.category)}
                        />
                        <FieldError message={form.errors.category} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Deskripsi</label>
                        <textarea
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            placeholder="Deskripsi kompetensi (opsional)"
                            style={withError(textareaStyle, !!form.errors.description)}
                        />
                        <FieldError message={form.errors.description} />
                    </div>
                </ModalShell>
            )}

            {/* Confirm delete competency modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus kompetensi?"
                    body={
                        <>
                            Kompetensi{' '}
                            <strong style={{ color: C.text }}>{confirm.name}</strong> beserta
                            seluruh penilaiannya akan dihapus.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={remove}
                />
            )}
        </>
    );
}
