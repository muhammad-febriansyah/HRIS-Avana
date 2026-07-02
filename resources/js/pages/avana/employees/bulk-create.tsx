import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import type { EmployeeFormOptions, FlashProps } from './types';

interface BulkRow {
    full_name: string;
    email: string;
    password: string;
}

interface CommonFields {
    branch_id: string;
    work_location_id: string;
    department_id: string;
    position_id: string;
    employment_status: string;
    status: string;
}

const emptyRow = (): BulkRow => ({ full_name: '', email: '', password: '' });

const cell: CSSProperties = {
    height: 40,
    padding: '0 11px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
    width: '100%',
};

const label: CSSProperties = {
    display: 'block',
    fontSize: 12.5,
    fontWeight: 500,
    color: C.text,
    marginBottom: 6,
};

const th: CSSProperties = {
    padding: '11px 12px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

export default function EmployeesBulkCreate({
    options,
}: {
    options: EmployeeFormOptions;
}) {
    const { flash } = usePage<FlashProps>().props;
    const form = useForm<{ employees: BulkRow[] }>({
        employees: [emptyRow(), emptyRow(), emptyRow()],
    });
    const { data, setData, processing, errors } = form;

    const [common, setCommon] = useState<CommonFields>({
        branch_id: '',
        work_location_id: '',
        department_id: '',
        position_id: '',
        employment_status: 'permanent',
        status: 'active',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const rows = data.employees;

    const updateRow = (index: number, key: keyof BulkRow, value: string) => {
        setData(
            'employees',
            rows.map((row, i) =>
                i === index ? { ...row, [key]: value } : row,
            ),
        );
    };

    const setCommonField = (key: keyof CommonFields, value: string) =>
        setCommon((prev) => ({ ...prev, [key]: value }));

    const setBranch = (value: string) =>
        setCommon((prev) => ({ ...prev, branch_id: value, work_location_id: '' }));

    const addRow = () => setData('employees', [...rows, emptyRow()]);

    const removeRow = (index: number) =>
        setData(
            'employees',
            rows.filter((_, i) => i !== index),
        );

    const isBlank = (row: BulkRow): boolean =>
        row.full_name.trim() === '' &&
        row.email.trim() === '' &&
        row.password.trim() === '';

    const submit = () => {
        const filled = rows.filter((row) => !isBlank(row));
        const source = filled.length > 0 ? filled : rows;
        // Fold the shared settings into every row before submitting.
        const employees = source.map((row) => ({ ...common, ...row }));
        form.transform(() => ({ employees }));
        form.post('/avana/employees/bulk', { preserveScroll: true });
    };

    const err = (index: number, key: string): string | undefined =>
        (errors as Record<string, string>)[`employees.${index}.${key}`];

    const availableLocations = options.workLocations.filter(
        (location) =>
            !common.branch_id ||
            String(location.branch_id ?? '') === common.branch_id,
    );

    const filledCount = rows.filter((row) => !isBlank(row)).length || rows.length;

    return (
        <>
            <Head title="Tambah Karyawan Massal" />
            <div style={{ padding: '28px 32px', maxWidth: 880 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href="/avana/employees"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Karyawan
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Tambah Massal</span>
                </div>

                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                        letterSpacing: '-.01em',
                    }}
                >
                    Tambah Karyawan Massal
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 20 }}>
                    Atur data yang sama sekali di atas, lalu isi nama tiap
                    karyawan di bawah.
                </div>

                {/* Shared settings applied to every row */}
                <div style={{ ...card, marginBottom: 18 }}>
                    <div
                        style={{
                            padding: '16px 20px',
                            borderBottom: `1px solid ${C.line}`,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 9,
                        }}
                    >
                        <AIcon name="layers" size={16} color={C.primary} />
                        <div
                            style={{
                                fontSize: 14.5,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Berlaku untuk semua
                        </div>
                        <span style={{ fontSize: 12.5, color: C.faint }}>
                            Cabang, lokasi & penempatan yang sama untuk semua
                            baris.
                        </span>
                    </div>
                    <div
                        style={{
                            padding: '18px 20px',
                            display: 'grid',
                            gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
                            gap: 14,
                        }}
                    >
                        <div>
                            <label style={label}>Cabang</label>
                            <select
                                value={common.branch_id}
                                onChange={(event) => setBranch(event.target.value)}
                                style={{ ...cell, cursor: 'pointer' }}
                            >
                                <option value="">— Pilih —</option>
                                {options.branches.map((branch) => (
                                    <option key={branch.id} value={String(branch.id)}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={label}>Lokasi Kerja</label>
                            <select
                                value={common.work_location_id}
                                onChange={(event) =>
                                    setCommonField('work_location_id', event.target.value)
                                }
                                style={{ ...cell, cursor: 'pointer' }}
                            >
                                <option value="">Otomatis (ikut cabang)</option>
                                {availableLocations.map((location) => (
                                    <option key={location.id} value={String(location.id)}>
                                        {location.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={label}>Departemen</label>
                            <select
                                value={common.department_id}
                                onChange={(event) =>
                                    setCommonField('department_id', event.target.value)
                                }
                                style={{ ...cell, cursor: 'pointer' }}
                            >
                                <option value="">— Pilih —</option>
                                {options.departments.map((department) => (
                                    <option key={department.id} value={String(department.id)}>
                                        {department.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={label}>Jabatan</label>
                            <select
                                value={common.position_id}
                                onChange={(event) =>
                                    setCommonField('position_id', event.target.value)
                                }
                                style={{ ...cell, cursor: 'pointer' }}
                            >
                                <option value="">— Pilih —</option>
                                {options.positions.map((position) => (
                                    <option key={position.id} value={String(position.id)}>
                                        {position.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={label}>Status Kepegawaian</label>
                            <select
                                value={common.employment_status}
                                onChange={(event) =>
                                    setCommonField('employment_status', event.target.value)
                                }
                                style={{ ...cell, cursor: 'pointer' }}
                            >
                                {options.employmentStatuses.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Per-employee rows */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: '#FAFBFD' }}>
                                <th style={{ ...th, width: 34 }}>#</th>
                                <th style={th}>Nama Lengkap *</th>
                                <th style={th}>Email</th>
                                <th style={th}>Password Login</th>
                                <th style={{ ...th, width: 44 }} />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={index}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            padding: '8px 12px',
                                            fontSize: 12.5,
                                            color: C.faint,
                                            textAlign: 'center',
                                        }}
                                    >
                                        {index + 1}
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <input
                                            value={row.full_name}
                                            onChange={(event) =>
                                                updateRow(index, 'full_name', event.target.value)
                                            }
                                            placeholder="Nama karyawan"
                                            style={
                                                err(index, 'full_name')
                                                    ? { ...cell, borderColor: C.red }
                                                    : cell
                                            }
                                        />
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <input
                                            type="email"
                                            value={row.email}
                                            onChange={(event) =>
                                                updateRow(index, 'email', event.target.value)
                                            }
                                            placeholder="email@perusahaan.co.id"
                                            style={
                                                err(index, 'email')
                                                    ? { ...cell, borderColor: C.red }
                                                    : cell
                                            }
                                        />
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <input
                                            type="password"
                                            autoComplete="new-password"
                                            value={row.password}
                                            onChange={(event) =>
                                                updateRow(index, 'password', event.target.value)
                                            }
                                            placeholder="opsional · buat login"
                                            style={
                                                err(index, 'password')
                                                    ? { ...cell, borderColor: C.red }
                                                    : cell
                                            }
                                        />
                                    </td>
                                    <td
                                        style={{
                                            padding: '8px 12px',
                                            textAlign: 'center',
                                        }}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => removeRow(index)}
                                            disabled={rows.length <= 1}
                                            aria-label="Hapus baris"
                                            style={{
                                                border: 'none',
                                                background: 'none',
                                                cursor:
                                                    rows.length <= 1
                                                        ? 'not-allowed'
                                                        : 'pointer',
                                                color:
                                                    rows.length <= 1
                                                        ? C.faint
                                                        : C.red,
                                                padding: 6,
                                            }}
                                        >
                                            <AIcon name="trash-2" size={16} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div
                        style={{
                            padding: '12px 16px',
                            borderTop: `1px solid ${C.border}`,
                        }}
                    >
                        <button
                            type="button"
                            onClick={addRow}
                            style={{ ...btnOut, height: 38 }}
                        >
                            <AIcon name="plus" size={16} />
                            Tambah Baris
                        </button>
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                        marginTop: 18,
                    }}
                >
                    <button
                        type="button"
                        onClick={() => router.visit('/avana/employees')}
                        style={btnOut}
                    >
                        <AIcon name="x" size={16} />
                        Batal
                    </button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={processing}
                        style={{
                            ...btnP,
                            opacity: processing ? 0.7 : 1,
                            cursor: processing ? 'not-allowed' : 'pointer',
                        }}
                    >
                        <AIcon name="save" size={16} color="#fff" />
                        Simpan {filledCount} Karyawan
                    </button>
                </div>
            </div>
        </>
    );
}
