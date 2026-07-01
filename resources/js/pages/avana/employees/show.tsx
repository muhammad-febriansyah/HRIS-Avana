import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import EmployeeController from '@/actions/App/Http/Controllers/Avana/EmployeeController';
import { AIcon, btnOut, btnP, C, card, statusBadge } from '@/lib/avana';
import type { Employee, FlashProps } from './types';

interface EmployeesShowProps {
    employee: {
        data: Employee;
    };
}

const empTabs = [
    { id: 'pribadi', label: 'Data Pribadi', icon: 'user' },
    { id: 'pegawai', label: 'Kepegawaian', icon: 'briefcase' },
    { id: 'dokumen', label: 'Dokumen', icon: 'folder' },
    { id: 'cuti', label: 'Cuti', icon: 'palmtree' },
    { id: 'payrolltab', label: 'Payroll', icon: 'wallet' },
] as const;

const GENDER_LABELS: Record<string, string> = {
    male: 'Laki-laki',
    female: 'Perempuan',
    unspecified: 'Tidak ditentukan',
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
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const birthInfo = [emp.birth_place, emp.birth_date]
        .filter((part) => part && part.trim() !== '')
        .join(', ');

    return (
        <>
            <Head title={emp.full_name} />
            <div style={{ padding: '28px 32px', maxWidth: 1000 }}>
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
                            height: 84,
                            background:
                                'linear-gradient(120deg,#0E1A3A,#2F54C9)',
                        }}
                    />
                    <div
                        style={{
                            padding: '0 26px 22px',
                            display: 'flex',
                            alignItems: 'flex-end',
                            gap: 18,
                            flexWrap: 'wrap',
                            marginTop: -38,
                        }}
                    >
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
                                border: '4px solid #fff',
                                flex: 'none',
                            }}
                        >
                            {emp.initials}
                        </div>
                        <div
                            style={{ flex: 1, minWidth: 200, paddingBottom: 2 }}
                        >
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
                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                paddingBottom: 4,
                            }}
                        >
                            <a
                                href={`/avana/payroll/1721/${emp.id}?year=${new Date().getFullYear()}`}
                                style={{ ...btnOut, textDecoration: 'none' }}
                                title="Unduh bukti potong PPh 21 tahunan"
                            >
                                <AIcon name="file-text" size={16} />
                                Bukti Potong 1721-A1
                            </a>
                            <Link
                                href={EmployeeController.edit(emp.id)}
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
                                    borderBottom: active
                                        ? `2px solid ${C.primary}`
                                        : '2px solid transparent',
                                    cursor: 'pointer',
                                    background: 'none',
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
                                label="Atasan Langsung"
                                value={dash(emp.manager?.name)}
                                indent
                            />
                            <Cell
                                label="Tgl Bergabung"
                                value={dash(emp.join_date)}
                                last
                            />
                        </div>
                    </div>
                )}

                {/* Dokumen — data belum terhubung (placeholder statis prototipe). */}
                {activeTab === 'dokumen' && (
                    <div style={{ ...card, padding: '14px 22px' }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '14px 0',
                                borderBottom: '1px solid #F5F7FB',
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
                                        background: 'rgba(220,38,38,.08)',
                                        color: C.red,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name="file-text"
                                        size={18}
                                        color={C.red}
                                    />
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 500,
                                            color: C.text,
                                        }}
                                    >
                                        Kontrak Kerja 2026.pdf
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: C.faint,
                                        }}
                                    >
                                        1,2 MB · diunggah 12 Jan 2026
                                    </div>
                                </div>
                            </div>
                            <button
                                style={{
                                    width: 34,
                                    height: 34,
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    cursor: 'pointer',
                                    color: C.muted,
                                }}
                            >
                                <AIcon
                                    name="download"
                                    size={16}
                                    color={C.muted}
                                />
                            </button>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '14px 0',
                                borderBottom: '1px solid #F5F7FB',
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
                                        background: 'rgba(47,84,201,.1)',
                                        color: C.primary,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name="image"
                                        size={18}
                                        color={C.primary}
                                    />
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 500,
                                            color: C.text,
                                        }}
                                    >
                                        Scan KTP.jpg
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: C.faint,
                                        }}
                                    >
                                        820 KB · diunggah 10 Jan 2026
                                    </div>
                                </div>
                            </div>
                            <button
                                style={{
                                    width: 34,
                                    height: 34,
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    cursor: 'pointer',
                                    color: C.muted,
                                }}
                            >
                                <AIcon
                                    name="download"
                                    size={16}
                                    color={C.muted}
                                />
                            </button>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '14px 0',
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
                                        background: 'rgba(22,163,74,.1)',
                                        color: C.green,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name="file-text"
                                        size={18}
                                        color={C.green}
                                    />
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 500,
                                            color: C.text,
                                        }}
                                    >
                                        Ijazah S1.pdf
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: C.faint,
                                        }}
                                    >
                                        2,4 MB · diunggah 10 Jan 2026
                                    </div>
                                </div>
                            </div>
                            <button
                                style={{
                                    width: 34,
                                    height: 34,
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    cursor: 'pointer',
                                    color: C.muted,
                                }}
                            >
                                <AIcon
                                    name="download"
                                    size={16}
                                    color={C.muted}
                                />
                            </button>
                        </div>
                    </div>
                )}

                {/* Cuti — data belum terhubung (placeholder statis prototipe). */}
                {activeTab === 'cuti' && (
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Jenis</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            padding: '12px 16px',
                                        }}
                                    >
                                        Tanggal
                                    </th>
                                    <th
                                        style={{
                                            ...thCell,
                                            padding: '12px 16px',
                                        }}
                                    >
                                        Durasi
                                    </th>
                                    <th style={thCell}>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                            fontSize: 13,
                                            color: C.text,
                                        }}
                                    >
                                        Cuti Tahunan
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 16px',
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        12–14 Mar 2026
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 16px',
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        3 hari
                                    </td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span
                                            style={{
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.green,
                                                background:
                                                    'rgba(22,163,74,.1)',
                                            }}
                                        >
                                            Disetujui
                                        </span>
                                    </td>
                                </tr>
                                <tr
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                            fontSize: 13,
                                            color: C.text,
                                        }}
                                    >
                                        Cuti Sakit
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 16px',
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        05 Feb 2026
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 16px',
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        1 hari
                                    </td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span
                                            style={{
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.green,
                                                background:
                                                    'rgba(22,163,74,.1)',
                                            }}
                                        >
                                            Disetujui
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Payroll — data belum terhubung (placeholder statis prototipe). */}
                {activeTab === 'payrolltab' && (
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                            }}
                        >
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
                                <tr
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                            fontSize: 13,
                                            color: C.text,
                                        }}
                                    >
                                        Mei 2026
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
                                        Rp 11.323.000
                                    </td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span
                                            style={{
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.green,
                                                background:
                                                    'rgba(22,163,74,.1)',
                                            }}
                                        >
                                            Dibayar
                                        </span>
                                    </td>
                                </tr>
                                <tr
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                            fontSize: 13,
                                            color: C.text,
                                        }}
                                    >
                                        April 2026
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
                                        Rp 11.323.000
                                    </td>
                                    <td style={{ padding: '13px 18px' }}>
                                        <span
                                            style={{
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.green,
                                                background:
                                                    'rgba(22,163,74,.1)',
                                            }}
                                        >
                                            Dibayar
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
