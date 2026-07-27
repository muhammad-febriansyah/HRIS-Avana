import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import EmailSettingController from '@/actions/App/Http/Controllers/Avana/EmailSettingController';
import { AIcon, btnP, C, card } from '@/lib/avana';

interface Settings {
    from_name: string | null;
    from_email: string | null;
    host: string | null;
    port: number | null;
    encryption: string;
    username: string | null;
    is_enabled: boolean;
    has_password: boolean;
    password_preview: string | null;
    is_ready: boolean;
}

interface LogRow {
    to: string;
    subject: string | null;
    status: string;
    error: string | null;
    date: string | null;
}

interface PageProps {
    scope: 'platform' | 'tenant';
    settings: Settings;
    encryptions: Record<string, string>;
    logs: LogRow[];
}

interface FlashProps {
    flash?: { success?: string };
    errors: Record<string, string>;
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

const hint: CSSProperties = { fontSize: 12, color: C.muted, marginTop: 6 };

export default function EmailSettings({
    scope,
    settings,
    encryptions,
    logs,
}: PageProps) {
    const { flash, errors } = usePage<FlashProps>().props;
    const [tab, setTab] = useState<'connection' | 'history'>('connection');
    const [testing, setTesting] = useState(false);

    const form = useForm<{
        from_name: string;
        from_email: string;
        host: string;
        port: string;
        encryption: string;
        username: string;
        password: string;
        is_enabled: boolean;
    }>({
        from_name: settings.from_name ?? '',
        from_email: settings.from_email ?? '',
        host: settings.host ?? '',
        port: settings.port ? String(settings.port) : '',
        encryption: settings.encryption ?? 'tls',
        username: settings.username ?? '',
        password: '',
        is_enabled: settings.is_enabled,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    useEffect(() => {
        if (errors?.email) {
            toast.error(errors.email, { id: errors.email });
        }
    }, [errors?.email]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(EmailSettingController.update().url, {
            preserveScroll: true,
            onSuccess: () => form.reset('password'),
        });
    };

    const sendTest = () => {
        setTesting(true);
        router.post(
            EmailSettingController.test().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTesting(false),
            },
        );
    };

    const statusBadge = (status: string) => {
        const ok = status === 'sent';

        return (
            <span
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 5,
                    padding: '3px 9px',
                    borderRadius: 999,
                    fontSize: 12,
                    fontWeight: 600,
                    color: ok ? C.green : C.red,
                    background: ok ? '#EAF7EF' : '#FCEBEB',
                }}
            >
                <AIcon
                    name={ok ? 'circle-check' : 'circle-alert'}
                    size={13}
                    color={ok ? C.green : C.red}
                />
                {ok ? 'Terkirim' : 'Gagal'}
            </span>
        );
    };

    const tabBtn = (
        key: 'connection' | 'history',
        icon: string,
        text: string,
    ) => {
        const active = tab === key;

        return (
            <button
                type="button"
                onClick={() => setTab(key)}
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 8,
                    padding: '10px 18px',
                    fontSize: 14,
                    fontWeight: 600,
                    borderRadius: 10,
                    cursor: 'pointer',
                    color: active ? C.primary : C.muted,
                    background: active ? '#fff' : 'transparent',
                    border: `1px solid ${active ? C.primary : 'transparent'}`,
                }}
            >
                <AIcon
                    name={icon}
                    size={16}
                    color={active ? C.primary : C.muted}
                />
                {text}
            </button>
        );
    };

    return (
        <>
            <Head title="Pengaturan Email" />

            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                        }}
                    >
                        <div
                            style={{
                                width: 44,
                                height: 44,
                                borderRadius: 12,
                                background: '#EEF2FE',
                                display: 'grid',
                                placeItems: 'center',
                            }}
                        >
                            <AIcon name="mail" size={22} color={C.primary} />
                        </div>
                        <div>
                            <h1
                                style={{
                                    fontSize: 20,
                                    fontWeight: 700,
                                    color: C.navy,
                                    margin: 0,
                                }}
                            >
                                Pengaturan Email
                            </h1>
                            <p
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    margin: 0,
                                }}
                            >
                                Konfigurasi SMTP untuk mengirim notifikasi &amp;
                                email sistem.
                            </p>
                        </div>
                    </div>
                    <span
                        style={{
                            padding: '4px 12px',
                            borderRadius: 999,
                            fontSize: 12,
                            fontWeight: 600,
                            color: C.primary,
                            background: '#EEF2FE',
                        }}
                    >
                        {scope === 'platform'
                            ? 'Default Platform'
                            : 'Tenant Ini'}
                    </span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 6,
                        padding: 4,
                        background: C.surface,
                        borderRadius: 12,
                        marginBottom: 18,
                        width: 'fit-content',
                    }}
                >
                    {tabBtn('connection', 'settings-2', 'Koneksi')}
                    {tabBtn('history', 'history', 'Riwayat')}
                </div>

                {tab === 'connection' && (
                    <form onSubmit={submit} style={{ ...card, padding: 22 }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                marginBottom: 4,
                            }}
                        >
                            <AIcon name="mail" size={17} color={C.primary} />
                            <h2
                                style={{
                                    fontSize: 15,
                                    fontWeight: 700,
                                    color: C.text,
                                    margin: 0,
                                }}
                            >
                                Konfigurasi SMTP
                            </h2>
                        </div>
                        <p style={{ ...hint, marginTop: 0, marginBottom: 18 }}>
                            {settings.is_ready
                                ? 'Konfigurasi siap digunakan.'
                                : 'Lengkapi & aktifkan untuk mulai mengirim email.'}
                        </p>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 16,
                            }}
                        >
                            <div>
                                <label style={label} htmlFor="from_name">
                                    Nama Pengirim
                                </label>
                                <input
                                    id="from_name"
                                    style={input}
                                    placeholder="AvanaHR"
                                    value={form.data.from_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'from_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <label style={label} htmlFor="from_email">
                                    Email Pengirim
                                </label>
                                <input
                                    id="from_email"
                                    type="email"
                                    style={input}
                                    placeholder="noreply@perusahaan.co.id"
                                    value={form.data.from_email}
                                    onChange={(e) =>
                                        form.setData(
                                            'from_email',
                                            e.target.value,
                                        )
                                    }
                                />
                                {form.errors.from_email && (
                                    <p style={{ ...hint, color: C.red }}>
                                        {form.errors.from_email}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '2fr 1fr 1fr',
                                gap: 16,
                                marginTop: 16,
                            }}
                        >
                            <div>
                                <label style={label} htmlFor="host">
                                    SMTP Host
                                </label>
                                <input
                                    id="host"
                                    style={input}
                                    placeholder="smtp.gmail.com"
                                    value={form.data.host}
                                    onChange={(e) =>
                                        form.setData('host', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label style={label} htmlFor="port">
                                    Port
                                </label>
                                <input
                                    id="port"
                                    inputMode="numeric"
                                    style={input}
                                    placeholder="587"
                                    value={form.data.port}
                                    onChange={(e) =>
                                        form.setData(
                                            'port',
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                />
                                {form.errors.port && (
                                    <p style={{ ...hint, color: C.red }}>
                                        {form.errors.port}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label style={label} htmlFor="encryption">
                                    Enkripsi
                                </label>
                                <select
                                    id="encryption"
                                    style={input}
                                    value={form.data.encryption}
                                    onChange={(e) =>
                                        form.setData(
                                            'encryption',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {Object.entries(encryptions).map(
                                        ([k, v]) => (
                                            <option key={k} value={k}>
                                                {v}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 16,
                                marginTop: 16,
                            }}
                        >
                            <div>
                                <label style={label} htmlFor="username">
                                    Username
                                </label>
                                <input
                                    id="username"
                                    style={input}
                                    autoComplete="off"
                                    placeholder="user@perusahaan.co.id"
                                    value={form.data.username}
                                    onChange={(e) =>
                                        form.setData('username', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label style={label} htmlFor="password">
                                    Password / App Secret
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    style={input}
                                    placeholder={
                                        settings.has_password
                                            ? 'Tersimpan — biarkan kosong untuk mempertahankan'
                                            : 'Masukkan password SMTP'
                                    }
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                />
                                <p style={hint}>Disimpan terenkripsi.</p>
                            </div>
                        </div>

                        <label
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                fontSize: 14,
                                color: C.text,
                                cursor: 'pointer',
                                margin: '18px 0 22px',
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={form.data.is_enabled}
                                onChange={(e) =>
                                    form.setData('is_enabled', e.target.checked)
                                }
                            />
                            Aktifkan pengiriman email
                        </label>

                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                alignItems: 'center',
                                borderTop: `1px solid ${C.line}`,
                                paddingTop: 18,
                            }}
                        >
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    background: C.green,
                                    opacity: form.processing ? 0.6 : 1,
                                    cursor: form.processing
                                        ? 'default'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="save" size={16} color="#fff" />
                                Simpan
                            </button>
                            <button
                                type="button"
                                onClick={sendTest}
                                disabled={testing || !settings.is_ready}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    padding: '10px 16px',
                                    fontSize: 14,
                                    fontWeight: 600,
                                    borderRadius: 8,
                                    color: C.text,
                                    background: '#fff',
                                    border: `1px solid ${C.border}`,
                                    cursor:
                                        testing || !settings.is_ready
                                            ? 'default'
                                            : 'pointer',
                                    opacity:
                                        testing || !settings.is_ready ? 0.5 : 1,
                                }}
                            >
                                <AIcon name="send" size={16} color={C.text} />
                                {testing ? 'Mengirim…' : 'Tes Koneksi'}
                            </button>
                        </div>
                    </form>
                )}

                {tab === 'history' && (
                    <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                        <div style={{ padding: '18px 22px 6px' }}>
                            <h2
                                style={{
                                    fontSize: 15,
                                    fontWeight: 700,
                                    color: C.text,
                                    margin: 0,
                                }}
                            >
                                Riwayat Email Terkirim
                            </h2>
                            <p style={hint}>
                                Catatan pengiriman email percobaan &amp; sistem.
                            </p>
                        </div>

                        {logs.length === 0 ? (
                            <div
                                style={{
                                    padding: '40px 22px',
                                    textAlign: 'center',
                                    color: C.muted,
                                    fontSize: 14,
                                }}
                            >
                                Belum ada riwayat email.
                            </div>
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        fontSize: 13,
                                    }}
                                >
                                    <thead>
                                        <tr
                                            style={{
                                                textAlign: 'left',
                                                color: C.muted,
                                                background: C.surface,
                                            }}
                                        >
                                            <th
                                                style={{ padding: '10px 22px' }}
                                            >
                                                Tanggal
                                            </th>
                                            <th
                                                style={{ padding: '10px 14px' }}
                                            >
                                                Kepada
                                            </th>
                                            <th
                                                style={{ padding: '10px 14px' }}
                                            >
                                                Subjek
                                            </th>
                                            <th
                                                style={{ padding: '10px 22px' }}
                                            >
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {logs.map((row, i) => (
                                            <tr
                                                key={i}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                    color: C.text,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        padding: '11px 22px',
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    {row.date ?? '-'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '11px 14px',
                                                    }}
                                                >
                                                    {row.to}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '11px 14px',
                                                    }}
                                                >
                                                    {row.subject ?? '-'}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '11px 22px',
                                                    }}
                                                >
                                                    {statusBadge(row.status)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
