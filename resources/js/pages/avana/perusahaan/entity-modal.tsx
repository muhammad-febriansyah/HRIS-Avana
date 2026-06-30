import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import { buildInitialForm } from './types';
import type { EntityRecord, SetupOptions, TabDef } from './types';

interface EntityModalProps {
    tab: TabDef;
    options: SetupOptions;
    record: EntityRecord | null;
    onClose: () => void;
}

/** Inline create/edit form for the active tab. */
export function EntityModal({ tab, options, record, onClose }: EntityModalProps) {
    const form = useForm<Record<string, string>>(buildInitialForm(tab, record));

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const callbacks = { preserveScroll: true, onSuccess: () => onClose() };

        if (record) {
            form.put(`/avana/perusahaan/${tab.key}/${record.id}`, callbacks);
        } else {
            form.post(`/avana/perusahaan/${tab.key}`, callbacks);
        }
    };

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 520,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        padding: '18px 22px',
                        borderBottom: `1px solid ${C.line}`,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <div
                        style={{ fontSize: 16, fontWeight: 600, color: C.navy }}
                    >
                        {record ? `Ubah ${tab.label}` : `Tambah ${tab.label}`}
                    </div>
                    <button
                        onClick={onClose}
                        aria-label="Tutup"
                        style={{
                            width: 32,
                            height: 32,
                            border: 'none',
                            background: 'none',
                            borderRadius: 8,
                            cursor: 'pointer',
                            color: C.muted,
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="x" size={18} />
                    </button>
                </div>

                <form onSubmit={submit} style={{ padding: '20px 22px' }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 16,
                        }}
                    >
                        {tab.fields.map((field) => {
                            const hasError = !!form.errors[field.name];
                            const value = form.data[field.name] ?? '';
                            const onChange = (v: string) =>
                                form.setData(field.name, v);

                            return (
                                <div
                                    key={field.name}
                                    style={{
                                        gridColumn:
                                            field.span === 'half'
                                                ? 'span 1'
                                                : 'span 2',
                                    }}
                                >
                                    <label style={fieldLabelStyle}>
                                        {field.label}
                                        {field.required && (
                                            <span style={{ color: C.red }}>
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </label>

                                    {field.type === 'select' ? (
                                        <select
                                            value={value}
                                            onChange={(event) =>
                                                onChange(event.target.value)
                                            }
                                            style={withError(
                                                selectStyle,
                                                hasError,
                                            )}
                                        >
                                            {!field.options && (
                                                <option value="">
                                                    Pilih{' '}
                                                    {field.label.toLowerCase()}
                                                </option>
                                            )}
                                            {field.optionsKey &&
                                                options[field.optionsKey].map(
                                                    (option) => (
                                                        <option
                                                            key={option.id}
                                                            value={String(
                                                                option.id,
                                                            )}
                                                        >
                                                            {option.name}
                                                        </option>
                                                    ),
                                                )}
                                            {field.options?.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    ) : field.type === 'textarea' ? (
                                        <textarea
                                            value={value}
                                            placeholder={field.placeholder}
                                            onChange={(event) =>
                                                onChange(event.target.value)
                                            }
                                            style={withError(
                                                textareaStyle,
                                                hasError,
                                            )}
                                        />
                                    ) : (
                                        <input
                                            type={
                                                field.type === 'number'
                                                    ? 'number'
                                                    : field.type === 'time'
                                                      ? 'time'
                                                      : 'text'
                                            }
                                            value={value}
                                            placeholder={field.placeholder}
                                            onChange={(event) =>
                                                onChange(event.target.value)
                                            }
                                            style={withError(
                                                inputStyle,
                                                hasError,
                                            )}
                                        />
                                    )}

                                    <FieldError
                                        message={form.errors[field.name]}
                                    />
                                </div>
                            );
                        })}
                    </div>

                    <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                        <button
                            type="button"
                            onClick={onClose}
                            style={{
                                ...btnOut,
                                flex: 1,
                                justifyContent: 'center',
                            }}
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            style={{
                                ...btnP,
                                flex: 1,
                                justifyContent: 'center',
                                opacity: form.processing ? 0.7 : 1,
                                cursor: form.processing
                                    ? 'not-allowed'
                                    : 'pointer',
                            }}
                        >
                            <AIcon name="check" size={16} color="#fff" />
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
