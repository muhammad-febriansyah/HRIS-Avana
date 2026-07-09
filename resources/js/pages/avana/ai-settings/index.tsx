import { Head, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import AiSettingController from '@/actions/App/Http/Controllers/Avana/AiSettingController';
import { AIcon, btnP, C, card } from '@/lib/avana';

interface Settings {
    provider: string;
    model: string;
    is_enabled: boolean;
    has_key: boolean;
    key_preview: string | null;
    is_ready: boolean;
}

interface PageProps {
    settings: Settings;
    providers: Record<string, string>;
    suggestedModels: Record<string, string[]>;
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

const label: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 600,
    color: C.text,
    marginBottom: 6,
};

const input: CSSProperties = {
    width: '100%',
    padding: '10px 12px',
    fontSize: 14,
    color: C.text,
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    outline: 'none',
};

const hint: CSSProperties = {
    fontSize: 12,
    color: C.muted,
    marginTop: 6,
};

export default function AiSettings({
    settings,
    providers,
    suggestedModels,
}: PageProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<{
        provider: string;
        model: string;
        api_key: string;
        is_enabled: boolean;
    }>({
        provider: settings.provider,
        model: settings.model ?? '',
        api_key: '',
        is_enabled: settings.is_enabled,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(AiSettingController.update().url, {
            preserveScroll: true,
            onSuccess: () => form.reset('api_key'),
        });
    };

    const isOllama = form.data.provider === 'ollama';
    const suggestions = suggestedModels[form.data.provider] ?? [];

    return (
        <>
            <Head title="Pengaturan AI" />

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
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Pengaturan AI
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
                            Pengaturan AI
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Pilih penyedia AI, API key, dan model untuk AI
                            Assistant.
                        </div>
                    </div>

                    <div
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            padding: '4px 10px',
                            borderRadius: 999,
                            fontSize: 12,
                            fontWeight: 600,
                            color: settings.is_ready ? C.green : C.amber,
                            background: settings.is_ready
                                ? '#EAF7EF'
                                : '#FDF4E7',
                        }}
                    >
                        <AIcon
                            name={
                                settings.is_ready
                                    ? 'circle-check'
                                    : 'circle-alert'
                            }
                            size={14}
                            color={settings.is_ready ? C.green : C.amber}
                        />
                        {settings.is_ready
                            ? 'Siap digunakan'
                            : 'Belum dikonfigurasi'}
                    </div>
                </div>

                <form onSubmit={submit} style={{ ...card, padding: 22 }}>
                    <div style={{ marginBottom: 18 }}>
                        <label style={label} htmlFor="provider">
                            Penyedia AI
                        </label>
                        <select
                            id="provider"
                            style={input}
                            value={form.data.provider}
                            onChange={(e) =>
                                form.setData('provider', e.target.value)
                            }
                        >
                            {Object.entries(providers).map(([key, name]) => (
                                <option key={key} value={key}>
                                    {name}
                                </option>
                            ))}
                        </select>
                        {form.errors.provider && (
                            <p style={{ ...hint, color: C.red }}>
                                {form.errors.provider}
                            </p>
                        )}
                    </div>

                    <div style={{ marginBottom: 18 }}>
                        <label style={label} htmlFor="model">
                            Model
                        </label>
                        <input
                            id="model"
                            list="model-suggestions"
                            style={input}
                            placeholder={suggestions[0] ?? 'nama-model'}
                            value={form.data.model}
                            onChange={(e) =>
                                form.setData('model', e.target.value)
                            }
                        />
                        <datalist id="model-suggestions">
                            {suggestions.map((m) => (
                                <option key={m} value={m} />
                            ))}
                        </datalist>
                        <p style={hint}>
                            Kosongkan untuk memakai model bawaan penyedia.
                        </p>
                        {form.errors.model && (
                            <p style={{ ...hint, color: C.red }}>
                                {form.errors.model}
                            </p>
                        )}
                    </div>

                    <div style={{ marginBottom: 18 }}>
                        <label style={label} htmlFor="api_key">
                            API Key
                        </label>
                        <input
                            id="api_key"
                            type="password"
                            autoComplete="new-password"
                            style={{
                                ...input,
                                background: isOllama ? C.surface : '#fff',
                            }}
                            disabled={isOllama}
                            placeholder={
                                isOllama
                                    ? 'Ollama lokal tidak perlu API key'
                                    : settings.has_key
                                      ? `Tersimpan (${settings.key_preview}) — biarkan kosong untuk mempertahankan`
                                      : 'Tempel API key di sini'
                            }
                            value={form.data.api_key}
                            onChange={(e) =>
                                form.setData('api_key', e.target.value)
                            }
                        />
                        <p style={hint}>
                            Key disimpan terenkripsi dan tidak pernah
                            ditampilkan kembali.
                        </p>
                        {form.errors.api_key && (
                            <p style={{ ...hint, color: C.red }}>
                                {form.errors.api_key}
                            </p>
                        )}
                    </div>

                    <label
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            fontSize: 14,
                            color: C.text,
                            cursor: 'pointer',
                            marginBottom: 22,
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={form.data.is_enabled}
                            onChange={(e) =>
                                form.setData('is_enabled', e.target.checked)
                            }
                        />
                        Aktifkan AI Assistant
                    </label>

                    <button
                        type="submit"
                        disabled={form.processing}
                        style={{
                            ...btnP,
                            opacity: form.processing ? 0.6 : 1,
                            cursor: form.processing ? 'default' : 'pointer',
                        }}
                    >
                        <AIcon name="save" size={16} color="#fff" />
                        Simpan
                    </button>
                </form>
            </div>
        </>
    );
}
