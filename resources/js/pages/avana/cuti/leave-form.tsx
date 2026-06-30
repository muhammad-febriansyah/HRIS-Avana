import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import {
    dateInputStyle,
    FieldError,
    fieldLabelStyle,
    selectStyle,
    withError,
} from './components';
import { leaveDayCount } from './types';
import type {
    EmployeeOption,
    LeaveBalance,
    LeaveFormData,
    LeaveTypeOption,
} from './types';

interface LeaveRequestFormProps {
    form: InertiaFormProps<LeaveFormData>;
    employees: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    balances: LeaveBalance[];
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** The "Ajukan Cuti" request form card. */
export function LeaveRequestForm({
    form,
    employees,
    leaveTypes,
    balances,
    onSubmit,
}: LeaveRequestFormProps) {
    const selectedBalance = balances.find(
        (balance) => String(balance.id) === form.data.leave_type_id,
    );
    const requestedDays = leaveDayCount(
        form.data.start_date,
        form.data.end_date,
    );

    return (
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
                    Ajukan Cuti
                </div>
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        marginTop: 3,
                    }}
                >
                    Lengkapi formulir di bawah ini.
                </div>
            </div>
            <form
                onSubmit={onSubmit}
                style={{
                    padding: '20px 22px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>
                        Karyawan <span style={{ color: C.red }}>*</span>
                    </label>
                    <select
                        value={form.data.employee_id}
                        onChange={(event) =>
                            form.setData('employee_id', event.target.value)
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
                                {employee.name} ({employee.employee_number})
                            </option>
                        ))}
                    </select>
                    <FieldError message={form.errors.employee_id} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>
                        Jenis Cuti <span style={{ color: C.red }}>*</span>
                    </label>
                    <select
                        value={form.data.leave_type_id}
                        onChange={(event) =>
                            form.setData('leave_type_id', event.target.value)
                        }
                        style={withError(
                            selectStyle,
                            !!form.errors.leave_type_id,
                        )}
                    >
                        <option value="">Pilih jenis cuti</option>
                        {leaveTypes.map((leaveType) => (
                            <option
                                key={leaveType.id}
                                value={String(leaveType.id)}
                            >
                                {leaveType.name}
                            </option>
                        ))}
                    </select>
                    <FieldError message={form.errors.leave_type_id} />
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
                            Mulai <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="date"
                            value={form.data.start_date}
                            onChange={(event) =>
                                form.setData('start_date', event.target.value)
                            }
                            style={withError(
                                dateInputStyle,
                                !!form.errors.start_date,
                            )}
                        />
                        <FieldError message={form.errors.start_date} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>
                            Selesai <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="date"
                            value={form.data.end_date}
                            onChange={(event) =>
                                form.setData('end_date', event.target.value)
                            }
                            style={withError(
                                dateInputStyle,
                                !!form.errors.end_date,
                            )}
                        />
                        <FieldError message={form.errors.end_date} />
                    </div>
                </div>
                <div
                    style={{
                        background: C.surface,
                        borderRadius: 8,
                        padding: '11px 13px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 9,
                        fontSize: 12.5,
                        color: C.muted,
                    }}
                >
                    <AIcon name="info" size={15} color={C.primary} />
                    Total&nbsp;
                    <span style={{ color: C.text, fontWeight: 600 }}>
                        {requestedDays} hari kerja
                    </span>
                    {selectedBalance && (
                        <>&nbsp;· sisa saldo {selectedBalance.sisa} hari</>
                    )}
                </div>
                <div>
                    <label style={fieldLabelStyle}>
                        Alasan <span style={{ color: C.red }}>*</span>
                    </label>
                    <textarea
                        placeholder="Tuliskan alasan pengajuan cuti"
                        rows={3}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        style={withError(
                            {
                                width: '100%',
                                padding: '11px 13px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13.5,
                                outline: 'none',
                                resize: 'vertical',
                            },
                            !!form.errors.reason,
                        )}
                    />
                    <FieldError message={form.errors.reason} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>Lampiran</label>
                    <div
                        style={{
                            border: '1.5px dashed #D5DCEA',
                            borderRadius: 9,
                            padding: 18,
                            textAlign: 'center',
                            cursor: 'pointer',
                            transition: '.15s',
                        }}
                    >
                        <AIcon
                            name="upload-cloud"
                            size={22}
                            color={C.faint}
                        />
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 6,
                            }}
                        >
                            Klik untuk unggah{' '}
                            <span
                                style={{
                                    color: C.primary,
                                    fontWeight: 500,
                                }}
                            >
                                surat keterangan
                            </span>
                        </div>
                        <div
                            style={{
                                fontSize: 11,
                                color: C.faint,
                                marginTop: 2,
                            }}
                        >
                            PDF / JPG · maks 5 MB
                        </div>
                    </div>
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
                        cursor: form.processing ? 'not-allowed' : 'pointer',
                        opacity: form.processing ? 0.7 : 1,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 8,
                        transition: '.15s',
                    }}
                >
                    <AIcon name="send" size={16} color="#fff" />
                    Kirim Pengajuan
                </button>
            </form>
        </div>
    );
}
