import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LearningController from '@/actions/App/Http/Controllers/Avana/LearningController';
import { AIcon, ActionBtn, btnOut, btnP, C, card, rp, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    EnrollmentStatusPill,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    StatusPill,
    TypeChip,
    withError,
} from './components';
import {
    emptyEnrollForm,
    emptyEnrollmentUpdateForm,
    ENROLLMENT_STATUS_OPTIONS,
    typeLabel,
} from './types';
import type {
    EnrollFormData,
    EnrollmentRow,
    EnrollmentUpdateFormData,
    FlashProps,
    PembelajaranIndexProps,
    TrainingRow,
} from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

const cellStyle: CSSProperties = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
};

/** Render a training's start/end window as a compact label. */
function periodLabel(training: TrainingRow): string {
    if (!training.start_date && !training.end_date) {
        return '—';
    }

    return [training.start_date ?? '?', training.end_date ?? '?'].join(' → ');
}

export default function PembelajaranIndex({
    trainings,
    enrollments,
    employees,
    kpis,
}: PembelajaranIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<TrainingRow | null>(null);
    const [enrollModalOpen, setEnrollModalOpen] = useState(false);
    const [updateTarget, setUpdateTarget] = useState<EnrollmentRow | null>(null);

    const enrollForm = useForm<EnrollFormData>({ ...emptyEnrollForm });
    const updateForm = useForm<EnrollmentUpdateFormData>({
        ...emptyEnrollmentUpdateForm,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const deleteTraining = () => {
        if (!confirm) {
            return;
        }

        router.delete(LearningController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const openEnroll = () => {
        enrollForm.clearErrors();
        enrollForm.setData({
            ...emptyEnrollForm,
            training_id: trainings[0] ? String(trainings[0].id) : '',
        });
        setEnrollModalOpen(true);
    };

    const closeEnroll = () => {
        setEnrollModalOpen(false);
        enrollForm.reset();
        enrollForm.clearErrors();
    };

    const submitEnroll = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        enrollForm.submit(LearningController.enroll(), {
            onSuccess: () => closeEnroll(),
        });
    };

    const openUpdate = (enrollment: EnrollmentRow) => {
        updateForm.clearErrors();
        updateForm.setData({
            status: enrollment.status,
            score: enrollment.score === null ? '' : String(enrollment.score),
            certificate_no: enrollment.certificate_no ?? '',
            completed_date: enrollment.completed_date ?? '',
        });
        setUpdateTarget(enrollment);
    };

    const closeUpdate = () => {
        setUpdateTarget(null);
        updateForm.reset();
        updateForm.clearErrors();
    };

    const submitUpdate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!updateTarget) {
            return;
        }

        updateForm.submit(LearningController.updateEnrollment(updateTarget.id), {
            preserveScroll: true,
            onSuccess: () => closeUpdate(),
        });
    };

    const kpiItems = [
        {
            label: 'Total Pelatihan',
            value: kpis.total_training,
            icon: 'graduation-cap',
            color: C.primary,
        },
        {
            label: 'Sedang Berjalan',
            value: kpis.ongoing,
            icon: 'loader',
            color: C.amber,
        },
        {
            label: 'Total Peserta',
            value: kpis.peserta,
            icon: 'users',
            color: C.sky,
        },
        {
            label: 'Selesai',
            value: kpis.completed,
            icon: 'circle-check',
            color: C.green,
        },
    ];

    return (
        <>
            <Head title="Pembelajaran" />
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
                            <span style={{ color: C.muted }}>Pembelajaran</span>
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
                            Pembelajaran (LMS)
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola katalog pelatihan &amp; peserta.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button
                            onClick={openEnroll}
                            disabled={trainings.length === 0}
                            style={{
                                ...btnOut,
                                opacity: trainings.length === 0 ? 0.6 : 1,
                                cursor:
                                    trainings.length === 0
                                        ? 'not-allowed'
                                        : 'pointer',
                            }}
                        >
                            <AIcon name="user-plus" size={16} color={C.text} />
                            Tambah Peserta
                        </button>
                        <Link
                            href={LearningController.create()}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Pelatihan
                        </Link>
                    </div>
                </div>

                {/* KPI cards */}
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 14,
                        marginBottom: 22,
                    }}
                >
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    marginBottom: 10,
                                }}
                            >
                                <div
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 9,
                                        background: `${item.color}1a`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name={item.icon}
                                        size={17}
                                        color={item.color}
                                    />
                                </div>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        fontWeight: 500,
                                    }}
                                >
                                    {item.label}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 26,
                                    fontWeight: 700,
                                    color: C.navy,
                                    letterSpacing: '-.02em',
                                }}
                            >
                                {item.value}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Trainings table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Katalog Pelatihan
                </div>
                <div style={{ ...card, overflow: 'hidden', marginBottom: 28 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 880,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Judul</th>
                                    <th style={thCell}>Kategori</th>
                                    <th style={thCell}>Tipe</th>
                                    <th style={thCell}>Periode</th>
                                    <th style={thCell}>Biaya</th>
                                    <th style={thCell}>Peserta</th>
                                    <th style={thCell}>Status</th>
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
                                {trainings.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={8}
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
                                                    name="graduation-cap"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada pelatihan.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {trainings.map((training) => (
                                    <tr
                                        key={training.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {training.title}
                                            {training.instructor && (
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        fontWeight: 400,
                                                        color: C.faint,
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    {training.instructor}
                                                </div>
                                            )}
                                        </td>
                                        <td style={cellStyle}>
                                            {training.category}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <TypeChip type={training.type} />
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {periodLabel(training)}
                                        </td>
                                        <td style={cellStyle}>
                                            {rp(training.cost)}
                                        </td>
                                        <td style={cellStyle}>
                                            {training.enrollments_count}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusPill
                                                status={training.status}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                    flexWrap: 'wrap',
                                                    justifyContent: 'flex-end',
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Ubah"
                                                    variant="neutral"
                                                    onClick={() =>
                                                        router.visit(
                                                            LearningController.edit(
                                                                training.id,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() =>
                                                        setConfirm(training)
                                                    }
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Enrollments table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Peserta Pelatihan
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 880,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Peserta</th>
                                    <th style={thCell}>Pelatihan</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>Nilai</th>
                                    <th style={thCell}>Sertifikat</th>
                                    <th style={thCell}>Tgl Selesai</th>
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
                                {enrollments.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
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
                                                <AIcon
                                                    name="users"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada peserta.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {enrollments.map((enrollment) => (
                                    <tr
                                        key={enrollment.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {enrollment.employee?.name ?? '—'}
                                            {enrollment.employee
                                                ?.employee_number && (
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        fontWeight: 400,
                                                        color: C.faint,
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    {
                                                        enrollment.employee
                                                            .employee_number
                                                    }
                                                </div>
                                            )}
                                        </td>
                                        <td style={cellStyle}>
                                            {enrollment.training_title ?? '—'}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <EnrollmentStatusPill
                                                status={enrollment.status}
                                            />
                                        </td>
                                        <td style={cellStyle}>
                                            {enrollment.score === null
                                                ? '—'
                                                : enrollment.score}
                                        </td>
                                        <td style={cellStyle}>
                                            {enrollment.certificate_no ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {enrollment.completed_date ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <ActionBtn
                                                icon="award"
                                                label="Perbarui"
                                                variant="primary"
                                                title="Perbarui & sertifikat"
                                                onClick={() =>
                                                    openUpdate(enrollment)
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Add participant modal */}
            {enrollModalOpen && (
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
                        onClick={closeEnroll}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitEnroll}
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
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Tambah Peserta
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Daftarkan karyawan ke sebuah pelatihan.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Pelatihan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={enrollForm.data.training_id}
                                    onChange={(event) =>
                                        enrollForm.setData(
                                            'training_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!enrollForm.errors.training_id,
                                    )}
                                >
                                    <option value="">Pilih pelatihan</option>
                                    {trainings.map((training) => (
                                        <option
                                            key={training.id}
                                            value={String(training.id)}
                                        >
                                            {training.title} (
                                            {typeLabel(training.type)})
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={enrollForm.errors.training_id}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Karyawan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={enrollForm.data.employee_id}
                                    onChange={(event) =>
                                        enrollForm.setData(
                                            'employee_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!enrollForm.errors.employee_id,
                                    )}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name}
                                            {employee.employee_number
                                                ? ` — ${employee.employee_number}`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={enrollForm.errors.employee_id}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Status{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={enrollForm.data.status}
                                    onChange={(event) =>
                                        enrollForm.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!enrollForm.errors.status,
                                    )}
                                >
                                    {ENROLLMENT_STATUS_OPTIONS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={enrollForm.errors.status}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeEnroll}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={enrollForm.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: enrollForm.processing ? 0.7 : 1,
                                    cursor: enrollForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Update enrollment modal */}
            {updateTarget && (
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
                        onClick={closeUpdate}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitUpdate}
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
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Perbarui Peserta
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            {updateTarget.employee?.name ?? 'Peserta'} ·{' '}
                            {updateTarget.training_title ?? '—'}
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Status{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={updateForm.data.status}
                                    onChange={(event) =>
                                        updateForm.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!updateForm.errors.status,
                                    )}
                                >
                                    {ENROLLMENT_STATUS_OPTIONS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={updateForm.errors.status}
                                />
                            </div>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 14,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>Nilai</label>
                                    <input
                                        type="number"
                                        min={0}
                                        max={100}
                                        value={updateForm.data.score}
                                        onChange={(event) =>
                                            updateForm.setData(
                                                'score',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0 - 100"
                                        style={withError(
                                            inputStyle,
                                            !!updateForm.errors.score,
                                        )}
                                    />
                                    <FieldError
                                        message={updateForm.errors.score}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Tgl Selesai
                                    </label>
                                    <input
                                        type="date"
                                        value={updateForm.data.completed_date}
                                        onChange={(event) =>
                                            updateForm.setData(
                                                'completed_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!updateForm.errors.completed_date,
                                        )}
                                    />
                                    <FieldError
                                        message={
                                            updateForm.errors.completed_date
                                        }
                                    />
                                </div>
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    No. Sertifikat
                                </label>
                                <input
                                    type="text"
                                    value={updateForm.data.certificate_no}
                                    onChange={(event) =>
                                        updateForm.setData(
                                            'certificate_no',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Contoh: CERT-1234"
                                    style={withError(
                                        inputStyle,
                                        !!updateForm.errors.certificate_no,
                                    )}
                                />
                                <FieldError
                                    message={updateForm.errors.certificate_no}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeUpdate}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={updateForm.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: updateForm.processing ? 0.7 : 1,
                                    cursor: updateForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete training modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus pelatihan?"
                    body={
                        <>
                            Pelatihan{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.title}
                            </strong>{' '}
                            beserta seluruh pesertanya akan dihapus. Tindakan
                            ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteTraining}
                />
            )}
        </>
    );
}
