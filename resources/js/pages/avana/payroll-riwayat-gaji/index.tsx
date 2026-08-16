import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import SalaryHistoryController from '@/actions/App/Http/Controllers/Avana/SalaryHistoryController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, card } from '@/lib/avana';
import type { PaginationMeta } from '../employees/types';

interface Version {
    id: number;
    employee: string | null;
    employee_number: string | null;
    component: string | null;
    component_type: string | null;
    amount: number;
    effective_start_date: string | null;
    effective_end_date: string | null;
    status: string;
    reason: string | null;
    master: string | null;
    contract: string | null;
    author: string | null;
    can_approve: boolean;
}

/** One Penetapan Gaji Massal run waiting on an approver. */
interface Batch {
    batch_id: string;
    master: string | null;
    effective_start_date: string | null;
    strategy: string | null;
    reason: string | null;
    author: string | null;
    employee_count: number;
    component_count: number;
    total_before: number;
    total_after: number;
    total_delta: number;
    exception_count: number;
    can_approve: boolean;
}

interface Props {
    versions: {
        data: Version[];
        meta: PaginationMeta;
    };
    batches: Batch[];
    employeeId: number | null;
    employeeOptions: { id: number; name: string; nik: string | null }[];
}

const pageItems = (current: number, last: number): (number | 'gap')[] => {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const items: (number | 'gap')[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) {
        items.push('gap');
    }

    for (let page = start; page <= end; page++) {
        items.push(page);
    }

    if (end < last - 1) {
        items.push('gap');
    }

    items.push(last);

    return items;
};

const th: React.CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
    whiteSpace: 'nowrap',
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
    verticalAlign: 'top',
};

const rupiah = (n: number) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

const smallBtn: React.CSSProperties = {
    padding: '5px 11px',
    borderRadius: 7,
    border: `1px solid ${C.line}`,
    background: '#fff',
    fontSize: 12,
    fontWeight: 600,
    cursor: 'pointer',
};
const approveBtn: React.CSSProperties = { ...smallBtn, color: C.green };
const rejectBtn: React.CSSProperties = { ...smallBtn, color: C.red };

const statusStyle = (status: string): React.CSSProperties => {
    const tone =
        status === 'active'
            ? { color: C.green, background: '#F0FDF4' }
            : status === 'pending_approval'
              ? { color: '#B45309', background: '#FFFBEB' }
              : { color: C.faint, background: C.surface };

    return {
        display: 'inline-block',
        padding: '3px 9px',
        borderRadius: 999,
        fontSize: 11.5,
        fontWeight: 600,
        ...tone,
    };
};

const statusLabel: Record<string, string> = {
    active: 'Berlaku',
    draft: 'Draft',
    pending_approval: 'Menunggu persetujuan',
    cancelled: 'Dibatalkan',
};

export default function RiwayatGaji({
    versions,
    batches,
    employeeId,
    employeeOptions,
}: Props) {
    const meta = versions.meta;
    const [rejectReason, setRejectReason] = useState('');

    /** Approve or turn down a whole mass-assignment run in one decision. */
    const decideBatch = (batchId: string, action: 'approve' | 'reject') => {
        if (action === 'reject' && rejectReason.trim() === '') {
            toast.error('Tulis alasan penolakan');

            return;
        }

        router.post(
            action === 'approve'
                ? SalaryHistoryController.approveBatch().url
                : SalaryHistoryController.rejectBatch().url,
            action === 'approve'
                ? { batch_id: batchId }
                : { batch_id: batchId, reason: rejectReason },
            {
                preserveScroll: true,
                onSuccess: () => setRejectReason(''),
                onError: (errors) =>
                    toast.error(errors.batch_id ?? 'Aksi gagal'),
            },
        );
    };
    const decide = (id: number, action: 'approve' | 'reject') =>
        router.post(
            action === 'approve'
                ? SalaryHistoryController.approve(id).url
                : SalaryHistoryController.reject(id).url,
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(errors.status ?? 'Aksi gagal'),
            },
        );

    const pick = (value: string) =>
        router.get(
            SalaryHistoryController.index().url,
            value === '' ? {} : { employee_id: Number(value), page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const goToPage = (page: number) =>
        router.get(
            SalaryHistoryController.index().url,
            {
                employee_id: employeeId ?? undefined,
                page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Riwayat Gaji" />
            <div style={{ padding: '28px 32px' }}>
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
                    <span>Payroll</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Riwayat Gaji</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Riwayat Gaji
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Setiap versi gaji beserta tanggal berlaku, alasan perubahan
                    dan siapa yang menyimpannya. Gaji lama tidak ditimpa, jadi
                    slip gaji periode lalu tetap memakai nominal saat itu.
                </div>

                {batches.length > 0 && (
                    <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                color: C.text,
                                marginBottom: 4,
                            }}
                        >
                            Penetapan Massal Menunggu Persetujuan
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginBottom: 12,
                            }}
                        >
                            Satu penetapan disetujui sekaligus — periksa jumlah
                            karyawan, total perubahan, dan pengecualiannya dulu.
                        </div>

                        {batches.map((batch) => (
                            <div
                                key={batch.batch_id}
                                style={{
                                    border: `1px solid ${C.line}`,
                                    borderRadius: 10,
                                    padding: 14,
                                    marginBottom: 10,
                                    display: 'grid',
                                    gap: 10,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        flexWrap: 'wrap',
                                        gap: 18,
                                        fontSize: 13,
                                        color: C.text,
                                    }}
                                >
                                    <span>
                                        <strong>{batch.master ?? '—'}</strong> ·
                                        berlaku {batch.effective_start_date}
                                    </span>
                                    <span>
                                        {batch.employee_count} karyawan ·{' '}
                                        {batch.component_count} komponen
                                    </span>
                                    <span>
                                        Total {rupiah(batch.total_before)} →{' '}
                                        <strong>
                                            {rupiah(batch.total_after)}
                                        </strong>{' '}
                                        (
                                        {batch.total_delta >= 0 ? '+' : ''}
                                        {rupiah(batch.total_delta)})
                                    </span>
                                    <span
                                        style={{
                                            color:
                                                batch.exception_count > 0
                                                    ? '#B45309'
                                                    : C.muted,
                                        }}
                                    >
                                        {batch.exception_count} pengecualian
                                        (nominal khusus)
                                    </span>
                                    <span style={{ color: C.faint }}>
                                        oleh {batch.author ?? '—'} ·{' '}
                                        {batch.strategy === 'overwrite'
                                            ? 'timpa nominal khusus'
                                            : 'pertahankan nominal khusus'}
                                    </span>
                                </div>

                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        flexWrap: 'wrap',
                                        alignItems: 'center',
                                    }}
                                >
                                    <input
                                        style={{
                                            padding: '7px 11px',
                                            borderRadius: 8,
                                            border: `1px solid ${C.line}`,
                                            fontSize: 12.5,
                                            minWidth: 220,
                                        }}
                                        placeholder="Alasan penolakan…"
                                        value={rejectReason}
                                        onChange={(e) =>
                                            setRejectReason(e.target.value)
                                        }
                                    />
                                    <button
                                        type="button"
                                        style={approveBtn}
                                        disabled={!batch.can_approve}
                                        title={
                                            batch.can_approve
                                                ? undefined
                                                : 'Penetapan yang Anda buat sendiri harus disetujui orang lain'
                                        }
                                        onClick={() =>
                                            decideBatch(
                                                batch.batch_id,
                                                'approve',
                                            )
                                        }
                                    >
                                        Setujui semua
                                    </button>
                                    <button
                                        type="button"
                                        style={rejectBtn}
                                        onClick={() =>
                                            decideBatch(batch.batch_id, 'reject')
                                        }
                                    >
                                        Tolak semua
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div style={{ maxWidth: 420 }}>
                        <SearchableSelect
                            value={employeeId === null ? '' : String(employeeId)}
                            onChange={pick}
                            placeholder="Semua karyawan — pilih untuk memfilter…"
                            searchPlaceholder="Cari NIK / nama…"
                            allowClear
                            options={employeeOptions.map((e) => ({
                                value: String(e.id),
                                label: (e.nik ? e.nik + ' · ' : '') + e.name,
                            }))}
                        />
                    </div>
                    {employeeId === null && (
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginTop: 10,
                            }}
                        >
                            Menampilkan riwayat gaji terbaru seluruh karyawan.
                        </div>
                    )}
                </div>

                <div style={{ ...card, overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                {[
                                    'Karyawan',
                                    'Komponen',
                                    'Nominal',
                                    'Berlaku',
                                    'Status',
                                    'Alasan',
                                    'Master Gaji',
                                    'Kontrak',
                                    'Dibuat oleh',
                                    'Aksi',
                                ].map((h) => (
                                    <th key={h} style={th}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {versions.data.length === 0 ? (
                                <tr>
                                    <td
                                        style={{
                                            ...td,
                                            color: C.faint,
                                            textAlign: 'center',
                                            padding: 28,
                                        }}
                                        colSpan={10}
                                    >
                                        Belum ada perubahan gaji tercatat.
                                    </td>
                                </tr>
                            ) : (
                                versions.data.map((v) => (
                                    <tr key={v.id}>
                                        <td style={td}>
                                            {v.employee ?? '—'}
                                            {v.employee_number !== null && (
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {v.employee_number}
                                                </div>
                                            )}
                                        </td>
                                        <td style={td}>
                                            {v.component ?? '—'}
                                            {v.component_type ===
                                                'deduction' && (
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    potongan
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                fontWeight: 600,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {rupiah(v.amount)}
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {v.effective_start_date ??
                                                'sejak awal'}
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                }}
                                            >
                                                {v.effective_end_date === null
                                                    ? 'masih berlaku'
                                                    : 's.d ' +
                                                      v.effective_end_date}
                                            </div>
                                        </td>
                                        <td style={td}>
                                            <span style={statusStyle(v.status)}>
                                                {statusLabel[v.status] ??
                                                    v.status}
                                            </span>
                                        </td>
                                        <td style={{ ...td, maxWidth: 240 }}>
                                            {v.reason ?? '—'}
                                        </td>
                                        <td style={td}>{v.master ?? '—'}</td>
                                        <td style={td}>{v.contract ?? '—'}</td>
                                        <td style={td}>{v.author ?? '—'}</td>
                                        <td style={td}>
                                            {v.status === 'pending_approval' ? (
                                                v.can_approve ? (
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            gap: 6,
                                                        }}
                                                    >
                                                        <button
                                                            type="button"
                                                            style={approveBtn}
                                                            onClick={() =>
                                                                decide(
                                                                    v.id,
                                                                    'approve',
                                                                )
                                                            }
                                                        >
                                                            Setujui
                                                        </button>
                                                        <button
                                                            type="button"
                                                            style={rejectBtn}
                                                            onClick={() =>
                                                                decide(
                                                                    v.id,
                                                                    'reject',
                                                                )
                                                            }
                                                        >
                                                            Tolak
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        menunggu approver lain
                                                    </span>
                                                )
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {meta.last_page > 1 && (
                        <div
                            style={{
                                padding: '14px 18px',
                                borderTop: `1px solid ${C.line}`,
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
                                riwayat gaji
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    alignItems: 'center',
                                }}
                            >
                                <button
                                    type="button"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => goToPage(meta.current_page - 1)}
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.line}`,
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
                                                type="button"
                                                onClick={() => goToPage(item)}
                                                style={{
                                                    height: 34,
                                                    minWidth: 34,
                                                    border:
                                                        item === meta.current_page
                                                            ? 'none'
                                                            : `1px solid ${C.line}`,
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
                                    type="button"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => goToPage(meta.current_page + 1)}
                                    style={{
                                        height: 34,
                                        minWidth: 34,
                                        padding: '0 10px',
                                        border: `1px solid ${C.line}`,
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
                    )}
                </div>
            </div>
        </>
    );
}
