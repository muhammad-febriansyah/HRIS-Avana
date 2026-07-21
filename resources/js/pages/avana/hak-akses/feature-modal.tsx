import { AIcon, btnP, C, card } from '@/lib/avana';

export interface FeatureForm {
    code: string;
    name: string;
    module_group: string;
    permission_modules: string;
    is_active: boolean;
}

export const blankFeatureForm: FeatureForm = {
    code: '',
    name: '',
    module_group: '',
    permission_modules: '',
    is_active: true,
};

interface ActionMeta {
    key: string;
    label: string;
}

/** Create / edit modal for a product feature (used inside the Hak Akses tab). */
export function FeatureModal({
    mode,
    form,
    setForm,
    errors,
    moduleGroups,
    moduleOptions,
    actions,
    onClose,
    onSubmit,
}: {
    mode: 'create' | 'edit';
    form: FeatureForm;
    setForm: React.Dispatch<React.SetStateAction<FeatureForm>>;
    errors: Record<string, string>;
    moduleGroups: string[];
    moduleOptions: string[];
    actions: ActionMeta[];
    onClose: () => void;
    onSubmit: () => void;
}) {
    const set = (patch: Partial<FeatureForm>) =>
        setForm((f) => ({ ...f, ...patch }));

    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(15,23,42,.45)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
                zIndex: 50,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    ...card,
                    width: 520,
                    maxWidth: '100%',
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    padding: 24,
                }}
            >
                <h2
                    style={{
                        fontSize: 18,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    {mode === 'edit' ? 'Ubah Fitur' : 'Tambah Fitur'}
                </h2>
                <p style={{ fontSize: 13, color: C.muted, margin: '0 0 18px' }}>
                    Code &amp; module_group huruf kecil/underscore. Modul izin =
                    prefix permission untuk kolom aksi per-peran.
                </p>

                <Field label="Code" error={errors.code}>
                    <input
                        value={form.code}
                        disabled={mode === 'edit'}
                        onChange={(e) =>
                            set({
                                code: e.target.value
                                    .toLowerCase()
                                    .replace(/[^a-z0-9_]/g, '_'),
                            })
                        }
                        placeholder="mis. e_learning"
                        style={{
                            ...input,
                            background: mode === 'edit' ? '#F1F3F9' : '#fff',
                        }}
                    />
                </Field>

                <Field label="Nama" error={errors.name}>
                    <input
                        value={form.name}
                        onChange={(e) => set({ name: e.target.value })}
                        placeholder="mis. E-Learning"
                        style={input}
                    />
                </Field>

                <Field label="Module Group" error={errors.module_group}>
                    <input
                        value={form.module_group}
                        list="hak-akses-module-groups"
                        onChange={(e) =>
                            set({
                                module_group: e.target.value
                                    .toLowerCase()
                                    .replace(/[^a-z0-9_]/g, '_'),
                            })
                        }
                        placeholder="mis. talent"
                        style={input}
                    />
                    <datalist id="hak-akses-module-groups">
                        {moduleGroups.map((g) => (
                            <option key={g} value={g} />
                        ))}
                    </datalist>
                </Field>

                <Field
                    label="Modul Izin (permission_modules)"
                    error={errors.permission_modules}
                    hint={`Pisah koma. Kosong = fitur-only. Tiap modul dapat aksi: ${actions
                        .map((a) => a.label)
                        .join('/')}.`}
                >
                    <input
                        value={form.permission_modules}
                        list="hak-akses-module-options"
                        onChange={(e) =>
                            set({ permission_modules: e.target.value })
                        }
                        placeholder="mis. e_learning  (atau kosong)"
                        style={input}
                    />
                    <datalist id="hak-akses-module-options">
                        {moduleOptions.map((m) => (
                            <option key={m} value={m} />
                        ))}
                    </datalist>
                </Field>

                <label
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 9,
                        marginTop: 4,
                        cursor: 'pointer',
                        fontSize: 13.5,
                        color: C.text,
                    }}
                >
                    <input
                        type="checkbox"
                        checked={form.is_active}
                        onChange={(e) => set({ is_active: e.target.checked })}
                    />
                    Aktif di katalog
                </label>

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                        marginTop: 22,
                    }}
                >
                    <button
                        onClick={onClose}
                        style={{
                            ...btnP,
                            background: '#fff',
                            color: C.muted,
                            border: `1px solid ${C.border}`,
                        }}
                    >
                        Batal
                    </button>
                    <button onClick={onSubmit} style={btnP}>
                        {mode === 'edit' ? 'Simpan' : 'Tambah'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div style={{ marginBottom: 14 }}>
            <div
                style={{
                    fontSize: 12.5,
                    fontWeight: 600,
                    color: C.text,
                    marginBottom: 6,
                }}
            >
                {label}
            </div>
            {children}
            {hint && !error && (
                <div style={{ fontSize: 11.5, color: C.faint, marginTop: 5 }}>
                    {hint}
                </div>
            )}
            {error && (
                <div style={{ fontSize: 11.5, color: '#DC2626', marginTop: 5 }}>
                    {error}
                </div>
            )}
        </div>
    );
}

const input: React.CSSProperties = {
    width: '100%',
    padding: '9px 12px',
    borderRadius: 9,
    border: `1px solid ${C.border}`,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    boxSizing: 'border-box',
};
