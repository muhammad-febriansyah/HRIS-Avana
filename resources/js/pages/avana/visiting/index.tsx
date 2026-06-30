import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import FieldVisitController from '@/actions/App/Http/Controllers/Avana/FieldVisitController';
import { AIcon, btnP, C, card } from '@/lib/avana';
import {
    ConfirmModal,
    filterSelectStyle,
    headThStyle,
    iconBtn,
} from './components';
import type { FlashProps, VisitingIndexProps, VisitRow } from './types';

export default function VisitingIndex({ visits, filters }: VisitingIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = visits.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [confirm, setConfirm] = useState<VisitRow | null>(null);
    const isFirstSearch = useRef(true);

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
                                Visiting Pekerjaan
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
                            Visiting Pekerjaan
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Catat kunjungan lapangan &amp; klien karyawan Anda
                        </div>
                    </div>
                    <Link
                        href={FieldVisitController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Catat Kunjungan
                    </Link>
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
                                                    {row.employee?.initials ??
                                                        '?'}
                                                </div>
                                                <div>
                                                    <div
                                                        style={{
                                                            fontSize: 13,
                                                            fontWeight: 500,
                                                            color: C.text,
                                                        }}
                                                    >
                                                        {row.employee?.name ??
                                                            '—'}
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
                                                            objectFit: 'cover',
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
                                                onClick={() => setConfirm(row)}
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

            {/* Confirm delete modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus kunjungan?"
                    body={
                        <>
                            Kunjungan ke{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.location}
                            </strong>{' '}
                            akan dihapus permanen. Tindakan ini tidak dapat
                            dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteVisit}
                />
            )}
        </>
    );
}
