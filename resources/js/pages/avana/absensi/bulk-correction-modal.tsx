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
import type { AbsensiFilters } from './types';

type CorrectionStatus = 'hadir' | 'leave' | 'absent';

/** What the bulk correction applies to: ticked rows, or every filtered row. */
export type BulkTarget =
    | { kind: 'items'; items: { employee_id: number; date: string }[] }
    | { kind: 'filter'; total: number; filters: AbsensiFilters };

interface BulkCorrectionModalProps {
    target: BulkTarget;
    onClose: () => void;
    onDone: () => void;
}

const STATUS_OPTIONS: { value: CorrectionStatus; label: string }[] = [
    { value: 'absent', label: 'Alpa' },
    { value: 'leave', label: 'Cuti / Izin' },
    { value: 'hadir', label: 'Hadir (isi jam masuk)' },
];

/**
 * One correction applied to many rekap rows at once — the rows HR ticked, or
 * every row the current filters show. Same rules as the single correction:
 * Hadir takes clock times and lets each day's shift decide Hadir vs
 * Terlambat; every row is logged with the shared reason. All or nothing.
 */
export function BulkCorrectionModal({
    target,
    onClose,
    onDone,
}: BulkCorrectionModalProps) {
    const form = useForm({
        status: 'absent' as CorrectionStatus,
        clock_in: '',
        clock_out: '',
        reason: '',
    });

    const count = target.kind === 'items' ? target.items.length : target.total;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            ...(target.kind === 'items'
                ? { items: target.items }
                : {
                      select_all: true,
                      filters: {
                          date_from: target.filters.date_from,
                          date_to: target.filters.date_to,
                          search: target.filters.search || null,
                          status: target.filters.status || null,
                          branch_id: target.filters.branch_id || null,
                      },
                  }),
        }));

        form.submit(AttendanceController.correctMany(), {
            preserveScroll: true,
            onSuccess: () => {
                onDone();
                onClose();
            },
        });
    };

    const isPresent = form.data.status === 'hadir';
    const errors = form.errors as Record<string, string | undefined>;
    const selectionError =
        errors.items ??
        Object.entries(errors).find(([key]) => key.startsWith('items.'))?.[1];

    return (
        <ModalShell
            title="Koreksi Absensi Massal"
            subtitle={`${count.toLocaleString('id-ID')} baris ${
                target.kind === 'filter' ? 'sesuai filter' : 'terpilih'
            }`}
            onClose={onClose}
            onSubmit={submit}
        >
            <div>
                <label style={fieldLabelStyle}>
                    Ubah status menjadi <span style={{ color: C.red }}>*</span>
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
                    placeholder="Contoh: tidak masuk tanpa keterangan, dikonfirmasi atasan"
                    style={withError(textareaStyle, !!form.errors.reason)}
                />
                <FieldError message={form.errors.reason} />
            </div>

            <div style={{ fontSize: 12, color: C.muted, lineHeight: 1.5 }}>
                Status dan alasan yang sama berlaku untuk semua baris.
                {isPresent
                    ? ' Hadir/Terlambat tetap dihitung dari shift masing-masing hari.'
                    : ''}{' '}
                Setiap baris tercatat di riwayat koreksi karyawan dan log
                aktivitas. Hitung ulang payroll bila periodenya sudah dihitung.
            </div>
            {selectionError && <FieldError message={selectionError} />}

            <ModalActions
                processing={form.processing}
                onCancel={onClose}
                submitLabel={`Koreksi ${count.toLocaleString('id-ID')} Baris`}
            />
        </ModalShell>
    );
}
