import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import FeatureCatalogController from '@/actions/App/Http/Controllers/Avana/FeatureCatalogController';
import { AIcon, btnP, C, card } from '@/lib/avana';

interface Feature {
    id: number;
    code: string;
    name: string;
    module_group: string;
    permission_modules: string[];
    is_active: boolean;
    tenants: number;
}

interface ActionMeta {
    key: string;
    label: string;
}

interface Props {
    features: Feature[];
    moduleGroups: string[];
    moduleOptions: string[];
    actions: ActionMeta[];
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

type Editing = { mode: 'create' } | { mode: 'edit'; feature: Feature } | null;

const blankForm = {
    code: '',
    name: '',
    module_group: '',
    permission_modules: '',
    is_active: true,
};

export default function KatalogFitur({
    features,
    moduleGroups,
    moduleOptions,
    actions,
}: Props) {
    const { flash } = usePage<FlashProps>().props;
    const [editing, setEditing] = useState<Editing>(null);
    const [form, setForm] = useState(blankForm);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const grouped = useMemo(() => {
        const map = new Map<string, Feature[]>();

        for (const feature of features) {
            const list = map.get(feature.module_group) ?? [];
            list.push(feature);
            map.set(feature.module_group, list);
        }

        return [...map.entries()];
    }, [features]);

    const openCreate = () => {
        setForm(blankForm);
        setErrors({});
        setEditing({ mode: 'create' });
    };

    const openEdit = (feature: Feature) => {
        setForm({
            code: feature.code,
            name: feature.name,
            module_group: feature.module_group,
            permission_modules: feature.permission_modules.join(', '),
            is_active: feature.is_active,
        });
        setErrors({});
        setEditing({ mode: 'edit', feature });
    };

    const close = () => setEditing(null);

    const submit = () => {
        const payload = {
            code: form.code.trim(),
            name: form.name.trim(),
            module_group: form.module_group.trim(),
            permission_modules: form.permission_modules
                .split(',')
                .map((m) => m.trim())
                .filter(Boolean),
            is_active: form.is_active,
        };

        const opts = {
            preserveScroll: true,
            onError: (e: Record<string, string>) => setErrors(e),
            onSuccess: () => close(),
        };

        if (editing?.mode === 'edit') {
            router.put(
                FeatureCatalogController.update(editing.feature.id).url,
                payload,
                opts,
            );
        } else {
            router.post(FeatureCatalogController.store().url, payload, opts);
        }
    };

    const remove = (feature: Feature) => {
        if (
            !window.confirm(
                `Hapus fitur "${feature.name}"? Menu terkait akan hilang dari matriks & tenant.`,
            )
        ) {
            return;
        }

        router.delete(FeatureCatalogController.destroy(feature.id).url, {
            preserveScroll: true,
        });
    };

    const toggleActive = (feature: Feature) => {
        router.put(
            FeatureCatalogController.update(feature.id).url,
            {
                code: feature.code,
                name: feature.name,
                module_group: feature.module_group,
                permission_modules: feature.permission_modules,
                is_active: !feature.is_active,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Katalog Fitur" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            <span>Platform</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Katalog Fitur
                            </span>
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Katalog Fitur
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                                maxWidth: 640,
                            }}
                        >
                            Daftar modul produk. Fitur baru otomatis muncul di
                            Hak Akses (master switch + izin per-peran) &amp;
                            bisa diaktifkan per tenant — tanpa ubah kode.
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} />
                        Tambah Fitur
                    </button>
                </div>

                {grouped.map(([group, list]) => (
                    <div key={group} style={{ marginBottom: 26 }}>
                        <div
                            style={{
                                fontSize: 11.5,
                                fontWeight: 700,
                                letterSpacing: '.05em',
                                textTransform: 'uppercase',
                                color: C.faint,
                                marginBottom: 10,
                            }}
                        >
                            {group} · {list.length}
                        </div>
                        <div style={{ ...card, overflow: 'hidden' }}>
                            {list.map((feature, i) => (
                                <div
                                    key={feature.id}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 16,
                                        padding: '15px 18px',
                                        borderTop:
                                            i === 0
                                                ? 'none'
                                                : `1px solid ${C.line}`,
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 9,
                                                flexWrap: 'wrap',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    fontSize: 14,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {feature.name}
                                            </span>
                                            <code
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    background: '#F1F3F9',
                                                    padding: '1px 7px',
                                                    borderRadius: 6,
                                                }}
                                            >
                                                {feature.code}
                                            </code>
                                            {feature.tenants > 0 && (
                                                <span
                                                    style={{
                                                        fontSize: 11,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {feature.tenants} tenant
                                                    aktif
                                                </span>
                                            )}
                                        </div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                flexWrap: 'wrap',
                                                marginTop: 7,
                                            }}
                                        >
                                            {feature.permission_modules
                                                .length === 0 ? (
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                        fontStyle: 'italic',
                                                    }}
                                                >
                                                    fitur-only (tanpa izin
                                                    per-peran)
                                                </span>
                                            ) : (
                                                feature.permission_modules.map(
                                                    (mod) => (
                                                        <span
                                                            key={mod}
                                                            style={{
                                                                fontSize: 11,
                                                                color: C.primary,
                                                                background:
                                                                    'rgba(47,84,201,.08)',
                                                                padding:
                                                                    '2px 8px',
                                                                borderRadius: 6,
                                                            }}
                                                        >
                                                            {mod}
                                                        </span>
                                                    ),
                                                )
                                            )}
                                        </div>
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 12,
                                            flex: 'none',
                                        }}
                                    >
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={feature.is_active}
                                            title={
                                                feature.is_active
                                                    ? 'Aktif — nonaktifkan untuk sembunyikan dari katalog'
                                                    : 'Nonaktif'
                                            }
                                            onClick={() =>
                                                toggleActive(feature)
                                            }
                                            style={{
                                                width: 44,
                                                height: 25,
                                                borderRadius: 100,
                                                border: 'none',
                                                cursor: 'pointer',
                                                position: 'relative',
                                                background: feature.is_active
                                                    ? C.primary
                                                    : '#D5DCEA',
                                                flex: 'none',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    position: 'absolute',
                                                    top: 3,
                                                    left: feature.is_active
                                                        ? 22
                                                        : 3,
                                                    width: 19,
                                                    height: 19,
                                                    borderRadius: '50%',
                                                    background: '#fff',
                                                    transition: 'left .15s',
                                                    boxShadow:
                                                        '0 1px 3px rgba(15,23,42,.2)',
                                                }}
                                            />
                                        </button>
                                        <button
                                            onClick={() => openEdit(feature)}
                                            title="Ubah"
                                            style={iconBtn}
                                        >
                                            <AIcon name="pencil" size={15} />
                                        </button>
                                        <button
                                            onClick={() => remove(feature)}
                                            title="Hapus"
                                            style={{
                                                ...iconBtn,
                                                color: '#DC2626',
                                            }}
                                        >
                                            <AIcon name="trash-2" size={15} />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {editing && (
                <FeatureModal
                    mode={editing.mode}
                    form={form}
                    setForm={setForm}
                    errors={errors}
                    moduleGroups={moduleGroups}
                    moduleOptions={moduleOptions}
                    actions={actions}
                    onClose={close}
                    onSubmit={submit}
                />
            )}
        </>
    );
}

const iconBtn: React.CSSProperties = {
    width: 32,
    height: 32,
    borderRadius: 8,
    border: `1px solid ${C.border}`,
    background: '#fff',
    color: C.muted,
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
};

function FeatureModal({
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
    form: typeof blankForm;
    setForm: React.Dispatch<React.SetStateAction<typeof blankForm>>;
    errors: Record<string, string>;
    moduleGroups: string[];
    moduleOptions: string[];
    actions: ActionMeta[];
    onClose: () => void;
    onSubmit: () => void;
}) {
    const set = (patch: Partial<typeof blankForm>) =>
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
                        list="module-groups"
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
                    <datalist id="module-groups">
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
                        list="module-options"
                        onChange={(e) =>
                            set({ permission_modules: e.target.value })
                        }
                        placeholder="mis. e_learning  (atau kosong)"
                        style={input}
                    />
                    <datalist id="module-options">
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
                        <AIcon name="x" size={15} color={C.muted} />
                        Batal
                    </button>
                    <button
                        onClick={onSubmit}
                        style={{ ...btnP, background: C.green }}
                    >
                        <AIcon
                            name={mode === 'edit' ? 'save' : 'plus'}
                            size={15}
                            color="#fff"
                        />
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
