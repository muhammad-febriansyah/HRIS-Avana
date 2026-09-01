import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import { DataTable, type DataTableMeta } from '@/components/avana-ui/data-table';
import { AIcon, btnOut, C, hexA } from '@/lib/avana';
import { EmptyState, PageShell, Panel } from '@/pages/avana/saya/components';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { destroy as destroyDevice } from '@/routes/security/devices';
import {
    destroy as destroySession,
    destroyOthers as destroyOtherSessions,
} from '@/routes/security/sessions';

type Session = {
    id: string;
    label: string;
    ip_address: string | null;
    last_active_at: string;
    last_active_diff: string;
    is_current: boolean;
};

type Device = {
    id: number;
    label: string | null;
    channel: string;
    ip_address: string | null;
    login_count: number;
    first_seen_at: string | null;
    last_seen_at: string | null;
    last_seen_diff: string | null;
    revoked: boolean;
};

type LoginEvent = {
    id: number;
    event: string;
    description: string | null;
    ip_address: string | null;
    device: string;
    created_at: string | null;
};

type PaginatedLoginHistory = {
    data: LoginEvent[];
    meta: DataTableMeta;
    search: string;
};

type Props = {
    passwordRules: string;
    sessionsAvailable: boolean;
    sessions: Session[];
    devices: Device[];
    loginHistory: PaginatedLoginHistory;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const EVENT_LABEL: Record<string, { text: string; tone: string }> = {
    login: { text: 'Login berhasil', tone: C.green },
    logout: { text: 'Logout', tone: C.muted },
    login_failed: { text: 'Login gagal', tone: C.amber },
    login_lockout: { text: 'Akun dikunci', tone: C.red },
    login_new_device: { text: 'Perangkat baru', tone: C.violet },
};

const rowStyle: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 14,
    padding: '12px 0',
    borderBottom: `1px solid ${C.border}`,
};

function Tag({ text, tone }: { text: string; tone: string }) {
    return (
        <span
            style={{
                fontSize: 11,
                fontWeight: 600,
                color: tone,
                background: hexA(tone, 0.12),
                borderRadius: 999,
                padding: '3px 9px',
                whiteSpace: 'nowrap',
            }}
        >
            {text}
        </span>
    );
}

function Meta({ children }: { children: React.ReactNode }) {
    return (
        <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3 }}>
            {children}
        </div>
    );
}

export default function Security(props: Props) {
    const [busy, setBusy] = useState<string | null>(null);

    const otherSessions = props.sessions.filter((s) => !s.is_current).length;

    const revokeSession = (session: Session) => {
        if (!confirm(`Akhiri sesi di ${session.label}?`)) {
            return;
        }

        setBusy(`session-${session.id}`);
        router.delete(destroySession(session.id).url, {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    const revokeOthers = () => {
        if (!confirm('Akhiri semua sesi lain selain perangkat ini?')) {
            return;
        }

        setBusy('others');
        router.delete(destroyOtherSessions().url, {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    const revokeDevice = (device: Device) => {
        if (!confirm(`Cabut akses perangkat ${device.label ?? 'ini'}?`)) {
            return;
        }

        setBusy(`device-${device.id}`);
        router.delete(destroyDevice(device.id).url, {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    return (
        <PageShell>
            <Head title="Keamanan Akun" />

            <div style={{ marginBottom: 22 }}>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: 0,
                        letterSpacing: '-.01em',
                    }}
                >
                    Keamanan Akun
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                    Verifikasi dua langkah, perangkat, dan riwayat login akun
                    ini
                </div>
            </div>

            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 18,
                }}
            >
                {props.canManageTwoFactor && (
                    <Panel title="Verifikasi Dua Langkah">
                        <ManageTwoFactor
                            canManageTwoFactor={props.canManageTwoFactor}
                            twoFactorEnabled={props.twoFactorEnabled}
                            requiresConfirmation={props.requiresConfirmation}
                            twoFactorQrCodeSvg={props.twoFactorQrCodeSvg}
                            twoFactorSecretKey={props.twoFactorSecretKey}
                            twoFactorRecoveryCodes={
                                props.twoFactorRecoveryCodes
                            }
                        />
                    </Panel>
                )}

                {props.canManagePasskeys && (
                    <Panel title="Passkey">
                        <ManagePasskeys
                            canManagePasskeys={props.canManagePasskeys}
                            passkeys={props.passkeys}
                        />
                    </Panel>
                )}

                <Panel
                    title="Sesi Aktif"
                    subtitle="Browser yang sedang masuk memakai akun ini"
                    action={
                        otherSessions > 0 ? (
                            <button
                                type="button"
                                onClick={revokeOthers}
                                disabled={busy === 'others'}
                                style={{
                                    ...btnOut,
                                    color: C.red,
                                    borderColor: hexA(C.red, 0.35),
                                }}
                            >
                                <AIcon name="log-out" size={14} />
                                Akhiri {otherSessions} sesi lain
                            </button>
                        ) : undefined
                    }
                >
                    {!props.sessionsAvailable ? (
                        <div style={{ fontSize: 13, color: C.muted }}>
                            Daftar sesi tidak tersedia karena sesi tidak
                            disimpan di database (SESSION_DRIVER bukan
                            "database").
                        </div>
                    ) : props.sessions.length === 0 ? (
                        <EmptyState
                            icon="monitor"
                            message="Belum ada sesi tercatat. Sesi akan muncul setelah login berikutnya."
                        />
                    ) : (
                        <div>
                            {props.sessions.map((session, index) => (
                                <div
                                    key={session.id}
                                    style={{
                                        ...rowStyle,
                                        borderBottom:
                                            index === props.sessions.length - 1
                                                ? 'none'
                                                : rowStyle.borderBottom,
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                            }}
                                        >
                                            {session.label}
                                            {session.is_current && (
                                                <Tag
                                                    text="Perangkat ini"
                                                    tone={C.green}
                                                />
                                            )}
                                        </div>
                                        <Meta>
                                            {session.ip_address ?? 'IP tidak diketahui'}{' '}
                                            · aktif {session.last_active_diff}
                                        </Meta>
                                    </div>

                                    {!session.is_current && (
                                        <button
                                            type="button"
                                            onClick={() => revokeSession(session)}
                                            disabled={
                                                busy === `session-${session.id}`
                                            }
                                            style={{
                                                ...btnOut,
                                                color: C.red,
                                                borderColor: hexA(C.red, 0.35),
                                            }}
                                        >
                                            Akhiri
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>

                <Panel
                    title="Perangkat Dikenal"
                    subtitle="Setiap browser dan ponsel yang pernah dipakai masuk ke akun ini"
                >
                    {props.devices.length === 0 ? (
                        <EmptyState
                            icon="smartphone"
                            message="Belum ada perangkat tercatat. Perangkat tercatat sejak login berikutnya."
                        />
                    ) : (
                        <div>
                            {props.devices.map((device, index) => (
                                <div
                                    key={device.id}
                                    style={{
                                        ...rowStyle,
                                        opacity: device.revoked ? 0.55 : 1,
                                        borderBottom:
                                            index === props.devices.length - 1
                                                ? 'none'
                                                : rowStyle.borderBottom,
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                            }}
                                        >
                                            {device.label ?? 'Perangkat'}
                                            <Tag
                                                text={
                                                    device.channel === 'mobile'
                                                        ? 'Aplikasi'
                                                        : 'Web'
                                                }
                                                tone={
                                                    device.channel === 'mobile'
                                                        ? C.violet
                                                        : C.primary
                                                }
                                            />
                                            {device.revoked && (
                                                <Tag
                                                    text="Dicabut"
                                                    tone={C.red}
                                                />
                                            )}
                                        </div>
                                        <Meta>
                                            {device.login_count}× login ·
                                            terakhir{' '}
                                            {device.last_seen_diff ?? '-'} ·{' '}
                                            {device.ip_address ?? 'IP tidak diketahui'}
                                        </Meta>
                                    </div>

                                    {!device.revoked && (
                                        <button
                                            type="button"
                                            onClick={() => revokeDevice(device)}
                                            disabled={
                                                busy === `device-${device.id}`
                                            }
                                            style={{
                                                ...btnOut,
                                                color: C.red,
                                                borderColor: hexA(C.red, 0.35),
                                            }}
                                        >
                                            Cabut
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>

                <Panel
                    title="Riwayat Login"
                    subtitle="Aktivitas masuk akun, termasuk percobaan yang gagal"
                >
                    <DataTable<LoginEvent>
                        columns={[
                            {
                                key: 'event',
                                header: 'Aktivitas',
                                sortable: false,
                                render: (row) => {
                                    const meta = EVENT_LABEL[row.event] ?? {
                                        text: row.event,
                                        tone: C.muted,
                                    };

                                    return <Tag text={meta.text} tone={meta.tone} />;
                                },
                            },
                            {
                                key: 'device',
                                header: 'Perangkat',
                                sortable: false,
                            },
                            {
                                key: 'created_at',
                                header: 'Waktu',
                                sortable: false,
                            },
                            {
                                key: 'ip_address',
                                header: 'IP Address',
                                sortable: false,
                                render: (row) => row.ip_address ?? 'Tidak diketahui',
                            },
                        ]}
                        rows={props.loginHistory.data}
                        meta={props.loginHistory.meta}
                        filters={{ search: props.loginHistory.search }}
                        searchPlaceholder="Cari aktivitas, perangkat, atau IP…"
                        rowKey={(row) => row.id}
                        emptyState={
                            <EmptyState
                                icon="history"
                                message="Belum ada riwayat yang sesuai."
                            />
                        }
                    />
                </Panel>

                <Panel
                    title="Ubah Kata Sandi"
                    subtitle="Formulirnya ada di halaman Edit Profil"
                >
                    <Link href={edit()} style={btnOut}>
                        <AIcon name="user" size={14} />
                        Buka Edit Profil
                    </Link>
                </Panel>
            </div>
        </PageShell>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Keamanan Akun',
            href: editSecurity(),
        },
    ],
};
