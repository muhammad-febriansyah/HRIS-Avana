import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AttendanceController from '@/actions/App/Http/Controllers/Avana/AttendanceController';
import { C } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    ModalActions,
    ModalShell,
    selectStyle,
    textareaStyle,
    withError,
} from '../timesheet/components';
import type { Attendance } from './types';

type CorrectionStatus = 'hadir' | 'leave' | 'absent';

interface CorrectionFormData {
    employee_id: string;
    date: string;
    status: CorrectionStatus;
    clock_in: string;
    clock_out: string;
    reason: string;
}

interface CorrectionModalProps {
    row: Attendance;
    /** Today on the tenant's clock (Y-m-d). */
    today: string;
    onClose: () => void;
}

const STATUS_OPTIONS: { value: CorrectionStatus; label: string }[] = [
    { value: 'hadir', label: 'Hadir (isi jam masuk)' },
    { value: 'leave', label: 'Cuti / Izin' },
    { value: 'absent', label: 'Alpa' },
];

/**
 * A row's current verdict as the form's starting status. A no-show on a past
 * day most often turns out to be Alpa, so that is offered first; today the
 * day is still open and a forgotten punch is the likelier story.
 */
function initialStatus(row: Attendance, today: string): CorrectionStatus {
    if (row.status === 'leave') {
        return 'leave';
    }

    if (row.status === 'absent') {
        return 'absent';
    }

    if (row.status === 'not_in' && (row.date_raw ?? today) < today) {
        return 'absent';
    }

    return 'hadir';
}

/** "—" is the table's empty cell, not a time. */
function timeOrEmpty(value: string): string {
    return /^\d{2}:\d{2}$/.test(value) ? value : '';
}

/**
 * HR's direct correction of one employee's day. Setting Hadir takes clock
 * times and lets the shift decide Hadir vs Terlambat; Cuti/Izin and Alpa set
 * the verdict outright. Every save is logged with its reason.
 */
export function CorrectionModal({ row, today, onClose }: CorrectionModalProps) {
    const form = useForm<CorrectionFormData>({
        employee_id: String(row.employee?.id ?? ''),
        date: row.date_raw ?? '',
        status: initialStatus(row, today),
        clock_in: timeOrEmpty(row.clock_in),
        clock_out: timeOrEmpty(row.clock_out),
        reason: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(AttendanceController.correct(), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const isPresent = form.data.status === 'hadir';

    return (
        <ModalShell
            title="Koreksi Absensi"
            subtitle={`${row.employee?.name ?? 'Karyawan'} · ${row.date}`}
            onClose={onClose}
            onSubmit={submit}
        >
            <div>
                <label style={fieldLabelStyle}>
                    Status <span style={{ color: C.red }}>*</span>
                </label>
                <select
                    value={form.data.status}
                    onChange={(event) =>
                        form.setData(
                            'status',
                            event.target.value as CorrectionStatus,
                        )
                    }
                    style={withError(selectStyle, !!form.errors.status)}
                >
                    {STATUS_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <FieldError message={form.errors.status} />
            </div>

            {isPresent && (
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Jam masuk <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="time"
                            value={form.data.clock_in}
                            onChange={(event) =>
                                form.setData('clock_in', event.target.value)
                            }
                            style={withError(
                                inputStyle,
                                !!form.errors.clock_in,
                            )}
                        />
                        <FieldError message={form.errors.clock_in} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Jam pulang</label>
                        <input
                            type="time"
                            value={form.data.clock_out}
                            onChange={(event) =>
                                form.setData('clock_out', event.target.value)
                            }
                            style={withError(
                                inputStyle,
                                !!form.errors.clock_out,
                            )}
                        />
                        <FieldError message={form.errors.clock_out} />
                    </div>
                </div>
            )}

            <div>
                <label style={fieldLabelStyle}>
                    Alasan <span style={{ color: C.red }}>*</span>
                </label>
                <textarea
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    rows={3}
                    placeholder="Contoh: lupa absen, sudah dikonfirmasi atasan"
                    style={withError(textareaStyle, !!form.errors.reason)}
                />
                <FieldError message={form.errors.reason} />
            </div>

            <div style={{ fontSize: 12, color: C.muted, lineHeight: 1.5 }}>
                {isPresent
                    ? 'Status Hadir/Terlambat dihitung dari shift hari itu. '
                    : ''}
                Koreksi tercatat di riwayat koreksi karyawan dan log aktivitas.
                Hitung ulang payroll bila periodenya sudah dihitung.
            </div>
            {(form.errors.employee_id || form.errors.date) && (
                <FieldError
                    message={form.errors.employee_id ?? form.errors.date}
                />
            )}

            <ModalActions
                processing={form.processing}
                onCancel={onClose}
                submitLabel="Simpan Koreksi"
            />
        </ModalShell>
    );
}
