import { Link } from '@inertiajs/react';
import { BadgeCheck, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Split shell for the "Daftar Perusahaan" entry point — form on the left,
 * a navy proof panel on the right (hidden below 860px via the shared
 * `.login-hero` rule in app.css, the same breakpoint the auth pages hide
 * their own hero at).
 *
 * The right panel's second floating card is the one thing that changes with
 * context: a referred visitor sees who vouched for them (their one signal
 * that this isn't a cold, generic sign-up); everyone else sees the same
 * "verified clock-in" proof the login screen shows, since there's no
 * partner to name yet.
 */
export function CompanyRegisterShell({
    brand,
    logo,
    eyebrow,
    title,
    description,
    partnerCode,
    children,
}: {
    brand: string;
    logo: string;
    eyebrow: string;
    title: string;
    description: string;
    /** Present only when a valid referral cookie resolved a partner. */
    partnerCode?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh bg-white font-sans text-[#1A2333] antialiased">
            <style>{`
                @keyframes avn-reg-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
                @keyframes avn-reg-drift { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
                .avn-reg-form { animation: avn-reg-rise .45s ease-out both; }
                .avn-reg-float { animation: avn-reg-drift 7s ease-in-out infinite; }
                .avn-reg-float2 { animation: avn-reg-drift 9s ease-in-out 1.2s infinite; }
                @media (prefers-reduced-motion: reduce) {
                    .avn-reg-form, .avn-reg-float, .avn-reg-float2 { animation: none; }
                }
            `}</style>

            {/* LEFT — form */}
            <div className="flex flex-1 items-center justify-center px-6 py-10 sm:px-10">
                <div className="avn-reg-form w-full max-w-[420px]">
                    <div className="mb-9 flex items-center justify-between">
                        <img src={logo} alt={brand} className="h-9 w-auto" />
                        <Link href="/login" className="text-[13px] font-medium text-[#5B6478] hover:text-[#2F54C9]">
                            Masuk →
                        </Link>
                    </div>

                    <span className="inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-[#F8FAFD] px-3.5 py-1.5 text-[11.5px] font-bold tracking-[0.08em] text-[#2F54C9] uppercase">
                        {eyebrow}
                    </span>
                    <h1 className="mt-4 text-[26px] leading-[1.15] font-extrabold tracking-[-0.02em] text-[#0E1A3A] sm:text-[30px]">{title}</h1>
                    <p className="mt-3 text-[14px] leading-relaxed text-[#5B6478]">{description}</p>

                    {partnerCode && (
                        <div className="mt-5 flex items-center gap-2.5 rounded-xl border border-[#DCFCE7] bg-[#F0FDF4] px-4 py-3">
                            <BadgeCheck className="h-4 w-4 flex-none text-[#16A34A]" />
                            <p className="text-[13px] text-[#166534]">
                                Diundang oleh mitra{' '}
                                <span className="font-mono font-bold tracking-wide">{partnerCode}</span>
                            </p>
                        </div>
                    )}

                    <div className="mt-7">{children}</div>

                    <p className="mt-10 text-center text-[12.5px] text-[#9AA4B8]">© 2026 AvanaHR · Advancing People, Empowering Growth</p>
                </div>
            </div>

            {/* RIGHT — proof panel */}
            <div
                className="login-hero relative hidden flex-1 flex-col justify-center overflow-hidden px-16 lg:flex"
                style={{ background: 'linear-gradient(150deg,#0E1A3A 0%,#1c3175 55%,#2F54C9 100%)' }}
            >
                <div className="absolute -top-20 -right-16 h-80 w-80 rounded-full bg-[#6E9BE6]/[.18]" />
                <div className="absolute -bottom-24 -left-20 h-90 w-90 rounded-full bg-[#6E9BE6]/[.10]" />

                {/* Slip Gaji — always shown: the one artifact every AvanaHR client recognizes. */}
                <div
                    className="avn-reg-float absolute top-12 right-14 w-[250px] rounded-[14px] border border-white/[.18] bg-white/[.08] p-4 text-white shadow-[0_18px_40px_rgba(5,10,30,.35)] backdrop-blur-[14px]"
                    aria-hidden
                >
                    <div className="mb-2.5 flex items-center justify-between">
                        <span className="text-[12px] font-semibold">Slip Gaji · Juni</span>
                        <span className="rounded-full bg-[#4ADE80]/[.18] px-2 py-0.5 text-[10.5px] font-semibold text-[#86EFAC]">Terbayar</span>
                    </div>
                    {[
                        ['Gaji Pokok', 'Rp 10.000.000'],
                        ['Lembur', 'Rp 854.186'],
                        ['PPh 21', '- Rp 522.907'],
                    ].map(([k, v]) => (
                        <div key={k} className="flex justify-between py-[3px] text-[11.5px] text-white/75">
                            <span>{k}</span>
                            <span className="tabular-nums">{v}</span>
                        </div>
                    ))}
                    <div className="mt-2 flex justify-between border-t border-white/15 pt-2 text-[12.5px] font-bold">
                        <span>Take Home Pay</span>
                        <span className="tabular-nums">Rp 9.939.995</span>
                    </div>
                </div>

                {/* Second card: referral proof when there's a partner to name, otherwise the same clock-in proof login shows. */}
                <div
                    className="avn-reg-float2 absolute right-14 bottom-12 flex items-center gap-2.5 rounded-[14px] border border-white/[.18] bg-white/[.08] px-4 py-3 text-white shadow-[0_18px_40px_rgba(5,10,30,.35)] backdrop-blur-[14px]"
                    aria-hidden
                >
                    <span className="flex h-[34px] w-[34px] flex-none items-center justify-center rounded-full bg-[#4ADE80]/[.16]">
                        <BadgeCheck className="h-[18px] w-[18px] text-[#86EFAC]" />
                    </span>
                    {partnerCode ? (
                        <span>
                            <span className="block text-[12.5px] font-semibold">Referral terverifikasi</span>
                            <span className="mt-0.5 block font-mono text-[11px] text-white/65">{partnerCode}</span>
                        </span>
                    ) : (
                        <span>
                            <span className="block text-[12.5px] font-semibold">Clock-in 07:58 · Wajah cocok</span>
                            <span className="mt-0.5 block text-[11px] text-white/65">Dalam area kantor</span>
                        </span>
                    )}
                </div>

                <div className="relative max-w-[440px] text-white">
                    <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-white/[.16] bg-white/10 px-3.5 py-1.5 text-[12.5px] font-medium">
                        <Sparkles className="h-3.5 w-3.5 text-[#6E9BE6]" />
                        Platform HRIS / HCM Multi-tenant
                    </div>
                    <div className="text-[36px] leading-[1.18] font-bold tracking-[-0.02em]">Satu platform untuk seluruh siklus karyawan Anda.</div>
                    <p className="mt-5 text-[15px] leading-[1.7] text-white/70">
                        Dari rekrutmen, absensi berbasis GPS, pengajuan cuti, hingga payroll &amp; slip gaji — semua terintegrasi dan sesuai
                        regulasi Indonesia.
                    </p>
                    <div className="mt-11 flex gap-9">
                        {[
                            ['12.400+', 'Karyawan dikelola'],
                            ['98,7%', 'Akurasi payroll'],
                            ['320+', 'Perusahaan'],
                        ].map(([n, l]) => (
                            <div key={l}>
                                <div className="text-[27px] font-bold">{n}</div>
                                <div className="mt-0.5 text-[13px] text-white/60">{l}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
