import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ContractController from '@/actions/App/Http/Controllers/Avana/ContractController';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import {
    ActionBtn,
    AIcon,
    btnOut,
    btnP,
    C,
    card,
    statusBadge,
} from '@/lib/avana';
import type {
    Employee,
    EmployeeContractRow,
    EmployeeDocumentRow,
    FlashProps,
    HeldAsset,
    LeaveHistoryRow,
    PayrollHistoryRow,
} from './types';

interface EmployeesShowProps {
    employee: {
        data: Employee;
    };
}

const empTabs = [
    { id: 'pribadi', label: 'Data Pribadi', icon: 'user' },
    { id: 'pegawai', label: 'Kepegawaian', icon: 'briefcase' },
    { id: 'kontrak', label: 'Kontrak', icon: 'file-text' },
    { id: 'dokumen', label: 'Dokumen', icon: 'folder' },
    { id: 'cuti', label: 'Cuti', icon: 'palmtree' },
    { id: 'payrolltab', label: 'Payroll', icon: 'wallet' },
    { id: 'aset', label: 'Aset', icon: 'package' },
] as const;

const GENDER_LABELS: Record<string, string> = {
    male: 'Laki-laki',
    female: 'Perempuan',
    unspecified: 'Tidak ditentukan',
};

/** Per-employee attendance scope override; an absent key follows the tenant policy. */
const ATTENDANCE_SCOPE_LABELS: Record<string, string> = {
    assigned: 'Lokasi kerja sendiri',
    any_branch: 'Semua cabang (radius tetap berlaku)',
    anywhere: 'Bebas di mana saja (WFA)',
};

const fieldLabel: CSSProperties = { fontSize: 12, color: C.faint };
const fieldValue: CSSProperties = { fontSize: 14, color: C.text, marginTop: 3 };
const thCell: CSSProperties = {
    padding: '12px 18px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

function dash(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value : '—';
}

/** A single labeled value cell inside an info grid (matches prototype). */
function Cell({
    label,
    value,
    indent = false,
    last = false,
    full = false,
}: {
    label: string;
    value: ReactNode;
    indent?: boolean;
    last?: boolean;
    full?: boolean;
}) {
    return (
        <div
            style={{
                padding: '14px 0',
                borderBottom: last ? undefined : '1px solid #F5F7FB',
                paddingLeft: indent ? 24 : undefined,
                gridColumn: full ? '1/-1' : undefined,
            }}
        >
            <div style={fieldLabel}>{label}</div>
            <div style={fieldValue}>{value}</div>
        </div>
    );
}

export default function EmployeesShow({ employee }: EmployeesShowProps) {
    const emp = employee.data;
    const badge = statusBadge(emp.employment_label);
    const { flash } = usePage<FlashProps>().props;
    const [activeTab, setActiveTab] = useState<string>('pribadi');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const birthInfo = [emp.birth_place, emp.birth_date]
        .filter((part) => part && part.trim() !== '')
        .join(', ');

    return (
        <>
            <Head title={emp.full_name} />
            <div style={{ padding: '28px 32px' }}>
                {/* Breadcrumb */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 18,
                    }}
                >
                    <Link
                        href={EmployeeController.index()}
                        style={{
                            cursor: 'pointer',
                            color: C.faint,
                            textDecoration: 'none',
                        }}
                    >
                        Karyawan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{emp.full_name}</span>
                </div>

                {/* Profile header card */}
                <div style={{ ...card, overflow: 'hidden', marginBottom: 18 }}>
                    <div
                        style={{
                            padding: '22px 26px',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 18,
                            flexWrap: 'wrap',
                        }}
                    >
                        {emp.photo_url ? (
                            <img
                                src={emp.photo_url}
                                alt={emp.full_name}
                                style={{
                                    width: 84,
                                    height: 84,
                                    borderRadius: 20,
                                    objectFit: 'cover',
                                    flex: 'none',
                                }}
                            />
                        ) : (
                            <div
                                style={{
                                    width: 84,
                                    height: 84,
                                    borderRadius: 20,
                                    background: emp.avatar_color,
                                    color: '#fff',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 30,
                                    fontWeight: 600,
                                    flex: 'none',
                                }}
                            >
                                {emp.initials}
                            </div>
                        )}
                        <div style={{ flex: 1, minWidth: 200 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    flexWrap: 'wrap',
                                }}
                            >
                                <h1
                                    style={{
                                        fontSize: 21,
                                        fontWeight: 600,
                                        color: C.navy,
                                        margin: 0,
                                    }}
                                >
                                    {emp.full_name}
                                </h1>
                                <span
                                    style={{
                                        display: 'inline-block',
                                        padding: '3px 10px',
                                        borderRadius: 100,
                                        fontSize: 11.5,
                                        fontWeight: 600,
                                        color: badge.color,
                                        background: badge.bg,
                                    }}
                                >
                                    {badge.label}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    color: C.muted,
                                    marginTop: 3,
                                }}
                            >
                                {dash(emp.position?.name)} ·{' '}
                                {dash(emp.department?.name)}
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 10 }}>
                            <a
                                href={`/avana/payroll/1721/${emp.id}?year=${new Date().getFullYear()}`}
                                style={{ ...btnOut, textDecoration: 'none' }}
                                title="Unduh bukti potong PPh 21 tahunan"
                            >
                                <AIcon name="file-text" size={16} />
                                Bukti Potong 1721-A1
                            </a>
                            <Link
                                href={EmployeeController.edit(emp.route_key)}
                                style={{ ...btnP, textDecoration: 'none' }}
                            >
                                <AIcon name="pencil" size={16} />
                                Ubah Data
                            </Link>
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            gap: 0,
                            padding: '0 26px',
                            borderTop: `1px solid ${C.line}`,
                            flexWrap: 'wrap',
                        }}
                    >
                        <div
                            style={{
                                padding: '14px 26px 14px 0',
                                borderRight: `1px solid ${C.line}`,
                                paddingRight: 26,
                            }}
                        >
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                ID Karyawan
                            </div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.text,
                                    marginTop: 2,
                                }}
                            >
                                {emp.employee_number}
                            </div>
                        </div>
                        <div style={{ padding: '14px 26px' }}>
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                Email
                            </div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.text,
                                    marginTop: 2,
                                }}
                            >
                                {dash(emp.email)}
                            </div>
                        </div>
                        <div
                            style={{
                                padding: '14px 26px',
                                borderLeft: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                Cabang
                            </div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.text,
                                    marginTop: 2,
                                }}
                            >
                                {dash(emp.branch?.name)}
                            </div>
                        </div>
                        <div
                            style={{
                                padding: '14px 26px',
                                borderLeft: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                Tgl Masuk
                            </div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.text,
                                    marginTop: 2,
                                }}
                            >
                                {dash(emp.join_date)}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Tab bar */}
                <div
                    style={{
                        borderBottom: `1px solid ${C.border}`,
                        marginBottom: 20,
                        display: 'flex',
                        overflowX: 'auto',
                    }}
                >
                    {empTabs.map((t) => {
                        const active = activeTab === t.id;

                        return (
                            <button
                                key={t.id}
                                onClick={() => setActiveTab(t.id)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '12px 4px',
                                    marginRight: 26,
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    color: active ? C.primary : C.muted,
                                    border: 'none',
                                    cursor: 'pointer',
                                    background: active
                                        ? 'rgba(47,84,201,.07)'
                                        : C.surface,
                                    borderRadius: 8,
                                    whiteSpace: 'nowrap',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name={t.icon} size={15} />
                                {t.label}
                            </button>
                        );
                    })}
                </div>

                {/* Data Pribadi */}
                {activeTab === 'pribadi' && (
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
                                Data Pribadi
                            </div>
                        </div>
                        <div
                            className="avn-2col"
                            style={{
                                padding: '8px 22px 18px',
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 0,
                            }}
                        >
                            <Cell label="NIK (KTP)" value={dash(emp.nik)} />
                            <Cell
                                label="Tempat, Tgl Lahir"
                                value={dash(birthInfo)}
                                indent
                            />
                            <Cell
                                label="Jenis Kelamin"
                                value={
                                    emp.gender
                                        ? (GENDER_LABELS[emp.gender] ??
                                          emp.gender)
                                        : '—'
                                }
                            />
                            <Cell
                                label="Agama"
                                value={dash(emp.religion)}
                                indent
                            />
                            <Cell label="No. Telepon" value={dash(emp.phone)} />
                            <Cell
                                label="Status Pernikahan"
                                value={dash(emp.marital_status)}
                                indent
                            />
                            <Cell
                                label="Alamat Domisili"
                                value={dash(emp.address)}
                                full
                                last
                            />
                        </div>
                    </div>
                )}

                {/* Kepegawaian */}
                {activeTab === 'pegawai' && (
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
                                Informasi Kepegawaian
                            </div>
                        </div>
                        <div
                            className="avn-2col"
                            style={{
                                padding: '8px 22px 18px',
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 0,
                            }}
                        >
                            <Cell
                                label="Status Kepegawaian"
                                value={emp.employment_label}
                            />
                            <Cell
                                label="Departemen"
                                value={dash(emp.department?.name)}
                                indent
                            />
                            <Cell
                                label="Jabatan"
                                value={dash(emp.position?.name)}
                            />
                            <Cell
                                label="Jenjang Jabatan"
                                value={dash(emp.job_level?.name)}
                                indent
                            />
                            <Cell
                                label="Cabang"
                                value={dash(emp.branch?.name)}
                            />
                            <Cell
                                label="Lokasi Kerja (Absensi)"
                                value={
                                    emp.work_location
                                        ? `${emp.work_location.name ?? '—'}${emp.work_location.radius_meter ? ` · radius ${emp.work_location.radius_meter} m` : ''}`
                                        : 'Otomatis (ikut cabang)'
                                }
                                indent
                            />
                            <Cell
                                label="Kebijakan Absensi"
                                value={
                                    ATTENDANCE_SCOPE_LABELS[
                                        emp.attendance_scope ?? ''
                                    ] ?? 'Ikut kebijakan perusahaan'
                                }
                                indent
                            />
                            <Cell
                                label="Atasan Langsung"
                                value={dash(emp.manager?.name)}
                            />
                            <Cell
                                label="Tgl Bergabung"
                                value={dash(emp.join_date)}
                                indent
                            />
                            {/* Yang dipakai payroll: master gaji yang menempel
                                dan nomor kepesertaan BPJS. */}
                            <Cell
                                label="Master Gaji"
                                value={
                                    emp.salary_master
                                        ? `${emp.salary_master.code}${emp.salary_master.category ? ` · ${emp.salary_master.category}` : ''}`
                                        : 'Belum ditempel'
                                }
                            />
                            <Cell
                                label="Kontrak Aktif"
                                value={
                                    (emp.contracts ?? []).find(
                                        (c) => c.status === 'active',
                                    )?.contract_number ?? 'Belum ada'
                                }
                                indent
                            />
                            <Cell
                                label="No. BPJS Ketenagakerjaan"
                                value={dash(emp.bpjs_ketenagakerjaan_number)}
                                last
                            />
                            <Cell
                                label="No. BPJS Kesehatan"
                                value={dash(emp.bpjs_kesehatan_number)}
                                indent
                            />
                            <Cell
                                label="Status PTKP"
                                value={emp.ptkp_status ?? 'Belum ditentukan'}
                                last
                            />
                        </div>
                    </div>
                )}

                {/* Kontrak — riwayat kontrak kerja karyawan ini. */}
                {activeTab === 'kontrak' && (
                    <KontrakTab
                        contracts={emp.contracts ?? []}
                        employeeRouteKey={emp.route_key}
                    />
                )}

                {/* Dokumen — berkas milik karyawan ini. */}
                {activeTab === 'dokumen' && (
                    <DokumenTab documents={emp.documents ?? []} />
                )}

                {/* Cuti — riwayat pengajuan cuti karyawan ini. */}
                {activeTab === 'cuti' && (
                    <CutiTab rows={emp.leave_history ?? []} />
                )}

                {/* Payroll — slip gaji karyawan ini per periode. */}
                {activeTab === 'payrolltab' && (
                    <PayrollTab rows={emp.payroll_history ?? []} />
                )}

                {/* Aset — aset perusahaan yang sedang dipegang karyawan. */}
                {activeTab === 'aset' && (
                    <AssetTab assets={emp.held_assets ?? []} />
                )}
            </div>
        </>
    );
}

/**
 * Centered placeholder shown when a tab has no rows to list. The icon needs
 * the flex column because the global reset makes every `svg` a block element,
 * which `text-align` cannot center.
 */
function EmptyTab({ icon, message }: { icon: string; message: string }) {
    return (
        <div
            style={{
                ...card,
                padding: '40px 22px',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: 10,
                textAlign: 'center',
                color: C.faint,
                fontSize: 13.5,
            }}
        >
            <AIcon name={icon} size={26} color={C.faint} />
            <div>{message}</div>
        </div>
    );
}

/** A rounded status pill tinted by the semantic color passed in. */
function Pill({ label, color }: { label: string; color: string }) {
    return (
        <span
            style={{
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color,
                background: `${color}1A`,
            }}
        >
            {label}
        </span>
    );
}

/** Map a request/run status onto its semantic pill color. */
function statusColor(status: string): string {
    if (['approved', 'paid', 'locked', 'published'].includes(status)) {
        return C.green;
    }

    if (['rejected', 'cancelled'].includes(status)) {
        return C.red;
    }

    return C.amber;
}

/** Pick the file icon and tint from the document's extension. */
function documentIcon(document: EmployeeDocumentRow): {
    icon: string;
    color: string;
} {
    const extension = (
        document.extension ??
        document.name.split('.').pop() ??
        ''
    ).toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
        return { icon: 'image', color: C.primary };
    }

    if (extension === 'pdf') {
        return { icon: 'file-text', color: C.red };
    }

    return { icon: 'file-text', color: C.green };
}

/** Documents uploaded for this employee. */
function DokumenTab({ documents }: { documents: EmployeeDocumentRow[] }) {
    if (documents.length === 0) {
        return (
            <EmptyTab
                icon="folder"
                message="Belum ada dokumen untuk karyawan ini."
            />
        );
    }

    return (
        <div style={{ ...card, padding: '14px 22px' }}>
            {documents.map((document, index) => {
                const { icon, color } = documentIcon(document);
                const meta = [document.size_label, document.uploaded_at]
                    .filter(Boolean)
                    .join(' · diunggah ');

                return (
                    <div
                        key={document.id}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            padding: '14px 0',
                            borderBottom:
                                index === documents.length - 1
                                    ? undefined
                                    : '1px solid #F5F7FB',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <div
                                style={{
                                    width: 38,
                                    height: 38,
                                    borderRadius: 9,
                                    background: `${color}1A`,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name={icon} size={18} color={color} />
                            </div>
                            <div>
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        fontWeight: 500,
                                        color: C.text,
                                    }}
                                >
                                    {document.name}
                                </div>
                                <div style={{ fontSize: 12, color: C.faint }}>
                                    {meta !== '' ? meta : dash(document.type)}
                                </div>
                            </div>
                        </div>
                        {document.download_url !== null && (
                            <a
                                href={document.download_url}
                                target="_blank"
                                rel="noreferrer"
                                style={{ textDecoration: 'none' }}
                            >
                                <ActionBtn
                                    icon="download"
                                    label="Unduh"
                                    variant="neutral"
                                />
                            </a>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/** Pill color for a contract status, amber while it is about to run out. */
function contractStatusColor(row: EmployeeContractRow): string {
    if (row.status === 'active') {
        return row.expiring_soon ? C.amber : C.green;
    }

    return row.status === 'terminated' ? C.red : C.muted;
}

/**
 * How long a contract still runs, in the words HR reads it: an active contract
 * counts down, an open-ended one says so, and a lapsed one is called lapsed
 * rather than shown as a negative number of days.
 */
function contractRemaining(row: EmployeeContractRow): string {
    if (row.end_date === null) {
        return 'Tanpa batas waktu';
    }

    if (row.days_to_expiry === null) {
        return '—';
    }

    if (row.days_to_expiry < 0) {
        return `Lewat ${Math.abs(row.days_to_expiry)} hari`;
    }

    return `${row.days_to_expiry} hari lagi`;
}

/**
 * The employee's contract history. A contract belongs to a person, so it is
 * read here rather than by hunting for their name in the tenant-wide Kontrak
 * list; the rows still open the same Kontrak form, which returns to this page.
 */
function KontrakTab({
    contracts,
    employeeRouteKey,
}: {
    contracts: EmployeeContractRow[];
    employeeRouteKey: string;
}) {
    const addHref = `${ContractController.create().url}?employee=${employeeRouteKey}`;

    if (contracts.length === 0) {
        return (
            <div style={{ display: 'grid', gap: 12, justifyItems: 'stretch' }}>
                <EmptyTab
                    icon="file-text"
                    message="Belum ada kontrak kerja untuk karyawan ini."
                />
                <div style={{ display: 'flex', justifyContent: 'center' }}>
                    <Link href={addHref} style={{ textDecoration: 'none' }}>
                        <ActionBtn
                            icon="plus"
                            label="Tambah Kontrak"
                            variant="primary"
                        />
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '14px 18px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div style={{ fontSize: 13.5, fontWeight: 600, color: C.text }}>
                    Riwayat Kontrak ({contracts.length})
                </div>
                <Link href={addHref} style={{ textDecoration: 'none' }}>
                    <ActionBtn
                        icon="plus"
                        label="Tambah Kontrak"
                        variant="primary"
                    />
                </Link>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={thCell}>No. Kontrak</th>
                            <th style={{ ...thCell, padding: '12px 16px' }}>
                                Jenis
                            </th>
                            <th style={{ ...thCell, padding: '12px 16px' }}>
                                Periode
                            </th>
                            <th style={{ ...thCell, padding: '12px 16px' }}>
                                Sisa
                            </th>
                            <th style={{ ...thCell, padding: '12px 16px' }}>
                                Dokumen
                            </th>
                            <th style={thCell}>Status</th>
                            <th style={{ ...thCell, textAlign: 'right' }}>
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {contracts.map((row) => (
                            <tr
                                key={row.id}
                                style={{ borderTop: `1px solid ${C.line}` }}
                            >
                                <td
                                    style={{
                                        padding: '13px 18px',
                                        fontSize: 13,
                                        color: C.text,
                                    }}
                                >
                                    {dash(row.contract_number)}
                                </td>
                                <td
                                    style={{
                                        padding: '13px 16px',
                                        fontSize: 13,
                                        color: C.muted,
                                    }}
                                >
                                    {row.contract_type_label}
                                </td>
                                <td
                                    style={{
                                        padding: '13px 16px',
                                        fontSize: 13,
                                        color: C.muted,
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {dash(row.start_date)} –{' '}
                                    {row.end_date ?? 'tanpa batas'}
                                </td>
                                <td
                                    style={{
                                        padding: '13px 16px',
                                        fontSize: 13,
                                        color: row.expiring_soon
                                            ? C.amber
                                            : C.muted,
                                        fontWeight: row.expiring_soon
                                            ? 600
                                            : 400,
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {contractRemaining(row)}
                                </td>
                                <td style={{ padding: '13px 16px' }}>
                                    {row.document ? (
                                        <a
                                            href={row.document.href}
                                            target="_blank"
                                            rel="noreferrer"
                                            style={{ textDecoration: 'none' }}
                                        >
                                            <ActionBtn
                                                icon="download"
                                                label="Unduh"
                                                variant="neutral"
                                            />
                                        </a>
                                    ) : (
                                        <span
                                            style={{
                                                fontSize: 12.5,
                                                color: C.faint,
                                            }}
                                        >
                                            Belum diunggah
                                        </span>
                                    )}
                                </td>
                                <td style={{ padding: '13px 18px' }}>
                                    <Pill
                                        label={row.status_label}
                                        color={contractStatusColor(row)}
                                    />
                                </td>
                                <td
                                    style={{
                                        padding: '13px 18px',
                                        textAlign: 'right',
                                    }}
                                >
                                    <Link
                                        href={`${ContractController.edit(row.route_key).url}?employee=${employeeRouteKey}`}
                                        style={{ textDecoration: 'none' }}
                                    >
                                        <ActionBtn
                                            icon="pencil"
                                            label="Ubah"
                                            variant="primary"
                                        />
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** Leave requests filed by this employee, newest first. */
function CutiTab({ rows }: { rows: LeaveHistoryRow[] }) {
    if (rows.length === 0) {
        return (
            <EmptyTab
                icon="palmtree"
                message="Belum ada pengajuan cuti dari karyawan ini."
            />
        );
    }

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                    <tr style={{ background: '#FAFBFD' }}>
                        <th style={thCell}>Jenis</th>
                        <th style={{ ...thCell, padding: '12px 16px' }}>
                            Tanggal
                        </th>
                        <th style={{ ...thCell, padding: '12px 16px' }}>
                            Durasi
                        </th>
                        <th style={thCell}>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            style={{ borderTop: `1px solid ${C.line}` }}
                        >
                            <td
                                style={{
                                    padding: '13px 18px',
                                    fontSize: 13,
                                    color: C.text,
                                }}
                            >
                                {row.type}
                            </td>
                            <td
                                style={{
                                    padding: '13px 16px',
                                    fontSize: 13,
                                    color: C.muted,
                                }}
                            >
                                {row.date_label}
                            </td>
                            <td
                                style={{
                                    padding: '13px 16px',
                                    fontSize: 13,
                                    color: C.muted,
                                }}
                            >
                                {row.duration_label}
                            </td>
                            <td style={{ padding: '13px 18px' }}>
                                <Pill
                                    label={row.status_label}
                                    color={statusColor(row.status)}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Payslip lines produced for this employee, newest run first. */
function PayrollTab({ rows }: { rows: PayrollHistoryRow[] }) {
    if (rows.length === 0) {
        return (
            <EmptyTab
                icon="wallet"
                message="Belum ada slip gaji untuk karyawan ini."
            />
        );
    }

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                    <tr style={{ background: '#FAFBFD' }}>
                        <th style={thCell}>Periode</th>
                        <th
                            style={{
                                ...thCell,
                                padding: '12px 16px',
                                textAlign: 'right',
                            }}
                        >
                            Gaji Bersih
                        </th>
                        <th style={thCell}>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            style={{ borderTop: `1px solid ${C.line}` }}
                        >
                            <td
                                style={{
                                    padding: '13px 18px',
                                    fontSize: 13,
                                    color: C.text,
                                }}
                            >
                                {row.period}
                            </td>
                            <td
                                style={{
                                    padding: '13px 16px',
                                    fontSize: 13,
                                    color: C.text,
                                    textAlign: 'right',
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {row.net_salary_label}
                            </td>
                            <td style={{ padding: '13px 18px' }}>
                                <Pill
                                    label={row.status_label}
                                    color={statusColor(row.status)}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Real active asset assignments held by the employee. */
function AssetTab({ assets }: { assets: HeldAsset[] }) {
    if (assets.length === 0) {
        return (
            <EmptyTab
                icon="package"
                message="Belum ada aset yang dipegang karyawan ini."
            />
        );
    }

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                    <tr style={{ background: '#FAFBFD' }}>
                        <th style={thCell}>Kode</th>
                        <th style={{ ...thCell, padding: '12px 16px' }}>
                            Aset
                        </th>
                        <th style={{ ...thCell, padding: '12px 16px' }}>
                            Kategori
                        </th>
                        <th style={{ ...thCell, padding: '12px 16px' }}>
                            Kondisi
                        </th>
                        <th style={thCell}>Tgl Ditugaskan</th>
                    </tr>
                </thead>
                <tbody>
                    {assets.map((held) => (
                        <tr
                            key={held.id}
                            style={{ borderTop: `1px solid ${C.line}` }}
                        >
                            <td
                                style={{
                                    padding: '13px 18px',
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.text,
                                }}
                            >
                                {held.asset ? (
                                    <Link
                                        href={`/avana/aset/${held.asset.id}`}
                                        style={{
                                            color: C.primary,
                                            textDecoration: 'none',
                                        }}
                                    >
                                        {held.asset.code}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </td>
                            <td
                                style={{
                                    padding: '13px 16px',
                                    fontSize: 13,
                                    color: C.text,
                                }}
                            >
                                {dash(held.asset?.name)}
                            </td>
                            <td
                                style={{
                                    padding: '13px 16px',
                                    fontSize: 13,
                                    color: C.muted,
                                }}
                            >
                                {dash(held.asset?.category)}
                            </td>
                            <td
                                style={{
                                    padding: '13px 16px',
                                    fontSize: 13,
                                    color: C.muted,
                                }}
                            >
                                {dash(held.asset?.condition_label)}
                            </td>
                            <td
                                style={{
                                    padding: '13px 18px',
                                    fontSize: 13,
                                    color: C.muted,
                                }}
                            >
                                {dash(held.assigned_date)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
