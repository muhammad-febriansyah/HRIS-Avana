import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { EmployeeOption, TicketFormData, UserOption } from './types';
import { CATEGORY_OPTIONS, PRIORITY_OPTIONS } from './types';

interface HelpdeskFormProps {
    form: InertiaFormProps<TicketFormData>;
    employees: EmployeeOption[];
    users: UserOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a helpdesk ticket. */
export function HelpdeskForm({
    form,
    employees,
    users,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: HelpdeskFormProps) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 640 }}>
            <div
                style={{
                    padding: '22px 24px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>
                        Subjek <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        value={data.subject}
                        onChange={(event) =>
                            setData('subject', event.target.value)
                        }
                        placeholder="Contoh: Laptop tidak bisa menyala"
                        style={withError(inputStyle, !!errors.subject)}
                    />
                    <FieldError message={errors.subject} />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Pelapor <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.requester_id}
                            onChange={(event) =>
                                setData('requester_id', event.target.value)
                            }
                            style={withError(
                                selectStyle,
                                !!errors.requester_id,
                            )}
                        >
                            <option value="">Pilih karyawan</option>
                            {employees.map((employee) => (
                                <option
                                    key={employee.id}
                                    value={String(employee.id)}
                                >
                                    {employee.name ?? '—'}
                                    {employee.employee_number
                                        ? ` (${employee.employee_number})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.requester_id} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Penanggung Jawab</label>
                        <select
                            value={data.assignee_id}
                            onChange={(event) =>
                                setData('assignee_id', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.assignee_id)}
                        >
                            <option value="">Belum ditugaskan</option>
                            {users.map((user) => (
                                <option key={user.id} value={String(user.id)}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.assignee_id} />
                    </div>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Kategori <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.category}
                            onChange={(event) =>
                                setData('category', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.category)}
                        >
                            {CATEGORY_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.category} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Prioritas <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.priority}
                            onChange={(event) =>
                                setData('priority', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.priority)}
                        >
                            {PRIORITY_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.priority} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Deskripsi <span style={{ color: C.red }}>*</span>
                    </label>
                    <textarea
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Jelaskan detail keluhan atau permintaan"
                        style={withError(textareaStyle, !!errors.description)}
                    />
                    <FieldError message={errors.description} />
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
                    Batal
                </Link>
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

export default HelpdeskForm;
