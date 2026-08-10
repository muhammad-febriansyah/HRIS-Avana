import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import SalaryHistoryController from '@/actions/App/Http/Controllers/Avana/SalaryHistoryController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, card } from '@/lib/avana';

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

interface Props {
    versions: Version[];
    employeeId: number | null;
    employeeOptions: { id: number; name: string; nik: string | null }[];
}

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
    employeeId,
    employeeOptions,
}: Props) {
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
            value === '' ? {} : { employee_id: Number(value) },
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
                            Menampilkan 200 perubahan terbaru seluruh karyawan.
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
                            {versions.length === 0 ? (
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
                                versions.map((v) => (
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
                </div>
            </div>
        </>
    );
}
