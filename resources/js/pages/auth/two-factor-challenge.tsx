import { Form, Head } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/two-factor/login';

const fieldClass =
    'h-11 w-full rounded-lg border border-[#E5E9F2] px-3.5 text-sm text-[#1A2333] outline-none transition focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/15';
const labelClass = 'mb-1.5 block text-[13px] font-medium text-[#1A2333]';

export default function TwoFactorChallenge() {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);

    return (
        <>
            <Head title="Verifikasi dua langkah" />

            <Form
                // The two inputs post to the same endpoint, so clearing the one
                // being hidden keeps a stale value from deciding the attempt.
                key={useRecoveryCode ? 'recovery' : 'code'}
                {...store.form()}
                resetOnError
                className="flex flex-col"
            >
                {({ processing, errors }) => (
                    <>
                        {useRecoveryCode ? (
                            <div className="mb-6">
                                <label
                                    htmlFor="recovery_code"
                                    className={labelClass}
                                >
                                    Kode Pemulihan
                                </label>
                                <input
                                    id="recovery_code"
                                    type="text"
                                    name="recovery_code"
                                    required
                                    autoFocus
                                    autoComplete="one-time-code"
                                    placeholder="xxxxxxxxxx-xxxxxxxxxx"
                                    className={fieldClass}
                                />
                                <InputError
                                    message={errors.recovery_code}
                                    className="mt-1.5"
                                />
                            </div>
                        ) : (
                            <div className="mb-6">
                                <label htmlFor="code" className={labelClass}>
                                    Kode Autentikasi
                                </label>
                                <input
                                    id="code"
                                    type="text"
                                    name="code"
                                    required
                                    autoFocus
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    maxLength={6}
                                    autoComplete="one-time-code"
                                    placeholder="000000"
                                    className={`${fieldClass} text-center text-lg tracking-[0.5em]`}
                                />
                                <InputError
                                    message={errors.code}
                                    className="mt-1.5"
                                />
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            data-test="two-factor-challenge-button"
                            className="flex h-[46px] w-full items-center justify-center gap-2 rounded-lg bg-[#2F54C9] text-[15px] font-semibold text-white transition hover:bg-[#2546ad] disabled:opacity-70"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <ShieldCheck size={18} />
                            )}
                            Verifikasi
                        </button>
                    </>
                )}
            </Form>

            <button
                type="button"
                onClick={() => setUseRecoveryCode((previous) => !previous)}
                className="mt-6 flex w-full items-center justify-center gap-2 text-[13px] font-medium text-[#2F54C9] hover:underline"
            >
                <KeyRound size={15} />
                {useRecoveryCode
                    ? 'Gunakan kode dari aplikasi autentikator'
                    : 'Gunakan kode pemulihan'}
            </button>
        </>
    );
}

TwoFactorChallenge.layout = {
    title: 'Verifikasi dua langkah',
    description:
        'Masukkan kode dari aplikasi autentikator Anda untuk menyelesaikan proses masuk.',
};
