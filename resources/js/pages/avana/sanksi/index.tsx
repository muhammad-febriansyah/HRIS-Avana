import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AttendancePenaltyController from '@/actions/App/Http/Controllers/Avana/AttendancePenaltyController';
import { AIcon, btnOut, btnP, C, rp, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    filterSelectStyle,
    iconBtn,
    inputStyle,
    pageItems,
    PenaltyBadge,
    ViolationBadge,
    withError,
} from './components';
import { violationOptions } from './types';
import type { FlashProps, PenaltyRow, SanksiIndexProps } from './types';

export default function SanksiIndex({
    penalties,
    filters,
}: SanksiIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = penalties.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [generateOpen, setGenerateOpen] = useState(false);
    const [confirm, setConfirm] = useState<PenaltyRow | null>(null);
    const isFirstSearch = useRef(true);

    const generateForm = useForm({ start_date: '', end_date: '' });

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

    const openGenerate = () => {
        generateForm.clearErrors();
        generateForm.setData({ start_date: '', end_date: '' });
        setGenerateOpen(true);
    };

    const closeGenerate = () => {
        setGenerateOpen(false);
        generateForm.reset();
        generateForm.clearErrors();
    };

    const submitGenerate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        generateForm.submit(AttendancePenaltyController.generate(), {
            onSuccess: () => closeGenerate(),
        });
    };

    const deletePenalty = () => {
        if (!confirm) {
            return;
        }

        router.delete(AttendancePenaltyController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Sanksi Absensi" />
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
                                Sanksi Absensi
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
                            Sanksi Absensi
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola sanksi pelanggaran kehadiran karyawan
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button onClick={openGenerate} style={btnOut}>
                            <AIcon name="wand-sparkles" size={16} />
                            Generate dari Absensi
                        </button>
                        <Link
                            href={AttendancePenaltyController.create()}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Sanksi
                        </Link>
                    </div>
                </div>

                {/* Table card */}
                <div
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                        overflow: 'hidden',
                    }}
                >
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
                                minWidth: 220,
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
                                placeholder="Cari nama atau NIK karyawan…"
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
                        <select
                            aria-label="Jenis Pelanggaran"
                            value={filters.violation_type ?? ''}
                            onChange={(event) =>
                                applyFilter('violation_type', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Pelanggaran</option>
                            {violationOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <div style={{ flex: 1 }} />
                    </div>

                    {/* Table */}
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
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Tanggal</th>
                                    <th style={thCell}>Pelanggaran</th>
                                    <th style={thCell}>Jenis Sanksi</th>
                                    <th style={thCell}>Nominal</th>
                                    <th style={thCell}>Catatan</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            padding: '12px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {penalties.data.length === 0 && (
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
                                                    name="shield-alert"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada sanksi absensi.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {penalties.data.map((penalty) => (
                                    <tr
                                        key={penalty.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                            transition: 'background .15s',
                                        }}
                                    >
                                        <td style={{ padding: '13px 16px' }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 11,
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        width: 36,
                                                        height: 36,
                                                        borderRadius: '50%',
                                                        flex: 'none',
                                                        background:
                                                            penalty.employee
                                                                ?.avatar_color ??
                                                            C.faint,
                                                        color: '#fff',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        fontSize: 12.5,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {penalty.employee
                                                        ?.initials ?? '?'}
                                                </div>
                                                <div style={{ minWidth: 0 }}>
                                                    <div
                                                        style={{
                                                            fontSize: 13.5,
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {penalty.employee
                                                            ?.name ?? '—'}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 12,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {penalty.employee
                                                            ?.employee_number ??
                                                            '—'}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.muted,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {penalty.date}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <ViolationBadge
                                                type={penalty.violation_type}
                                            />
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <PenaltyBadge
                                                type={penalty.penalty_type}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color:
                                                    penalty.amount > 0
                                                        ? C.text
                                                        : C.faint,
                                                fontWeight:
                                                    penalty.amount > 0
                                                        ? 600
                                                        : 400,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {penalty.amount > 0
                                                ? rp(penalty.amount)
                                                : '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.muted,
                                                maxWidth: 240,
                                            }}
                                        >
                                            {penalty.notes ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <button
                                                title="Hapus"
                                                onClick={() =>
                                                    setConfirm(penalty)
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
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.from ?? 0}–{meta.to ?? 0}
                            </span>{' '}
                            dari{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.total.toLocaleString('id-ID')}
                            </span>{' '}
                            sanksi
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
                                onClick={() => goToPage(meta.current_page - 1)}
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
                                    gap: 5,
                                }}
                            >
                                <AIcon name="chevron-left" size={15} />
                            </button>
                            {pageItems(meta.current_page, meta.last_page).map(
                                (item, index) =>
                                    item === 'gap' ? (
                                        <span
                                            key={`gap-${index}`}
                                            style={{
                                                color: C.faint,
                                                padding: '0 4px',
                                            }}
                                        >
                                            …
                                        </span>
                                    ) : (
                                        <button
                                            key={item}
                                            onClick={() => goToPage(item)}
                                            style={{
                                                height: 34,
                                                minWidth: 34,
                                                border:
                                                    item === meta.current_page
                                                        ? 'none'
                                                        : `1px solid ${C.border}`,
                                                background:
                                                    item === meta.current_page
                                                        ? C.primary
                                                        : '#fff',
                                                borderRadius: 8,
                                                fontSize: 13,
                                                color:
                                                    item === meta.current_page
                                                        ? '#fff'
                                                        : C.text,
                                                fontWeight:
                                                    item === meta.current_page
                                                        ? 600
                                                        : 400,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            {item}
                                        </button>
                                    ),
                            )}
                            <button
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => goToPage(meta.current_page + 1)}
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

            {/* Generate modal */}
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
                            maxWidth: 440,
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
                            Generate dari Absensi
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Buat sanksi peringatan otomatis dari data keterlambatan
                            dan ketidakhadiran pada rentang tanggal.
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
                                    Tanggal Mulai{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={generateForm.data.start_date}
                                    onChange={(event) =>
                                        generateForm.setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!generateForm.errors.start_date,
                                    )}
                                />
                                <FieldError
                                    message={generateForm.errors.start_date}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal Akhir{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={generateForm.data.end_date}
                                    onChange={(event) =>
                                        generateForm.setData(
                                            'end_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!generateForm.errors.end_date,
                                    )}
                                />
                                <FieldError
                                    message={generateForm.errors.end_date}
                                />
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
                                <AIcon
                                    name="wand-sparkles"
                                    size={16}
                                    color="#fff"
                                />
                                Generate
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus sanksi?"
                    body={
                        <>
                            Sanksi untuk{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.employee?.name ?? 'karyawan ini'}
                            </strong>{' '}
                            akan dihapus permanen. Tindakan ini tidak dapat
                            dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deletePenalty}
                />
            )}
        </>
    );
}
