import { Head, Link } from '@inertiajs/react';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import { AIcon, btnOut, C } from '@/lib/avana';
import { PageShell, Panel } from '@/pages/avana/saya/components';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
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
                    Verifikasi dua langkah dan passkey untuk akun ini
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
