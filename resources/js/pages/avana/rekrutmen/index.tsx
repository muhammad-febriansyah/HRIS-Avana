import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    selectStyle,
    StageBadge,
    StatusPill,
    textareaStyle,
    withError,
} from './components';
import { emptyApplicantForm, employmentTypeLabel } from './types';
import type {
    ApplicantCard,
    ApplicantFormData,
    PostingRow,
    RekrutmenIndexProps,
} from './types';
import type { FlashProps } from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

export default function RekrutmenIndex({
    postings,
    pipeline,
    stages,
    kpis,
}: RekrutmenIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<PostingRow | null>(null);
    const [applicantModalOpen, setApplicantModalOpen] = useState(false);

    const applicantForm = useForm<ApplicantFormData>({ ...emptyApplicantForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const deletePosting = () => {
        if (!confirm) {
            return;
        }

        router.delete(RecruitmentController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const openApplicant = () => {
        applicantForm.clearErrors();
        applicantForm.setData({
            ...emptyApplicantForm,
            job_posting_id: postings[0] ? String(postings[0].id) : '',
            applied_date: new Date().toISOString().slice(0, 10),
        });
        setApplicantModalOpen(true);
    };

    const closeApplicantModal = () => {
        setApplicantModalOpen(false);
        applicantForm.reset();
        applicantForm.clearErrors();
    };

    const submitApplicant = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        applicantForm.submit(RecruitmentController.storeApplicant(), {
            onSuccess: () => closeApplicantModal(),
        });
    };

    /** Move an applicant to an adjacent pipeline stage. */
    const moveStage = (applicant: ApplicantCard, direction: -1 | 1) => {
        const order = stages.map((option) => option.value);
        const currentIndex = order.indexOf(applicant.stage);
        const nextIndex = currentIndex + direction;

        if (nextIndex < 0 || nextIndex >= order.length) {
            return;
        }

        router.post(
            RecruitmentController.moveStage(applicant.id).url,
            { stage: order[nextIndex] },
            { preserveScroll: true },
        );
    };

    const kpiItems = [
        {
            label: 'Lowongan Dibuka',
            value: kpis.open_postings,
            icon: 'briefcase',
            color: C.primary,
        },
        {
            label: 'Total Pelamar',
            value: kpis.total_applicants,
            icon: 'users',
            color: C.sky,
        },
        {
            label: 'Dalam Proses',
            value: kpis.in_process,
            icon: 'loader',
            color: C.amber,
        },
        {
            label: 'Diterima',
            value: kpis.hired,
            icon: 'circle-check',
            color: C.green,
        },
    ];

    return (
        <>
            <Head title="Rekrutmen" />
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
                            <span style={{ color: C.muted }}>Rekrutmen</span>
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
                            Rekrutmen (ATS)
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola lowongan &amp; pipeline pelamar.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button
                            onClick={openApplicant}
                            disabled={postings.length === 0}
                            style={{
                                ...btnOut,
                                opacity: postings.length === 0 ? 0.6 : 1,
                                cursor:
                                    postings.length === 0
                                        ? 'not-allowed'
                                        : 'pointer',
                            }}
                        >
                            <AIcon name="user-plus" size={16} color={C.text} />
                            Tambah Pelamar
                        </button>
                        <Link
                            href={RecruitmentController.create()}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Lowongan
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

                {/* Job postings table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Daftar Lowongan
                </div>
                <div style={{ ...card, overflow: 'hidden', marginBottom: 28 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 820,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Judul</th>
                                    <th style={thCell}>Departemen</th>
                                    <th style={thCell}>Lokasi</th>
                                    <th style={thCell}>Tipe</th>
                                    <th style={thCell}>Kuota</th>
                                    <th style={thCell}>Pelamar</th>
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
                                {postings.length === 0 && (
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
                                                    name="briefcase"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada lowongan.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {postings.map((posting) => (
                                    <tr
                                        key={posting.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {posting.title}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {posting.department ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {posting.location ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {employmentTypeLabel(
                                                posting.employment_type,
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {posting.quota}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {posting.applicants_count}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusPill
                                                status={posting.status}
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
                                                }}
                                            >
                                                <Link
                                                    title="Ubah"
                                                    href={RecruitmentController.edit(
                                                        posting.id,
                                                    )}
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="pencil"
                                                        size={15}
                                                        color={C.muted}
                                                    />
                                                </Link>
                                                <button
                                                    title="Hapus"
                                                    onClick={() =>
                                                        setConfirm(posting)
                                                    }
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="trash-2"
                                                        size={15}
                                                        color={C.red}
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Applicant pipeline board */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Pipeline Pelamar
                </div>
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        overflowX: 'auto',
                        paddingBottom: 8,
                    }}
                >
                    {stages.map((stage) => {
                        const cards = pipeline[stage.value] ?? [];

                        return (
                            <div
                                key={stage.value}
                                style={{
                                    ...card,
                                    flex: '0 0 260px',
                                    background: C.surface,
                                    padding: 12,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                    }}
                                >
                                    <StageBadge
                                        stage={stage.value}
                                        label={stage.label}
                                    />
                                    <span
                                        style={{
                                            fontSize: 12,
                                            fontWeight: 600,
                                            color: C.muted,
                                        }}
                                    >
                                        {cards.length}
                                    </span>
                                </div>

                                {cards.length === 0 && (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.faint,
                                            textAlign: 'center',
                                            padding: '18px 0',
                                        }}
                                    >
                                        Kosong
                                    </div>
                                )}

                                {cards.map((applicant) => (
                                    <div
                                        key={applicant.id}
                                        style={{
                                            background: '#fff',
                                            border: `1px solid ${C.border}`,
                                            borderRadius: 10,
                                            padding: 12,
                                        }}
                                    >
                                        <Link
                                            href={RecruitmentController.showApplicant(
                                                applicant.id,
                                            )}
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                                textDecoration: 'none',
                                                cursor: 'pointer',
                                            }}
                                        >
                                            {applicant.name}
                                        </Link>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.muted,
                                                marginTop: 2,
                                            }}
                                        >
                                            {applicant.job_title ?? '—'}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                                marginTop: 6,
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 5,
                                            }}
                                        >
                                            <AIcon
                                                name="mail"
                                                size={12}
                                                color={C.faint}
                                            />
                                            {applicant.email}
                                        </div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                                marginTop: 10,
                                            }}
                                        >
                                            <button
                                                title="Tahap sebelumnya"
                                                onClick={() =>
                                                    moveStage(applicant, -1)
                                                }
                                                style={iconBtn}
                                            >
                                                <AIcon
                                                    name="chevron-left"
                                                    size={15}
                                                    color={C.muted}
                                                />
                                            </button>
                                            <span
                                                style={{
                                                    fontSize: 11,
                                                    color: C.faint,
                                                }}
                                            >
                                                {applicant.applied_date ?? ''}
                                            </span>
                                            <button
                                                title="Tahap berikutnya"
                                                onClick={() =>
                                                    moveStage(applicant, 1)
                                                }
                                                style={iconBtn}
                                            >
                                                <AIcon
                                                    name="chevron-right"
                                                    size={15}
                                                    color={C.muted}
                                                />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Add applicant modal */}
            {applicantModalOpen && (
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
                        onClick={closeApplicantModal}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitApplicant}
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
                            Tambah Pelamar
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Catat pelamar baru untuk sebuah lowongan.
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
                                    Lowongan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={applicantForm.data.job_posting_id}
                                    onChange={(event) =>
                                        applicantForm.setData(
                                            'job_posting_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!applicantForm.errors.job_posting_id,
                                    )}
                                >
                                    <option value="">Pilih lowongan</option>
                                    {postings.map((posting) => (
                                        <option
                                            key={posting.id}
                                            value={String(posting.id)}
                                        >
                                            {posting.title}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={
                                        applicantForm.errors.job_posting_id
                                    }
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Nama <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={applicantForm.data.name}
                                    onChange={(event) =>
                                        applicantForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Nama pelamar"
                                    style={withError(
                                        inputStyle,
                                        !!applicantForm.errors.name,
                                    )}
                                />
                                <FieldError
                                    message={applicantForm.errors.name}
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
                                    <label style={fieldLabelStyle}>
                                        Email{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        type="email"
                                        value={applicantForm.data.email}
                                        onChange={(event) =>
                                            applicantForm.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="email@contoh.com"
                                        style={withError(
                                            inputStyle,
                                            !!applicantForm.errors.email,
                                        )}
                                    />
                                    <FieldError
                                        message={applicantForm.errors.email}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Telepon
                                    </label>
                                    <input
                                        type="text"
                                        value={applicantForm.data.phone}
                                        onChange={(event) =>
                                            applicantForm.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="08xxxxxxxxxx"
                                        style={withError(
                                            inputStyle,
                                            !!applicantForm.errors.phone,
                                        )}
                                    />
                                    <FieldError
                                        message={applicantForm.errors.phone}
                                    />
                                </div>
                            </div>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 14,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Sumber
                                    </label>
                                    <input
                                        type="text"
                                        value={applicantForm.data.source}
                                        onChange={(event) =>
                                            applicantForm.setData(
                                                'source',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="LinkedIn, Referral, ..."
                                        style={withError(
                                            inputStyle,
                                            !!applicantForm.errors.source,
                                        )}
                                    />
                                    <FieldError
                                        message={applicantForm.errors.source}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Tahap{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <select
                                        value={applicantForm.data.stage}
                                        onChange={(event) =>
                                            applicantForm.setData(
                                                'stage',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!applicantForm.errors.stage,
                                        )}
                                    >
                                        {stages.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError
                                        message={applicantForm.errors.stage}
                                    />
                                </div>
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal Melamar{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={applicantForm.data.applied_date}
                                    onChange={(event) =>
                                        applicantForm.setData(
                                            'applied_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!applicantForm.errors.applied_date,
                                    )}
                                />
                                <FieldError
                                    message={applicantForm.errors.applied_date}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Catatan</label>
                                <textarea
                                    value={applicantForm.data.notes}
                                    onChange={(event) =>
                                        applicantForm.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Catatan (opsional)"
                                    style={withError(
                                        textareaStyle,
                                        !!applicantForm.errors.notes,
                                    )}
                                />
                                <FieldError
                                    message={applicantForm.errors.notes}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeApplicantModal}
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
                                disabled={applicantForm.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: applicantForm.processing ? 0.7 : 1,
                                    cursor: applicantForm.processing
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

            {/* Confirm delete posting modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus lowongan?"
                    body={
                        <>
                            Lowongan{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.title}
                            </strong>{' '}
                            beserta seluruh pelamarnya akan dihapus. Tindakan
                            ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deletePosting}
                />
            )}
        </>
    );
}
