import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LetterTemplateController from '@/actions/App/Http/Controllers/Avana/LetterTemplateController';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    ActivePill,
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    selectStyle,
    withError,
} from './components';
import { emptyGenerateForm } from './types';
import type {
    FlashProps,
    GenerateFormData,
    GeneratedLetterRow,
    SuratIndexProps,
    TemplateRow,
} from './types';

const tdStyle = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
} as const;

export default function SuratIndex({
    templates,
    generatedLetters,
    employees,
    templateOptions,
}: SuratIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirmTemplate, setConfirmTemplate] = useState<TemplateRow | null>(
        null,
    );
    const [confirmLetter, setConfirmLetter] =
        useState<GeneratedLetterRow | null>(null);
    const [generateOpen, setGenerateOpen] = useState(false);

    const generateForm = useForm<GenerateFormData>({ ...emptyGenerateForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openGenerate = () => {
        generateForm.clearErrors();
        generateForm.setData({
            ...emptyGenerateForm,
            letter_template_id: templateOptions[0]
                ? String(templateOptions[0].value)
                : '',
            employee_id: employees[0] ? String(employees[0].id) : '',
            generated_at: new Date().toISOString().slice(0, 10),
        });
        setGenerateOpen(true);
    };

    const closeGenerate = () => {
        setGenerateOpen(false);
        generateForm.reset();
        generateForm.clearErrors();
    };

    const submitGenerate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        generateForm.submit(LetterTemplateController.generate(), {
            onSuccess: () => closeGenerate(),
        });
    };

    const deleteTemplate = () => {
        if (!confirmTemplate) {
            return;
        }

        router.delete(LetterTemplateController.destroy(confirmTemplate.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirmTemplate(null),
        });
    };

    const deleteLetter = () => {
        if (!confirmLetter) {
            return;
        }

        router.delete(
            LetterTemplateController.destroyGenerated(confirmLetter.id).url,
            {
                preserveScroll: true,
                onSuccess: () => setConfirmLetter(null),
            },
        );
    };

    const canGenerate = templateOptions.length > 0 && employees.length > 0;

    return (
        <>
            <Head title="Template Surat" />
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
                            <span>Sistem</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Template Surat
                            </span>
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
                            Template Surat
                        </h1>
                        <div
                            style={{ fontSize: 14, color: C.muted, marginTop: 4 }}
                        >
                            Kelola template &amp; buat surat HR otomatis.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button
                            onClick={openGenerate}
                            disabled={!canGenerate}
                            style={{
                                ...btnOut,
                                opacity: canGenerate ? 1 : 0.6,
                                cursor: canGenerate ? 'pointer' : 'not-allowed',
                            }}
                        >
                            <AIcon
                                name="file-plus"
                                size={16}
                                color={C.text}
                            />
                            Generate Surat
                        </button>
                        <Link
                            href={LetterTemplateController.create()}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Buat Template
                        </Link>
                    </div>
                </div>

                {/* Templates table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Daftar Template
                </div>
                <div style={{ ...card, overflow: 'hidden', marginBottom: 28 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 640,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Nama</th>
                                    <th style={thCell}>Jenis</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>Diperbarui</th>
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
                                {templates.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={5}
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
                                                    name="file-text"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada template surat.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {templates.map((template) => (
                                    <tr
                                        key={template.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                ...tdStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {template.name}
                                        </td>
                                        <td style={tdStyle}>
                                            {template.type_label}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <ActivePill
                                                active={template.is_active}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                ...tdStyle,
                                                color: C.muted,
                                            }}
                                        >
                                            {template.updated_at ?? '—'}
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
                                                    href={LetterTemplateController.edit(
                                                        template.id,
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
                                                        setConfirmTemplate(
                                                            template,
                                                        )
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

                {/* Generated letters table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Surat Tergenerate
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 720,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Judul</th>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Nomor</th>
                                    <th style={thCell}>Tanggal</th>
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
                                {generatedLetters.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={5}
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
                                                    name="mail"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada surat dibuat.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {generatedLetters.map((letter) => (
                                    <tr
                                        key={letter.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                ...tdStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {letter.title}
                                        </td>
                                        <td style={tdStyle}>
                                            {letter.employee_name ?? '—'}
                                        </td>
                                        <td style={tdStyle}>
                                            {letter.letter_number ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                ...tdStyle,
                                                color: C.muted,
                                            }}
                                        >
                                            {letter.generated_at ?? '—'}
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
                                                    title="Cetak"
                                                    href={LetterTemplateController.print(
                                                        letter.id,
                                                    )}
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="printer"
                                                        size={15}
                                                        color={C.muted}
                                                    />
                                                </Link>
                                                <button
                                                    title="Hapus"
                                                    onClick={() =>
                                                        setConfirmLetter(letter)
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
            </div>

            {/* Generate letter modal */}
            {generateOpen && (
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
                        onClick={closeGenerate}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitGenerate}
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
                            Generate Surat
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Pilih template &amp; karyawan, placeholder diisi
                            otomatis.
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
                                    Template{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={generateForm.data.letter_template_id}
                                    onChange={(event) =>
                                        generateForm.setData(
                                            'letter_template_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!generateForm.errors
                                            .letter_template_id,
                                    )}
                                >
                                    <option value="">Pilih template</option>
                                    {templateOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={
                                        generateForm.errors.letter_template_id
                                    }
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Karyawan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={generateForm.data.employee_id}
                                    onChange={(event) =>
                                        generateForm.setData(
                                            'employee_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!generateForm.errors.employee_id,
                                    )}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={generateForm.errors.employee_id}
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
                                        Nomor Surat
                                    </label>
                                    <input
                                        type="text"
                                        value={generateForm.data.letter_number}
                                        onChange={(event) =>
                                            generateForm.setData(
                                                'letter_number',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="001/HR/2026"
                                        style={withError(
                                            inputStyle,
                                            !!generateForm.errors.letter_number,
                                        )}
                                    />
                                    <FieldError
                                        message={
                                            generateForm.errors.letter_number
                                        }
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Tanggal
                                    </label>
                                    <input
                                        type="date"
                                        value={generateForm.data.generated_at}
                                        onChange={(event) =>
                                            generateForm.setData(
                                                'generated_at',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!generateForm.errors.generated_at,
                                        )}
                                    />
                                    <FieldError
                                        message={
                                            generateForm.errors.generated_at
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeGenerate}
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
                                disabled={generateForm.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: generateForm.processing ? 0.7 : 1,
                                    cursor: generateForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Buat Surat
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete template */}
            {confirmTemplate && (
                <ConfirmModal
                    title="Hapus template?"
                    body={
                        <>
                            Template{' '}
                            <strong style={{ color: C.text }}>
                                {confirmTemplate.name}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirmTemplate(null)}
                    onConfirm={deleteTemplate}
                />
            )}

            {/* Confirm delete generated letter */}
            {confirmLetter && (
                <ConfirmModal
                    title="Hapus surat?"
                    body={
                        <>
                            Surat{' '}
                            <strong style={{ color: C.text }}>
                                {confirmLetter.title}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirmLetter(null)}
                    onConfirm={deleteLetter}
                />
            )}
        </>
    );
}
