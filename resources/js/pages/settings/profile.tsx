import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import {
    Field,
    inputStyle,
    PageShell,
    Panel,
    withError,
} from '@/pages/avana/saya/components';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    employeeName: string | null;
    phone: string | null;
    avatarUrl: string | null;
    hasOwnAvatar: boolean;
    securityUnlocked: boolean;
} & Partial<ManagePasskeysProps> &
    Partial<ManageTwoFactorProps>;

const profileTabs = [
    { id: 'profil', label: 'Profil', icon: 'user' },
    { id: 'keamanan', label: 'Keamanan', icon: 'shield-check' },
] as const;

/** Initials fallback shown while the account has no photo. */
function initialsOf(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function Profile(props: Props) {
    const {
        mustVerifyEmail,
        status,
        employeeName,
        phone,
        avatarUrl,
        hasOwnAvatar,
        securityUnlocked,
    } = props;

    const { auth } = usePage<PageProps>().props;
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [activeTab, setActiveTab] = useState<string>('profil');

    const displayName = employeeName ?? auth.user.name;

    const submitPhoto = (file: File) => {
        setUploading(true);
        router.post(
            ProfileController.updateAvatar.url(),
            { avatar: file },
            {
                preserveScroll: true,
                forceFormData: true,
                onFinish: () => setUploading(false),
            },
        );
    };

    const removePhoto = () => {
        setUploading(true);
        router.post(
            ProfileController.updateAvatar.url(),
            { remove: true },
            { preserveScroll: true, onFinish: () => setUploading(false) },
        );
    };

    return (
        <PageShell>
            <Head title="Edit Profil" />

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
                    Edit Profil
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                    Data akun yang Anda pakai untuk masuk ke AvanaHR
                </div>
            </div>

            <div
                style={{
                    borderBottom: `1px solid ${C.border}`,
                    marginBottom: 20,
                    display: 'flex',
                    overflowX: 'auto',
                }}
            >
                {profileTabs.map((tab) => {
                    const active = activeTab === tab.id;

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setActiveTab(tab.id)}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                padding: '12px 4px',
                                marginRight: 26,
                                fontSize: 13.5,
                                fontWeight: active ? 600 : 500,
                                color: active ? C.primary : C.muted,
                                border: 'none',
                                cursor: 'pointer',
                                background: active
                                    ? 'rgba(47,84,201,.07)'
                                    : C.surface,
                                borderRadius: 8,
                                whiteSpace: 'nowrap',
                                transition: '.15s',
                            }}
                        >
                            <AIcon name={tab.icon} size={15} />
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 18,
                }}
            >
                {activeTab === 'profil' && (
                    <>
                        <Panel
                            title="Foto Profil"
                            subtitle="Tampil di pojok kanan atas dan di daftar pengguna"
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 18,
                                    flexWrap: 'wrap',
                                }}
                            >
                                {avatarUrl ? (
                                    <img
                                        src={avatarUrl}
                                        alt={displayName}
                                        style={{
                                            width: 72,
                                            height: 72,
                                            borderRadius: '50%',
                                            objectFit: 'cover',
                                            border: `1px solid ${C.border}`,
                                        }}
                                    />
                                ) : (
                                    <div
                                        style={{
                                            width: 72,
                                            height: 72,
                                            borderRadius: '50%',
                                            background: C.primary,
                                            color: '#fff',
                                            fontSize: 24,
                                            fontWeight: 600,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        {initialsOf(displayName)}
                                    </div>
                                )}

                                <div style={{ display: 'flex', gap: 9 }}>
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        accept="image/*"
                                        hidden
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0];

                                            if (file) {
                                                submitPhoto(file);
                                            }

                                            event.target.value = '';
                                        }}
                                    />

                                    <button
                                        type="button"
                                        style={btnOut}
                                        disabled={uploading}
                                        onClick={() =>
                                            fileInput.current?.click()
                                        }
                                    >
                                        <AIcon name="upload" size={14} />
                                        {uploading
                                            ? 'Mengunggah...'
                                            : 'Ganti Foto'}
                                    </button>

                                    {hasOwnAvatar && (
                                        <button
                                            type="button"
                                            style={{ ...btnOut, color: C.red }}
                                            disabled={uploading}
                                            onClick={removePhoto}
                                        >
                                            Hapus Foto
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.faint,
                                    marginTop: 12,
                                }}
                            >
                                Format gambar, maksimal 2 MB.
                                {!hasOwnAvatar &&
                                    avatarUrl !== null &&
                                    ' Saat ini memakai foto dari data karyawan.'}
                            </div>
                        </Panel>

                        <Panel
                            title="Data Akun"
                            subtitle="Nama, email dan nomor telepon akun ini"
                        >
                            <Form
                                {...ProfileController.update.form()}
                                options={{ preserveScroll: true }}
                                className="space-y-5"
                            >
                                {({ processing, errors }) => (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 16,
                                        }}
                                    >
                                        <Field
                                            label="Nama"
                                            required
                                            error={errors.name}
                                            hint={
                                                employeeName !== null
                                                    ? 'Nama mengikuti data karyawan. Hubungi HR bila perlu diubah.'
                                                    : undefined
                                            }
                                        >
                                            <input
                                                name="name"
                                                defaultValue={displayName}
                                                required
                                                // Read-only, not disabled: a disabled
                                                // field posts nothing and the required
                                                // rule would reject the save.
                                                readOnly={employeeName !== null}
                                                placeholder="Nama lengkap"
                                                style={{
                                                    ...withError(
                                                        inputStyle,
                                                        Boolean(errors.name),
                                                    ),
                                                    ...(employeeName !== null
                                                        ? {
                                                              background:
                                                                  C.surface,
                                                              color: C.muted,
                                                          }
                                                        : {}),
                                                }}
                                            />
                                        </Field>

                                        <Field
                                            label="Email"
                                            required
                                            error={errors.email}
                                        >
                                            <input
                                                type="email"
                                                name="email"
                                                defaultValue={auth.user.email}
                                                required
                                                autoComplete="username"
                                                placeholder="nama@perusahaan.co.id"
                                                style={withError(
                                                    inputStyle,
                                                    Boolean(errors.email),
                                                )}
                                            />
                                        </Field>

                                        <Field
                                            label="No. Telepon"
                                            error={errors.phone}
                                            hint="Dipakai HR untuk menghubungi Anda."
                                        >
                                            <input
                                                name="phone"
                                                defaultValue={phone ?? ''}
                                                placeholder="cth. 0812xxxxxxx"
                                                style={withError(
                                                    inputStyle,
                                                    Boolean(errors.phone),
                                                )}
                                            />
                                        </Field>

                                        {mustVerifyEmail &&
                                            auth.user.email_verified_at ===
                                                null && (
                                                <div
                                                    style={{
                                                        fontSize: 12.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    Email ini belum
                                                    diverifikasi.{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        style={{
                                                            color: C.primary,
                                                            textDecoration:
                                                                'underline',
                                                        }}
                                                    >
                                                        Kirim ulang email
                                                        verifikasi.
                                                    </Link>
                                                    {status ===
                                                        'verification-link-sent' && (
                                                        <div
                                                            style={{
                                                                color: C.green,
                                                                marginTop: 6,
                                                            }}
                                                        >
                                                            Tautan verifikasi
                                                            baru sudah dikirim
                                                            ke email Anda.
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                        <div>
                                            <button
                                                type="submit"
                                                style={btnP}
                                                disabled={processing}
                                                data-test="update-profile-button"
                                            >
                                                <AIcon name="check" size={14} />
                                                Simpan
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </Panel>
                    </>
                )}

                {activeTab === 'keamanan' && (
                    <>
                        <Panel
                            title="Kata Sandi"
                            subtitle="Pakai kata sandi yang panjang dan acak agar akun tetap aman"
                        >
                            <Form
                                {...SecurityController.update.form()}
                                options={{ preserveScroll: true }}
                                resetOnError={[
                                    'password',
                                    'password_confirmation',
                                    'current_password',
                                ]}
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 16,
                                        }}
                                    >
                                        <Field
                                            label="Kata sandi saat ini"
                                            required
                                            error={errors.current_password}
                                        >
                                            <input
                                                type="password"
                                                name="current_password"
                                                autoComplete="current-password"
                                                placeholder="Kata sandi yang dipakai sekarang"
                                                style={withError(
                                                    inputStyle,
                                                    Boolean(
                                                        errors.current_password,
                                                    ),
                                                )}
                                            />
                                        </Field>

                                        <Field
                                            label="Kata sandi baru"
                                            required
                                            error={errors.password}
                                        >
                                            <input
                                                type="password"
                                                name="password"
                                                autoComplete="new-password"
                                                placeholder="Minimal 8 karakter"
                                                style={withError(
                                                    inputStyle,
                                                    Boolean(errors.password),
                                                )}
                                            />
                                        </Field>

                                        <Field
                                            label="Konfirmasi kata sandi baru"
                                            required
                                            error={errors.password_confirmation}
                                        >
                                            <input
                                                type="password"
                                                name="password_confirmation"
                                                autoComplete="new-password"
                                                placeholder="Ulangi kata sandi baru"
                                                style={withError(
                                                    inputStyle,
                                                    Boolean(
                                                        errors.password_confirmation,
                                                    ),
                                                )}
                                            />
                                        </Field>

                                        <div>
                                            <button
                                                type="submit"
                                                style={btnP}
                                                disabled={processing}
                                                data-test="update-password-button"
                                            >
                                                <AIcon name="check" size={14} />
                                                Simpan
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </Panel>

                        {securityUnlocked ? (
                            <>
                                {props.canManageTwoFactor && (
                                    <Panel title="Verifikasi Dua Langkah">
                                        <ManageTwoFactor
                                            canManageTwoFactor={
                                                props.canManageTwoFactor
                                            }
                                            twoFactorEnabled={
                                                props.twoFactorEnabled
                                            }
                                            requiresConfirmation={
                                                props.requiresConfirmation
                                            }
                                            twoFactorQrCodeSvg={
                                                props.twoFactorQrCodeSvg
                                            }
                                            twoFactorSecretKey={
                                                props.twoFactorSecretKey
                                            }
                                            twoFactorRecoveryCodes={
                                                props.twoFactorRecoveryCodes
                                            }
                                        />
                                    </Panel>
                                )}

                                {props.canManagePasskeys && (
                                    <Panel title="Passkey">
                                        <ManagePasskeys
                                            canManagePasskeys={
                                                props.canManagePasskeys
                                            }
                                            passkeys={props.passkeys}
                                        />
                                    </Panel>
                                )}
                            </>
                        ) : (
                            <Panel
                                title="Verifikasi Dua Langkah & Passkey"
                                subtitle="Perlu konfirmasi kata sandi sebelum ditampilkan"
                            >
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: C.muted,
                                        marginBottom: 14,
                                    }}
                                >
                                    Kode QR dan kode pemulihan hanya dikirim ke
                                    sesi yang baru saja mengonfirmasi kata
                                    sandinya.
                                </div>

                                <Link href={editSecurity()} style={btnOut}>
                                    <AIcon name="shield-check" size={14} />
                                    Buka Pengaturan Keamanan
                                </Link>
                            </Panel>
                        )}
                    </>
                )}
            </div>
        </PageShell>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Edit Profil',
            href: edit(),
        },
    ],
};
