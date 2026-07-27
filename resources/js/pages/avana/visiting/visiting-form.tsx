import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    dateInputStyle,
    EmployeeChip,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    TaskProgressBar,
    textareaStyle,
    withError,
} from './components';
import type { BranchOption, EmployeeOption, VisitFormData } from './types';

interface VisitingFormProps {
    form: InertiaFormProps<VisitFormData>;
    employees: EmployeeOption[];
    branches: BranchOption[];
    submitLabel: string;
    submitIcon: string;
    /** Saves the report as a draft to be finished later. */
    onSaveDraft?: () => void;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Create form for a field visit: attendees and tasklist evidence. */
export function VisitingForm({
    form,
    employees,
    branches,
    submitLabel,
    submitIcon,
    onSaveDraft,
    cancelHref,
    onSubmit,
}: VisitingFormProps) {
    const { data, setData, errors, processing } = form;

    const [taskDraft, setTaskDraft] = useState('');
    const [locating, setLocating] = useState(false);
    const [locationError, setLocationError] = useState<string | null>(null);

    const addEmployee = (value: string) => {
        if (value && !data.employee_ids.includes(value)) {
            setData('employee_ids', [...data.employee_ids, value]);
        }
    };

    const removeEmployee = (value: string) => {
        setData(
            'employee_ids',
            data.employee_ids.filter((id) => id !== value),
        );
    };

    const addTask = () => {
        const title = taskDraft.trim();

        if (title !== '') {
            setData('tasks', [...data.tasks, title]);
            setTaskDraft('');
        }
    };

    const removeTask = (index: number) => {
        setData(
            'tasks',
            data.tasks.filter((_, i) => i !== index),
        );
    };

    const fillPreciseLocation = () => {
        if (!navigator.geolocation) {
            setLocationError('Browser ini tidak mendukung lokasi presisi.');

            return;
        }

        setLocating(true);
        setLocationError(null);

        navigator.geolocation.getCurrentPosition(
            (position) => {
                setData((current) => ({
                    ...current,
                    latitude: position.coords.latitude.toFixed(7),
                    longitude: position.coords.longitude.toFixed(7),
                }));
                setLocating(false);
            },
            () => {
                setLocationError(
                    'Gagal mengambil lokasi. Izinkan akses lokasi lalu coba lagi.',
                );
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    };

    const employeeLabel = (id: string): string => {
        const employee = employees.find((row) => String(row.id) === id);

        return employee ? employee.name : id;
    };

    // The picker only offers people not already on the list.
    const availableEmployees = employees.filter(
        (employee) => !data.employee_ids.includes(String(employee.id)),
    );

    return (
        <form onSubmit={onSubmit} style={{ ...card }}>
            <div
                style={{
                    padding: '22px 24px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Karyawan <span style={{ color: C.red }}>*</span>
                        </label>
                        <SearchableSelect
                            value=""
                            onChange={addEmployee}
                            options={availableEmployees.map((employee) => ({
                                value: String(employee.id),
                                label: `${employee.name} (${employee.employee_number})`,
                            }))}
                            placeholder="Pilih satu atau lebih karyawan"
                            searchPlaceholder="Cari nama karyawan…"
                            style={withError(
                                selectStyle,
                                !!errors.employee_ids,
                            )}
                        />
                        {data.employee_ids.length > 0 && (
                            <div
                                style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 6,
                                    marginTop: 8,
                                }}
                            >
                                {data.employee_ids.map((id) => (
                                    <EmployeeChip
                                        key={id}
                                        label={employeeLabel(id)}
                                        onRemove={() => removeEmployee(id)}
                                    />
                                ))}
                            </div>
                        )}
                        <div
                            style={{
                                fontSize: 11,
                                color: C.faint,
                                marginTop: 6,
                            }}
                        >
                            Pilih berulang untuk menambah lebih dari satu
                            karyawan.
                        </div>
                        <FieldError message={errors.employee_ids} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Nama Cabang</label>
                        <SearchableSelect
                            value={data.branch_id}
                            onChange={(value) => setData('branch_id', value)}
                            options={branches.map((branch) => ({
                                value: String(branch.id),
                                label: branch.name,
                            }))}
                            placeholder="Pilih cabang"
                            searchPlaceholder="Cari cabang…"
                            allowClear
                            style={withError(selectStyle, !!errors.branch_id)}
                        />
                        <FieldError message={errors.branch_id} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Tanggal Kunjungan{' '}
                        <span style={{ color: C.red }}>*</span>
                    </label>
                    <DatePicker
                        value={data.visit_date}
                        onChange={(nextValue) =>
                            setData('visit_date', nextValue)
                        }
                        placeholder="Pilih tanggal"
                        hasError={!!errors.visit_date}
                        width="100%"
                    />
                    <FieldError message={errors.visit_date} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Lokasi <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        placeholder="Alamat / area kunjungan"
                        value={data.location}
                        onChange={(event) =>
                            setData('location', event.target.value)
                        }
                        style={withError(inputStyle, !!errors.location)}
                    />
                    <FieldError message={errors.location} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Klien</label>
                    <input
                        type="text"
                        placeholder="Nama klien / perusahaan"
                        value={data.client_name}
                        onChange={(event) =>
                            setData('client_name', event.target.value)
                        }
                        style={withError(inputStyle, !!errors.client_name)}
                    />
                    <FieldError message={errors.client_name} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Tujuan</label>
                    <textarea
                        rows={2}
                        placeholder="Tujuan kunjungan"
                        value={data.purpose}
                        onChange={(event) =>
                            setData('purpose', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.purpose)}
                    />
                    <FieldError message={errors.purpose} />
                </div>

                {/* Tasklist */}
                <div
                    style={{
                        border: `1px solid ${C.border}`,
                        borderRadius: 10,
                        padding: 16,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 12,
                        background: '#FAFBFD',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 9,
                        }}
                    >
                        <AIcon
                            name="clipboard-check"
                            size={16}
                            color={C.primary}
                        />
                        <div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Tasklist Pekerjaan
                            </div>
                            <div style={{ fontSize: 11.5, color: C.faint }}>
                                Daftar tugas selama kunjungan
                            </div>
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: 8 }}>
                        <input
                            type="text"
                            placeholder="Tambah tugas baru…"
                            value={taskDraft}
                            onChange={(event) =>
                                setTaskDraft(event.target.value)
                            }
                            onKeyDown={(event) => {
                                // Enter adds a task; it must not submit the visit.
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    addTask();
                                }
                            }}
                            style={{ ...inputStyle, flex: 1 }}
                        />
                        <button
                            type="button"
                            onClick={addTask}
                            aria-label="Tambah tugas"
                            style={{
                                ...btnP,
                                width: 44,
                                height: 42,
                                padding: 0,
                                justifyContent: 'center',
                                flex: 'none',
                            }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                        </button>
                    </div>

                    {data.tasks.length === 0 ? (
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                textAlign: 'center',
                                padding: '10px 0',
                            }}
                        >
                            Belum ada tugas. Tugas bersifat opsional.
                        </div>
                    ) : (
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            {data.tasks.map((task, index) => (
                                <div
                                    key={`${task}-${index}`}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 10,
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        padding: '10px 12px',
                                        background: '#fff',
                                        fontSize: 13,
                                        color: C.text,
                                    }}
                                >
                                    <span>{task}</span>
                                    <button
                                        type="button"
                                        onClick={() => removeTask(index)}
                                        aria-label={`Hapus tugas ${task}`}
                                        style={{
                                            border: 'none',
                                            background: 'rgba(107,114,128,.14)',
                                            borderRadius: '50%',
                                            cursor: 'pointer',
                                            display: 'inline-flex',
                                            padding: 3,
                                        }}
                                    >
                                        <AIcon
                                            name="x"
                                            size={14}
                                            color={C.faint}
                                        />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}

                    {data.tasks.length > 0 && (
                        <TaskProgressBar
                            progress={{ done: 0, total: data.tasks.length }}
                        />
                    )}
                    <FieldError message={errors.tasks} />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>Latitude</label>
                        <input
                            type="number"
                            step="any"
                            placeholder="-6.2088"
                            value={data.latitude}
                            onChange={(event) =>
                                setData('latitude', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.latitude)}
                        />
                        <FieldError message={errors.latitude} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Longitude</label>
                        <input
                            type="number"
                            step="any"
                            placeholder="106.8456"
                            value={data.longitude}
                            onChange={(event) =>
                                setData('longitude', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.longitude)}
                        />
                        <FieldError message={errors.longitude} />
                    </div>
                </div>

                <div>
                    <button
                        type="button"
                        onClick={fillPreciseLocation}
                        disabled={locating}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            height: 32,
                            padding: '0 11px',
                            borderRadius: 7,
                            border: '1px solid rgba(47,84,201,.35)',
                            background: 'rgba(47,84,201,.07)',
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: C.primary,
                            cursor: locating ? 'wait' : 'pointer',
                        }}
                    >
                        <AIcon name="map-pin" size={14} color={C.primary} />
                        {locating
                            ? 'Mengambil lokasi…'
                            : 'Perbarui Lokasi Presisi'}
                    </button>
                    <FieldError message={locationError ?? undefined} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        rows={2}
                        placeholder="Catatan tambahan"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.notes)}
                    />
                    <FieldError message={errors.notes} />
                </div>
            </div>

            <div
                style={{
                    display: 'flex',
                    gap: 10,
                    justifyContent: 'flex-end',
                    padding: '16px 24px',
                    borderTop: `1px solid ${C.line}`,
                }}
            >
                <Link
                    href={cancelHref}
                    style={{
                        ...btnOut,
                        height: 44,
                        justifyContent: 'center',
                        textDecoration: 'none',
                    }}
                >
                    <AIcon name="x" size={16} />
                    Batalkan Kunjungan
                </Link>
                {onSaveDraft && (
                    <button
                        type="button"
                        onClick={onSaveDraft}
                        disabled={processing}
                        style={{
                            ...btnOut,
                            height: 44,
                            justifyContent: 'center',
                            cursor: processing ? 'not-allowed' : 'pointer',
                        }}
                    >
                        <AIcon name="save" size={16} />
                        Simpan sebagai Draft
                    </button>
                )}
                <button
                    type="submit"
                    disabled={processing}
                    style={{
                        ...btnP,
                        height: 44,
                        justifyContent: 'center',
                        opacity: processing ? 0.7 : 1,
                        cursor: processing ? 'not-allowed' : 'pointer',
                    }}
                >
                    <AIcon name={submitIcon} size={16} color="#fff" />
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}

export default VisitingForm;
