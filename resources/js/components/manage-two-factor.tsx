import { Form, router } from '@inertiajs/react';
import { Check, Copy, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    confirm,
    disable,
    enable,
    regenerateRecoveryCodes,
} from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    twoFactorEnabled?: boolean;
    requiresConfirmation?: boolean;
    twoFactorQrCodeSvg?: string | null;
    twoFactorSecretKey?: string | null;
    twoFactorRecoveryCodes?: string[];
};

const RecoveryCodes = ({ codes }: { codes: string[] }) => {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(codes.join('\n')).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <div className="space-y-3 rounded-lg border border-border bg-muted/50 p-4">
            <div className="flex items-start justify-between gap-4">
                <p className="text-sm text-muted-foreground">
                    Simpan di tempat aman. Tiap kode bisa dipakai sekali untuk
                    masuk bila Anda kehilangan akses ke aplikasi authenticator.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleCopy}
                >
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Tersalin' : 'Salin'}
                </Button>
            </div>

            <ul className="grid gap-1 font-mono text-sm sm:grid-cols-2">
                {codes.map((code) => (
                    <li key={code}>{code}</li>
                ))}
            </ul>
        </div>
    );
};

export default function ManageTwoFactor(props: Props) {
    const [showRecoveryCodes, setShowRecoveryCodes] = useState(false);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    const enabled = props.twoFactorEnabled ?? false;

    // Fortify writes the secret the moment enrolment starts, so its presence
    // while the account is still unprotected is what marks a half-finished
    // setup waiting on a code.
    const awaitingConfirmation = !enabled && Boolean(props.twoFactorSecretKey);

    const recoveryCodes = props.twoFactorRecoveryCodes ?? [];

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Verifikasi dua langkah"
                description="Tambah kode sekali pakai dari aplikasi authenticator di atas kata sandi"
            />

            {enabled && (
                <div className="space-y-6">
                    <div className="flex items-center gap-2 text-sm font-medium text-emerald-600">
                        <ShieldCheck className="h-4 w-4" />
                        Verifikasi dua langkah aktif
                    </div>

                    {showRecoveryCodes && recoveryCodes.length > 0 && (
                        <RecoveryCodes codes={recoveryCodes} />
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setShowRecoveryCodes((previous) => !previous)
                            }
                        >
                            {showRecoveryCodes ? 'Sembunyikan' : 'Tampilkan'}{' '}
                            kode pemulihan
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setShowRecoveryCodes(true);
                                router.post(
                                    regenerateRecoveryCodes.url(),
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            Buat ulang kode pemulihan
                        </Button>

                        <Button
                            type="button"
                            variant="destructive"
                            data-test="disable-two-factor-button"
                            onClick={() =>
                                router.delete(disable.url(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Matikan
                        </Button>
                    </div>
                </div>
            )}

            {awaitingConfirmation && (
                <div className="space-y-6">
                    <div className="space-y-4 rounded-lg border border-border bg-muted/50 p-4">
                        <p className="text-sm text-muted-foreground">
                            Pindai kode ini dengan aplikasi authenticator, lalu
                            masukkan enam digit yang muncul.
                        </p>

                        {props.twoFactorQrCodeSvg && (
                            <div
                                className="inline-block rounded-lg bg-white p-3 [&>svg]:h-40 [&>svg]:w-40"
                                // Fortify renders the QR code itself; the SVG
                                // is built from the secret, not user input.
                                dangerouslySetInnerHTML={{
                                    __html: props.twoFactorQrCodeSvg,
                                }}
                            />
                        )}

                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground">
                                Tidak bisa memindai? Masukkan kunci ini manual:
                            </p>
                            <p className="font-mono text-sm break-all">
                                {props.twoFactorSecretKey}
                            </p>
                        </div>
                    </div>

                    <Form
                        {...confirm.form()}
                        // Fortify throws the "wrong code" error into a named
                        // bag, so the form has to look in the same place.
                        errorBag="confirmTwoFactorAuthentication"
                        options={{ preserveScroll: true }}
                        resetOnError
                        className="space-y-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="code">
                                        Kode dari aplikasi Anda
                                    </Label>

                                    <Input
                                        id="code"
                                        name="code"
                                        inputMode="numeric"
                                        pattern="[0-9]*"
                                        maxLength={6}
                                        autoComplete="one-time-code"
                                        placeholder="000000"
                                        autoFocus
                                        className="max-w-40 border-foreground/20"
                                    />

                                    <InputError message={errors.code} />
                                </div>

                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="confirm-two-factor-button"
                                    >
                                        Konfirmasi dan aktifkan
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(disable.url(), {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Batal
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            )}

            {!enabled && !awaitingConfirmation && (
                <Button
                    type="button"
                    variant="outline"
                    data-test="enable-two-factor-button"
                    onClick={() =>
                        router.post(enable.url(), {}, { preserveScroll: true })
                    }
                >
                    Aktifkan verifikasi dua langkah
                </Button>
            )}
        </div>
    );
}
