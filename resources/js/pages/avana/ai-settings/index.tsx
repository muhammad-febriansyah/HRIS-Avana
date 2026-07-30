import { Head, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import AiSettingController from '@/actions/App/Http/Controllers/Avana/AiSettingController';
import { AIcon, btnSave, C, card } from '@/lib/avana';

interface Settings {
    provider: string;
    model: string;
    is_enabled: boolean;
    has_key: boolean;
    key_preview: string | null;
    is_ready: boolean;
    image_enabled: boolean;
    image_model: string;
    image_token_cost: number;
    can_generate_images: boolean;
    stt_enabled: boolean;
    stt_provider: string;
    stt_model: string;
    stt_language: string;
    has_stt_key: boolean;
    stt_key_preview: string | null;
    stt_token_cost_per_minute: number;
    meeting_max_minutes: number;
    meeting_audio_keep: boolean;
    meeting_pro_model: string;
    embedding_model: string;
    can_record_meetings: boolean;
}

interface PageProps {
    settings: Settings;
    providers: Record<string, string>;
    suggestedModels: Record<string, string[]>;
    imageModels: Record<string, string[]>;
    sttModels: Record<string, string[]>;
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
    imageModels,
    sttModels,
}: PageProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<{
        provider: string;
        model: string;
        api_key: string;
        is_enabled: boolean;
        image_enabled: boolean;
        image_model: string;
        image_token_cost: number;
        stt_enabled: boolean;
        stt_provider: string;
        stt_api_key: string;
        stt_model: string;
        stt_language: string;
        stt_token_cost_per_minute: number;
        meeting_max_minutes: number;
        meeting_audio_keep: boolean;
        meeting_pro_model: string;
        embedding_model: string;
    }>({
        provider: settings.provider,
        model: settings.model ?? '',
        api_key: '',
        is_enabled: settings.is_enabled,
        image_enabled: settings.image_enabled,
        image_model: settings.image_model ?? '',
        image_token_cost: settings.image_token_cost,
        stt_enabled: settings.stt_enabled,
        stt_provider: settings.stt_provider,
        stt_api_key: '',
        stt_model: settings.stt_model ?? '',
        stt_language: settings.stt_language ?? '',
        stt_token_cost_per_minute: settings.stt_token_cost_per_minute,
        meeting_max_minutes: settings.meeting_max_minutes,
        meeting_audio_keep: settings.meeting_audio_keep,
        meeting_pro_model: settings.meeting_pro_model ?? '',
        embedding_model: settings.embedding_model ?? '',
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
            onSuccess: () => form.reset('api_key', 'stt_api_key'),
        });
    };

    const isOllama = form.data.provider === 'ollama';
    const suggestions = suggestedModels[form.data.provider] ?? [];

    // Only two providers can draw. Saying so beats a switch that silently does
    // nothing after it is turned on.
    const imageSuggestions = imageModels[form.data.provider] ?? [];
    const providerCanDraw = imageSuggestions.length > 0;

    const sttSuggestions = sttModels[form.data.stt_provider] ?? [];

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

                    <div
                        style={{
                            borderTop: `1px solid ${C.border}`,
                            paddingTop: 20,
                            marginBottom: 22,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Pembuatan Gambar
                        </div>
                        <p style={{ ...hint, marginTop: 0, marginBottom: 14 }}>
                            {providerCanDraw
                                ? 'Asisten dapat menggambar bila diminta. Biayanya dipotong dari dompet token yang sama dengan chat.'
                                : `${providers[form.data.provider]} belum mendukung pembuatan gambar. Pilih OpenAI atau Google Gemini.`}
                        </p>

                        <label
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                fontSize: 14,
                                color: providerCanDraw ? C.text : C.faint,
                                cursor: providerCanDraw
                                    ? 'pointer'
                                    : 'not-allowed',
                                marginBottom: 16,
                            }}
                        >
                            <input
                                type="checkbox"
                                disabled={!providerCanDraw}
                                checked={form.data.image_enabled}
                                onChange={(e) =>
                                    form.setData(
                                        'image_enabled',
                                        e.target.checked,
                                    )
                                }
                            />
                            Izinkan asisten membuat gambar
                        </label>

                        {providerCanDraw && form.data.image_enabled && (
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(auto-fit, minmax(220px, 1fr))',
                                    gap: 16,
                                }}
                            >
                                <div>
                                    <label style={label} htmlFor="image_model">
                                        Model Gambar
                                    </label>
                                    <input
                                        id="image_model"
                                        list="image-model-suggestions"
                                        style={input}
                                        placeholder={imageSuggestions[0]}
                                        value={form.data.image_model}
                                        onChange={(e) =>
                                            form.setData(
                                                'image_model',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <datalist id="image-model-suggestions">
                                        {imageSuggestions.map((m) => (
                                            <option key={m} value={m} />
                                        ))}
                                    </datalist>
                                    <p style={hint}>
                                        Kosongkan untuk memakai{' '}
                                        {imageSuggestions[0]}.
                                    </p>
                                </div>

                                <div>
                                    <label
                                        style={label}
                                        htmlFor="image_token_cost"
                                    >
                                        Biaya per Gambar (token)
                                    </label>
                                    <input
                                        id="image_token_cost"
                                        type="number"
                                        min={0}
                                        style={input}
                                        placeholder="cth. 1000"
                                        value={form.data.image_token_cost}
                                        onChange={(e) =>
                                            form.setData(
                                                'image_token_cost',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <p style={hint}>
                                        Penyedia menagih per gambar, bukan per
                                        token, jadi harganya dikonversi di sini.
                                    </p>
                                    {form.errors.image_token_cost && (
                                        <p style={{ ...hint, color: C.red }}>
                                            {form.errors.image_token_cost}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    <div
                        style={{
                            borderTop: `1px solid ${C.border}`,
                            paddingTop: 20,
                            marginBottom: 22,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Rapat & Transkrip (AI Recorder)
                        </div>
                        <p style={{ ...hint, marginTop: 0, marginBottom: 14 }}>
                            Transkripsi memakai penyedia suara terpisah — ia
                            menagih per detik audio dan memisahkan pembicara,
                            jauh lebih murah daripada mengirim audio ke model
                            chat. Ringkasan otomatis tetap memakai model chat di
                            atas; model mahal hanya dipakai untuk analisis
                            premium yang diklik pengguna.
                        </p>

                        <label
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                fontSize: 14,
                                color: C.text,
                                cursor: 'pointer',
                                marginBottom: 16,
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={form.data.stt_enabled}
                                onChange={(e) =>
                                    form.setData(
                                        'stt_enabled',
                                        e.target.checked,
                                    )
                                }
                            />
                            Aktifkan perekaman & transkripsi rapat
                        </label>

                        {form.data.stt_enabled && (
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(auto-fit, minmax(220px, 1fr))',
                                    gap: 16,
                                }}
                            >
                                <div>
                                    <label style={label} htmlFor="stt_provider">
                                        Penyedia Transkripsi
                                    </label>
                                    <select
                                        id="stt_provider"
                                        style={input}
                                        value={form.data.stt_provider}
                                        onChange={(e) =>
                                            form.setData(
                                                'stt_provider',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {Object.keys(sttModels).map((key) => (
                                            <option key={key} value={key}>
                                                {key === 'deepgram'
                                                    ? 'Deepgram'
                                                    : key}
                                            </option>
                                        ))}
                                    </select>
                                    <p style={hint}>
                                        Deepgram memisahkan pembicara
                                        (diarization) di permintaan yang sama.
                                    </p>
                                </div>

                                <div>
                                    <label style={label} htmlFor="stt_api_key">
                                        API Key Transkripsi
                                    </label>
                                    <input
                                        id="stt_api_key"
                                        type="password"
                                        autoComplete="new-password"
                                        style={input}
                                        placeholder={
                                            settings.has_stt_key
                                                ? `Tersimpan (${settings.stt_key_preview}) — biarkan kosong untuk mempertahankan`
                                                : 'Tempel project key Deepgram'
                                        }
                                        value={form.data.stt_api_key}
                                        onChange={(e) =>
                                            form.setData(
                                                'stt_api_key',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <p style={hint}>
                                        Key ini tidak pernah dikirim ke HP —
                                        aplikasi hanya menerima token sementara
                                        berumur 1 menit.
                                    </p>
                                    {form.errors.stt_api_key && (
                                        <p style={{ ...hint, color: C.red }}>
                                            {form.errors.stt_api_key}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label style={label} htmlFor="stt_model">
                                        Model Suara
                                    </label>
                                    <input
                                        id="stt_model"
                                        list="stt-model-suggestions"
                                        style={input}
                                        placeholder={
                                            sttSuggestions[0] ?? 'nova-2'
                                        }
                                        value={form.data.stt_model}
                                        onChange={(e) =>
                                            form.setData(
                                                'stt_model',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <datalist id="stt-model-suggestions">
                                        {sttSuggestions.map((m) => (
                                            <option key={m} value={m} />
                                        ))}
                                    </datalist>
                                    <p style={hint}>
                                        Kosongkan untuk memakai{' '}
                                        {sttSuggestions[0] ?? 'nova-2'}.
                                    </p>
                                </div>

                                <div>
                                    <label style={label} htmlFor="stt_language">
                                        Bahasa
                                    </label>
                                    <input
                                        id="stt_language"
                                        style={input}
                                        placeholder="id"
                                        value={form.data.stt_language}
                                        onChange={(e) =>
                                            form.setData(
                                                'stt_language',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <p style={hint}>
                                        Kode bahasa penyedia, mis.{' '}
                                        <code>id</code> untuk Bahasa Indonesia.
                                    </p>
                                </div>

                                <div>
                                    <label
                                        style={label}
                                        htmlFor="stt_token_cost_per_minute"
                                    >
                                        Biaya per Menit Audio (token)
                                    </label>
                                    <input
                                        id="stt_token_cost_per_minute"
                                        type="number"
                                        min={0}
                                        style={input}
                                        placeholder="cth. 500"
                                        value={
                                            form.data.stt_token_cost_per_minute
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'stt_token_cost_per_minute',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <p style={hint}>
                                        Penyedia menagih detik audio, dompet
                                        menghitung token — konversinya di sini.
                                    </p>
                                    {form.errors.stt_token_cost_per_minute && (
                                        <p style={{ ...hint, color: C.red }}>
                                            {
                                                form.errors
                                                    .stt_token_cost_per_minute
                                            }
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label
                                        style={label}
                                        htmlFor="meeting_max_minutes"
                                    >
                                        Batas Durasi Rekaman (menit)
                                    </label>
                                    <input
                                        id="meeting_max_minutes"
                                        type="number"
                                        min={1}
                                        style={input}
                                        placeholder="180"
                                        value={form.data.meeting_max_minutes}
                                        onChange={(e) =>
                                            form.setData(
                                                'meeting_max_minutes',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <p style={hint}>
                                        Rekaman berhenti sendiri di batas ini,
                                        supaya sesi yang terlupa tidak menguras
                                        token.
                                    </p>
                                    {form.errors.meeting_max_minutes && (
                                        <p style={{ ...hint, color: C.red }}>
                                            {form.errors.meeting_max_minutes}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label
                                        style={label}
                                        htmlFor="meeting_pro_model"
                                    >
                                        Model Analisis Premium
                                    </label>
                                    <input
                                        id="meeting_pro_model"
                                        list="model-suggestions"
                                        style={input}
                                        placeholder={
                                            suggestions[1] ?? 'model-reasoning'
                                        }
                                        value={form.data.meeting_pro_model}
                                        onChange={(e) =>
                                            form.setData(
                                                'meeting_pro_model',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <p style={hint}>
                                        Dipakai hanya untuk Executive Summary,
                                        analisis keputusan, risiko, sentimen,
                                        dan rekomendasi. Kosongkan untuk memakai
                                        model chat.
                                    </p>
                                </div>

                                <div>
                                    <label
                                        style={label}
                                        htmlFor="embedding_model"
                                    >
                                        Model Embedding
                                    </label>
                                    <input
                                        id="embedding_model"
                                        style={input}
                                        placeholder="text-embedding-3-small"
                                        value={form.data.embedding_model}
                                        onChange={(e) =>
                                            form.setData(
                                                'embedding_model',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <p style={hint}>
                                        Dipakai agar asisten bisa menjawab
                                        pertanyaan soal isi rapat tanpa membaca
                                        transkrip utuh.
                                    </p>
                                </div>

                                <label
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        fontSize: 14,
                                        color: C.text,
                                        cursor: 'pointer',
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.meeting_audio_keep}
                                        onChange={(e) =>
                                            form.setData(
                                                'meeting_audio_keep',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Simpan berkas audio rapat
                                </label>
                            </div>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        style={{
                            ...btnSave,
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
