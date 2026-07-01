import { Form, Head, Link, router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound, Lock, LogIn, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const fieldClass =
    'h-11 w-full rounded-lg border border-[#E5E9F2] pr-3.5 pl-10 text-sm text-[#1A2333] outline-none transition focus:border-[#2F54C9] focus:ring-2 focus:ring-[#2F54C9]/15';
const labelClass = 'mb-1.5 block text-[13px] font-medium text-[#1A2333]';

export default function Login({ status, canResetPassword }: Props) {
    const { verify, isLoading: passkeyLoading, isSupported: passkeySupported } = usePasskeyVerify({
        onSuccess: (response) => router.visit(response.redirect ?? '/dashboard'),
    });

    // Passkey login is hidden for now. Flip to `true` to bring the button back.
    const showPasskeyLogin = false;

    return (
        <>
            <Head title="Masuk" />

            {status && <div className="mb-4 rounded-lg bg-green-50 px-4 py-2.5 text-center text-sm font-medium text-green-700">{status}</div>}

            <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col">
                {({ processing, errors }) => (
                    <>
                        <div className="mb-[18px]">
                            <label htmlFor="email" className={labelClass}>
                                Email <span className="text-[#DC2626]">*</span>
                            </label>
                            <div className="relative">
                                <Mail size={17} className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#9CA3AF]" />
                                <input id="email" type="email" name="email" required autoFocus tabIndex={1} autoComplete="email" placeholder="nama@perusahaan.co.id" className={fieldClass} />
                            </div>
                            <InputError message={errors.email} className="mt-1.5" />
                        </div>

                        <div className="mb-3.5">
                            <label htmlFor="password" className={labelClass}>
                                Kata Sandi <span className="text-[#DC2626]">*</span>
                            </label>
                            <div className="relative">
                                <Lock size={17} className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#9CA3AF]" />
                                <input id="password" type="password" name="password" required tabIndex={2} autoComplete="current-password" placeholder="Masukkan kata sandi" className={fieldClass} />
                            </div>
                            <InputError message={errors.password} className="mt-1.5" />
                        </div>

                        <div className="mb-6 flex items-center justify-between">
                            <label className="flex cursor-pointer items-center gap-2 text-[13px] text-[#6B7280]">
                                <input type="checkbox" name="remember" tabIndex={3} className="h-[15px] w-[15px] accent-[#2F54C9]" />
                                Ingat saya
                            </label>
                            {canResetPassword && (
                                <Link href={request()} tabIndex={5} className="text-[13px] font-medium text-[#2F54C9] hover:underline">
                                    Lupa sandi?
                                </Link>
                            )}
                        </div>

                        <button
                            type="submit"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                            className="flex h-[46px] w-full items-center justify-center gap-2 rounded-lg bg-[#2F54C9] text-[15px] font-semibold text-white transition hover:bg-[#2546ad] disabled:opacity-70"
                        >
                            {processing ? <Spinner /> : <LogIn size={18} />}
                            Masuk
                        </button>
                    </>
                )}
            </Form>

            {showPasskeyLogin && passkeySupported && (
                <>
                    <div className="my-6 flex items-center gap-3.5 text-xs text-[#9CA3AF]">
                        <div className="h-px flex-1 bg-[#E5E9F2]" />
                        atau
                        <div className="h-px flex-1 bg-[#E5E9F2]" />
                    </div>
                    <button
                        type="button"
                        onClick={verify}
                        disabled={passkeyLoading}
                        className="flex h-[46px] w-full items-center justify-center gap-2.5 rounded-lg border border-[#E5E9F2] bg-white text-sm font-medium text-[#1A2333] transition hover:border-[#2F54C9] hover:bg-[#F4F6FB] disabled:opacity-70"
                    >
                        {passkeyLoading ? <Spinner /> : <KeyRound size={17} className="text-[#2F54C9]" />}
                        Masuk dengan Passkey
                    </button>
                </>
            )}

            <div className="mt-7 text-center text-[13px] text-[#6B7280]">
                Belum punya akun?{' '}
                <Link href={register()} tabIndex={5} className="font-medium text-[#2F54C9] hover:underline">
                    Daftar
                </Link>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Masuk ke akun Anda',
    description: 'Kelola karyawan, absensi, dan payroll dalam satu platform.',
};
