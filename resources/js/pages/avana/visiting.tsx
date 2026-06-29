import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import FieldVisitController from '@/actions/App/Http/Controllers/Avana/FieldVisitController';
import { AIcon, C, card } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** Employee option surfaced in the "Catat Kunjungan" form. */
interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

/** Eager-loaded employee summary attached to a visit row. */
interface VisitEmployee {
    name: string;
    employee_number: string | null;
    initials: string;
    avatar_color: string;
}

/** A single field visit row as serialized by `FieldVisitController@index`. */
interface VisitRow {
    id: number;
    employee: VisitEmployee | null;
    visit_date: string | null;
    location: string;
    client_name: string | null;
    purpose: string | null;
    notes: string | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    status: string;
}

interface VisitingFilters {
    search?: string;
    date?: string;
    per_page?: string;
}

interface VisitingProps {
    visits: {
        data: VisitRow[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    employees: EmployeeOption[];
    filters: VisitingFilters;
}

/** Form payload backing the "Catat Kunjungan" form (file via forceFormData). */
interface VisitFormData {
    employee_id: string;
    visit_date: string;
    location: string;
    client_name: string;
    purpose: string;
    latitude: string;
    longitude: string;
    notes: string;
    photo: File | null;
}

const emptyForm: VisitFormData = {
    employee_id: '',
    visit_date: '',
    location: '',
    client_name: '',
    purpose: '',
    latitude: '',
    longitude: '',
    notes: '',
    photo: null,
};

const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
};

const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const selectStyle: CSSProperties = {
    ...inputStyle,
    color: C.muted,
    cursor: 'pointer',
};

const dateInputStyle: CSSProperties = {
    ...inputStyle,
    fontSize: 12.5,
    color: C.muted,
};

const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    resize: 'vertical',
};

const filterSelectStyle: CSSProperties = {
    height: 38,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.muted,
    background: '#fff',
    cursor: 'pointer',
    outline: 'none',
};

const headThStyle: CSSProperties = {
    padding: '11px 16px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

/** Inline error message rendered under a field. */
function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={errorTextStyle}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Apply the red error border to a base input/select style when invalid. */
function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

export default function AvanaVisiting({
    visits,
    employees,
    filters,
}: VisitingProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = visits.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [confirm, setConfirm] = useState<VisitRow | null>(null);
    const isFirstSearch = useRef(true);

    const form = useForm<VisitFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const applyFilter = (key: string, value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const onPhotoChange = (event: ChangeEvent<HTMLInputElement>) => {
        form.setData('photo', event.target.files?.[0] ?? null);
    };

    const submitVisit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(FieldVisitController.store(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const deleteVisit = () => {
        if (!confirm) {
            return;
        }

        router.delete(FieldVisitController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Visiting Pekerjaan" />
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
                        <span style={{ color: C.muted }}>Visiting Pekerjaan</span>
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
                        Visiting Pekerjaan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Catat kunjungan lapangan &amp; klien karyawan Anda
                    </div>
                </div>

                <div
                    className="avn-abs"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '380px 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Form */}
                    <div style={card}>
                        <div
                            style={{
                                padding: '18px 22px',
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Catat Kunjungan
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 3,
                                }}
                            >
                                Lengkapi detail kunjungan lapangan.
                            </div>
                        </div>
                        <form
                            onSubmit={submitVisit}
                            style={{
                                padding: '20px 22px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
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
                                            {employee.name} (
                                            {employee.employee_number})
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.employee_id} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal Kunjungan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={form.data.visit_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'visit_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        dateInputStyle,
                                        !!form.errors.visit_date,
                                    )}
                                />
                                <FieldError message={form.errors.visit_date} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Lokasi{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    placeholder="Alamat / area kunjungan"
                                    value={form.data.location}
                                    onChange={(event) =>
                                        form.setData(
                                            'location',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.location,
                                    )}
                                />
                                <FieldError message={form.errors.location} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Klien</label>
                                <input
                                    type="text"
                                    placeholder="Nama klien / perusahaan"
                                    value={form.data.client_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'client_name',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.client_name,
                                    )}
                                />
                                <FieldError message={form.errors.client_name} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Tujuan</label>
                                <textarea
                                    rows={2}
                                    placeholder="Tujuan kunjungan"
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            'purpose',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textareaStyle,
                                        !!form.errors.purpose,
                                    )}
                                />
                                <FieldError message={form.errors.purpose} />
                            </div>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Latitude
                                    </label>
                                    <input
                                        type="number"
                                        step="any"
                                        placeholder="-6.2088"
                                        value={form.data.latitude}
                                        onChange={(event) =>
                                            form.setData(
                                                'latitude',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.latitude,
                                        )}
                                    />
                                    <FieldError
                                        message={form.errors.latitude}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Longitude
                                    </label>
                                    <input
                                        type="number"
                                        step="any"
                                        placeholder="106.8456"
                                        value={form.data.longitude}
                                        onChange={(event) =>
                                            form.setData(
                                                'longitude',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!form.errors.longitude,
                                        )}
                                    />
                                    <FieldError
                                        message={form.errors.longitude}
                                    />
                                </div>
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Foto Kunjungan
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={onPhotoChange}
                                    style={{
                                        width: '100%',
                                        fontSize: 12.5,
                                        color: C.muted,
                                    }}
                                />
                                <div
                                    style={{
                                        fontSize: 11,
                                        color: C.faint,
                                        marginTop: 4,
                                    }}
                                >
                                    JPG / PNG · maks 4 MB
                                </div>
                                <FieldError message={form.errors.photo} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Catatan</label>
                                <textarea
                                    rows={2}
                                    placeholder="Catatan tambahan"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textareaStyle,
                                        !!form.errors.notes,
                                    )}
                                />
                                <FieldError message={form.errors.notes} />
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    width: '100%',
                                    height: 44,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: form.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                    opacity: form.processing ? 0.7 : 1,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="map-pin" size={16} color="#fff" />
                                Simpan Kunjungan
                            </button>
                        </form>
                    </div>

                    {/* Table */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        {/* Filter bar */}
                        <div
                            style={{
                                padding: '16px 18px',
                                borderBottom: `1px solid ${C.border}`,
                                display: 'flex',
                                gap: 10,
                                flexWrap: 'wrap',
                                alignItems: 'center',
                            }}
                        >
                            <div
                                style={{
                                    position: 'relative',
                                    flex: 1,
                                    minWidth: 200,
                                    maxWidth: 320,
                                }}
                            >
                                <AIcon
                                    name="search"
                                    size={16}
                                    color={C.faint}
                                    style={{
                                        position: 'absolute',
                                        left: 12,
                                        top: '50%',
                                        transform: 'translateY(-50%)',
                                    }}
                                />
                                <input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari lokasi atau klien…"
                                    style={{
                                        width: '100%',
                                        height: 38,
                                        padding: '0 12px 0 36px',
                                        background: C.surface,
                                        border: '1px solid transparent',
                                        borderRadius: 8,
                                        fontSize: 13,
                                        outline: 'none',
                                        transition: '.15s',
                                    }}
                                />
                            </div>
                            <input
                                type="date"
                                aria-label="Tanggal"
                                value={filters.date ?? ''}
                                onChange={(event) =>
                                    applyFilter('date', event.target.value)
                                }
                                style={filterSelectStyle}
                            />
                            <div style={{ flex: 1 }} />
                        </div>

                        {/* Table */}
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
                                        <th style={headThStyle}>Karyawan</th>
                                        <th style={headThStyle}>Tanggal</th>
                                        <th style={headThStyle}>Lokasi</th>
                                        <th style={headThStyle}>Klien</th>
                                        <th style={headThStyle}>Tujuan</th>
                                        <th style={headThStyle}>Foto</th>
                                        <th
                                            style={{
                                                ...headThStyle,
                                                textAlign: 'right',
                                            }}
                                        >
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visits.data.length === 0 && (
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
                                                        name="map-pin"
                                                        size={28}
                                                        color={C.faint}
                                                    />
                                                    <div>
                                                        Belum ada kunjungan
                                                        tercatat.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {visits.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={{ padding: '12px 16px' }}>
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 10,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            width: 32,
                                                            height: 32,
                                                            borderRadius: '50%',
                                                            flex: 'none',
                                                            background:
                                                                row.employee
                                                                    ?.avatar_color ??
                                                                C.faint,
                                                            color: '#fff',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent:
                                                                'center',
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {row.employee
                                                            ?.initials ?? '?'}
                                                    </div>
                                                    <div>
                                                        <div
                                                            style={{
                                                                fontSize: 13,
                                                                fontWeight: 500,
                                                                color: C.text,
                                                            }}
                                                        >
                                                            {row.employee
                                                                ?.name ?? '—'}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {row.employee
                                                                ?.employee_number ??
                                                                ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12.5,
                                                    color: C.text,
                                                }}
                                            >
                                                {row.visit_date ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12.5,
                                                    color: C.text,
                                                }}
                                            >
                                                {row.location}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                }}
                                            >
                                                {row.client_name ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                    maxWidth: 220,
                                                }}
                                            >
                                                {row.purpose ?? '—'}
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                {row.photo_url ? (
                                                    <a
                                                        href={row.photo_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <img
                                                            src={row.photo_url}
                                                            alt="Foto kunjungan"
                                                            style={{
                                                                width: 40,
                                                                height: 40,
                                                                borderRadius: 8,
                                                                objectFit:
                                                                    'cover',
                                                                border: `1px solid ${C.border}`,
                                                            }}
                                                        />
                                                    </a>
                                                ) : (
                                                    <span
                                                        style={{
                                                            fontSize: 12.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 18px',
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <button
                                                    title="Hapus"
                                                    onClick={() =>
                                                        setConfirm(row)
                                                    }
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="trash-2"
                                                        size={15}
                                                        color={C.red}
                                                    />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination footer */}
                        <div
                            style={{
                                padding: '14px 18px',
                                borderTop: `1px solid ${C.border}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                flexWrap: 'wrap',
                                gap: 12,
                            }}
                        >
                            <div style={{ fontSize: 13, color: C.muted }}>
                                Menampilkan{' '}
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {meta.from ?? 0}–{meta.to ?? 0}
                                </span>{' '}
                                dari{' '}
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {meta.total.toLocaleString('id-ID')}
                                </span>{' '}
                                kunjungan
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    alignItems: 'center',
                                }}
                            >
                                <button
                                    disabled={meta.current_page <= 1}
                                    onClick={() =>
                                        goToPage(meta.current_page - 1)
                                    }
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color:
                                            meta.current_page <= 1
                                                ? C.faint
                                                : C.text,
                                        cursor:
                                            meta.current_page <= 1
                                                ? 'not-allowed'
                                                : 'pointer',
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                    }}
                                >
                                    <AIcon name="chevron-left" size={15} />
                                </button>
                                <span
                                    style={{
                                        fontSize: 13,
                                        color: C.muted,
                                        padding: '0 4px',
                                    }}
                                >
                                    {meta.current_page} / {meta.last_page}
                                </span>
                                <button
                                    disabled={
                                        meta.current_page >= meta.last_page
                                    }
                                    onClick={() =>
                                        goToPage(meta.current_page + 1)
                                    }
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color:
                                            meta.current_page >= meta.last_page
                                                ? C.faint
                                                : C.text,
                                        cursor:
                                            meta.current_page >= meta.last_page
                                                ? 'not-allowed'
                                                : 'pointer',
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                    }}
                                >
                                    <AIcon name="chevron-right" size={15} />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Confirm delete modal */}
            {confirm && (
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
                        onClick={() => setConfirm(null)}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <div
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 400,
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                width: 48,
                                height: 48,
                                borderRadius: 12,
                                background: 'rgba(220,38,38,.1)',
                                color: C.red,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: 16,
                            }}
                        >
                            <AIcon name="trash-2" size={22} color={C.red} />
                        </div>
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Hapus kunjungan?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Kunjungan ke{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.location}
                            </strong>{' '}
                            akan dihapus permanen. Tindakan ini tidak dapat
                            dibatalkan.
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                onClick={() => setConfirm(null)}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                onClick={deleteVisit}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.red,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="trash-2" size={16} />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
};
