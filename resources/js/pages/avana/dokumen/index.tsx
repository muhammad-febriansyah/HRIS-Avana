import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import DokumenController from '@/actions/App/Http/Controllers/Avana/DokumenController';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    selectStyle,
    TypeBadge,
    withError,
} from './components';
import { DOCUMENT_TYPE_OPTIONS, emptyDocumentForm } from './types';
import type {
    DocumentFormData,
    DocumentRow,
    DokumenIndexProps,
    FlashProps,
} from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

export default function DokumenIndex({
    documents,
    employees,
    kpis,
}: DokumenIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<DocumentRow | null>(null);
    const [uploadOpen, setUploadOpen] = useState(false);
    const [employeeFilter, setEmployeeFilter] = useState('');

    const form = useForm<DocumentFormData>({ ...emptyDocumentForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const visibleDocuments = useMemo(() => {
        if (!employeeFilter) {
            return documents;
        }

        return documents.filter(
            (document) => String(document.employee_id) === employeeFilter,
        );
    }, [documents, employeeFilter]);

    const openUpload = () => {
        form.clearErrors();
        form.setData({
            ...emptyDocumentForm,
            employee_id: employees[0] ? String(employees[0].id) : '',
        });
        setUploadOpen(true);
    };

    const closeUpload = () => {
        setUploadOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submitUpload = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(DokumenController.store(), {
            forceFormData: true,
            onSuccess: () => closeUpload(),
        });
    };

    const deleteDocument = () => {
        if (!confirm) {
            return;
        }

        router.delete(DokumenController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const kpiItems = [
        {
            label: 'Total Dokumen',
            value: kpis.total_documents,
            icon: 'file-text',
            color: C.primary,
        },
        {
            label: 'Karyawan Berdokumen',
            value: kpis.employees_with_documents,
            icon: 'users',
            color: C.sky,
        },
        {
            label: 'Total Karyawan',
            value: kpis.total_employees,
            icon: 'user-check',
            color: C.green,
        },
    ];

    return (
        <>
            <Head title="Dokumen Karyawan" />
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
                            <span style={{ color: C.muted }}>
                                Dokumen Karyawan
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
                            Dokumen Karyawan
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola dokumen &amp; berkas karyawan.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button
                            onClick={openUpload}
                            disabled={employees.length === 0}
                            style={{
                                ...btnP,
                                opacity: employees.length === 0 ? 0.6 : 1,
                                cursor:
                                    employees.length === 0
                                        ? 'not-allowed'
                                        : 'pointer',
                            }}
                        >
                            <AIcon name="upload" size={16} color="#fff" />
                            Unggah Dokumen
                        </button>
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

                {/* Filter + table heading */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 12,
                        marginBottom: 12,
                    }}
                >
                    <div
                        style={{
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Daftar Dokumen
                    </div>
                    <select
                        value={employeeFilter}
                        onChange={(event) =>
                            setEmployeeFilter(event.target.value)
                        }
                        style={{ ...selectStyle, width: 220, height: 38 }}
                    >
                        <option value="">Semua karyawan</option>
                        {employees.map((employee) => (
                            <option
                                key={employee.id}
                                value={String(employee.id)}
                            >
                                {employee.name ?? '—'}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Documents table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 760,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Nama</th>
                                    <th style={thCell}>Jenis</th>
                                    <th style={thCell}>Ukuran</th>
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
                                {visibleDocuments.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={6}
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
                                                <div>Belum ada dokumen.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {visibleDocuments.map((document) => (
                                    <tr
                                        key={document.id}
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
                                            {document.employee ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {document.name}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <TypeBadge type={document.type} />
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {document.file_size_label ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {document.uploaded_at ?? '—'}
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
                                                {document.download_url && (
                                                    <a
                                                        title="Unduh"
                                                        href={
                                                            document.download_url
                                                        }
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="download"
                                                            size={15}
                                                            color={C.muted}
                                                        />
                                                    </a>
                                                )}
                                                <button
                                                    title="Hapus"
                                                    onClick={() =>
                                                        setConfirm(document)
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

            {/* Upload document modal */}
            {uploadOpen && (
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
                        onClick={closeUpload}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitUpload}
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
                            Unggah Dokumen
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Simpan berkas dokumen untuk seorang karyawan.
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
                                    Karyawan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.employee_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'employee_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.employee_id,
                                    )}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name ?? '—'}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={form.errors.employee_id}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Nama Dokumen{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder="contoh: KTP Budi"
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.name,
                                    )}
                                />
                                <FieldError message={form.errors.name} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Jenis</label>
                                <select
                                    value={form.data.type}
                                    onChange={(event) =>
                                        form.setData('type', event.target.value)
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.type,
                                    )}
                                >
                                    <option value="">Pilih jenis</option>
                                    {DOCUMENT_TYPE_OPTIONS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.type} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    File <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    style={{
                                        ...withError(
                                            inputStyle,
                                            !!form.errors.file,
                                        ),
                                        height: 'auto',
                                        padding: '10px 13px',
                                    }}
                                />
                                <FieldError message={form.errors.file} />
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: C.faint,
                                        marginTop: 6,
                                    }}
                                >
                                    PDF, JPG, PNG, DOC, atau DOCX. Maks 5 MB.
                                </div>
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeUpload}
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
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: form.processing ? 0.7 : 1,
                                    cursor: form.processing
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

            {/* Confirm delete modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus dokumen?"
                    body={
                        <>
                            Dokumen{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.name}
                            </strong>{' '}
                            beserta berkasnya akan dihapus. Tindakan ini tidak
                            dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteDocument}
                />
            )}
        </>
    );
}
