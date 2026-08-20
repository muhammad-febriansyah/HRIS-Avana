import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUp,
    Facebook,
    Instagram,
    Linkedin,
    Mail,
    MapPin,
    Music2,
    Phone,
    Youtube,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { liveTracking } from '@/routes';
import { BrandLogo } from './brand-logo';
import { FOOTER_EXPLORE } from './content';
import { Container, Reveal } from './reveal';
import { XIcon } from './share-buttons';
import { useCtaTargets } from './use-cta';

type Social = {
    href: string;
    icon: ComponentType<{ className?: string }>;
    label: string;
};

/** The 8 concrete modules from `ModulesSection`, linked to that section. */
const FOOTER_MODULES = [
    'Core HR',
    'Payroll',
    'Attendance',
    'Leave & Cuti',
    'Recruitment',
    'Performance',
    'AI Intelligence',
    'Workforce Analytics',
];

/** Article categories seeded via `NewsSeeder` — kept in sync manually. */
const FOOTER_INSIGHT = [
    { label: 'Semua Berita', href: '/berita' },
    { label: 'HR Tips', href: '/berita?q=HR+Tips' },
    { label: 'Regulasi', href: '/berita?q=Regulasi' },
    { label: 'Update Produk', href: '/berita?q=Produk' },
];

const LINK_CLASS =
    'inline-block rounded-sm text-[14px] text-blue-100/70 transition-[color,transform] duration-200 hover:translate-x-1 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60';

const SOCIAL_CLASS =
    'grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-blue-200 transition-all duration-200 hover:-translate-y-0.5 hover:border-avana-blue hover:bg-avana-blue hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60';

/** Google Play triangle mark, drawn as a single flat-color path (no gradient assets needed). */
function PlayStoreGlyph({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden className={className}>
            <path
                d="M4.5 2.7c-.4.2-.6.6-.6 1.1v16.4c0 .5.2.9.6 1.1l9.8-9.3z"
                fill="#00D2FF"
            />
            <path d="M17.9 10.4 14.6 8.5 4.7 2.8l9.6 9.2z" fill="#00F076" />
            <path d="M4.7 21.2l9.9-5.7 3.3-1.9-3.6-3.4z" fill="#F73177" />
            <path
                d="M17.9 10.4l-3.6 3.4 3.6 3.4 4.3-2.5c.7-.4.7-1.3 0-1.7z"
                fill="#FFCF00"
            />
        </svg>
    );
}

/** Apple glyph — the official mark, drawn as a single path. */
function AppleGlyph({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden
            className={className}
        >
            <path d="M16.365 1.43c0 1.14-.462 2.157-1.203 2.898-.855.906-2.191 1.615-3.288 1.523-.148-1.086.434-2.256 1.156-2.984.842-.876 2.288-1.523 3.335-1.437ZM20.5 17.02c-.396.914-.582 1.323-1.09 2.135-.712 1.146-1.716 2.575-2.958 2.585-1.104.012-1.39-.719-2.888-.71-1.498.008-1.813.723-2.918.71-1.243-.012-2.194-1.297-2.907-2.443-1.99-3.192-2.2-6.94-.972-8.94.87-1.418 2.245-2.246 3.535-2.246 1.313 0 2.14.72 3.23.72 1.057 0 1.7-.722 3.222-.722 1.148 0 2.364.626 3.23 1.706-2.84 1.556-2.38 5.606.516 7.205Z" />
        </svg>
    );
}

function ColumnHeading({ children }: { children: string }) {
    return (
        <h2 className="text-[12px] font-bold tracking-[0.18em] text-blue-400 uppercase">
            {children}
        </h2>
    );
}

/**
 * Footer.
 *
 * Dark navy footer — brand column with contact + socials, product and page
 * link columns, over a bottom bar carrying the copyright and account
 * actions. Every link points somewhere that exists: landing sections, the
 * login route, and whichever contact channels are actually filled in on the
 * website settings.
 */
export function SiteFooter({
    brand,
    logo,
    anchorPrefix = '',
}: {
    brand: string;
    logo: string;
    /** Feature pages pass "/" so section links jump back to the landing page. */
    anchorPrefix?: string;
}) {
    const { website } = usePage().props;
    const { login: loginUrl, trial, trialExternal } = useCtaTargets();

    const socials = (
        [
            { href: website.social.twitter, icon: XIcon, label: 'X' },
            {
                href: website.social.facebook,
                icon: Facebook,
                label: 'Facebook',
            },
            {
                href: website.social.instagram,
                icon: Instagram,
                label: 'Instagram',
            },
            {
                href: website.social.linkedin,
                icon: Linkedin,
                label: 'LinkedIn',
            },
            { href: website.social.youtube, icon: Youtube, label: 'YouTube' },
            { href: website.social.tiktok, icon: Music2, label: 'TikTok' },
        ] as { href: string | null; icon: Social['icon']; label: string }[]
    ).filter((social): social is Social => Boolean(social.href));

    const hasContact = Boolean(
        website.contact.email ||
        website.contact.phone ||
        website.contact.address,
    );

    return (
        <footer
            id="kontak"
            className="relative scroll-mt-28 overflow-hidden bg-avana-navy text-white"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-avana-blue/60 to-transparent"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute -top-40 left-1/2 h-80 w-[640px] -translate-x-1/2 rounded-full bg-avana-blue/10 blur-[120px]"
            />

            <Container className="relative grid grid-cols-1 gap-x-6 gap-y-12 py-16 sm:grid-cols-2 lg:grid-cols-12 lg:gap-8 lg:py-20">
                <Reveal className="sm:col-span-2 lg:col-span-4">
                    <BrandLogo
                        src={logo}
                        alt={brand}
                        loading="lazy"
                        className="h-9 w-auto rounded-xl bg-white object-contain p-2"
                    />
                    <p className="mt-5 max-w-xs text-[14px] leading-relaxed text-blue-200/80">
                        {website.tagline ??
                            'Platform HR yang menyatukan HR Management, Attendance, Payroll, Finance, Talent, Employee Self Service dan Analytics.'}
                    </p>

                    {hasContact && (
                        <ul className="mt-6 space-y-3 text-[14px] text-blue-100/80">
                            {website.contact.address && (
                                <li className="flex items-start gap-3">
                                    <MapPin
                                        className="mt-0.5 h-4.5 w-4.5 shrink-0 text-avana-blue"
                                        aria-hidden
                                    />
                                    <span className="leading-snug">
                                        {website.contact.address}
                                    </span>
                                </li>
                            )}
                            {website.contact.email && (
                                <li className="flex items-center gap-3">
                                    <Mail
                                        className="h-4.5 w-4.5 shrink-0 text-avana-blue"
                                        aria-hidden
                                    />
                                    <a
                                        href={`mailto:${website.contact.email}`}
                                        className="transition-colors hover:text-white"
                                    >
                                        {website.contact.email}
                                    </a>
                                </li>
                            )}
                            {website.contact.phone && (
                                <li className="flex items-center gap-3">
                                    <Phone
                                        className="h-4.5 w-4.5 shrink-0 text-avana-blue"
                                        aria-hidden
                                    />
                                    <a
                                        href={`tel:${website.contact.phone}`}
                                        className="transition-colors hover:text-white"
                                    >
                                        {website.contact.phone}
                                    </a>
                                </li>
                            )}
                        </ul>
                    )}

                    {socials.length > 0 && (
                        <ul className="mt-7 flex flex-wrap gap-2.5">
                            {socials.map((social) => (
                                <li key={social.label}>
                                    <a
                                        href={social.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={social.label}
                                        className={SOCIAL_CLASS}
                                    >
                                        <social.icon
                                            className="h-4 w-4"
                                            aria-hidden
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </Reveal>

                <Reveal
                    as="section"
                    delay={0.05}
                    className="col-span-1 lg:col-span-2"
                >
                    <nav aria-label="Produk & fitur">
                        <ColumnHeading>Produk &amp; Fitur</ColumnHeading>
                        <ul className="mt-6 space-y-3">
                            {FOOTER_MODULES.map((label) => (
                                <li key={label}>
                                    <a
                                        href={`${anchorPrefix}#platform`}
                                        className={LINK_CLASS}
                                    >
                                        {label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>
                </Reveal>

                <Reveal
                    as="section"
                    delay={0.08}
                    className="col-span-1 lg:col-span-2"
                >
                    <nav aria-label="Solusi terpadu">
                        <ColumnHeading>Solusi Terpadu</ColumnHeading>
                        <ul className="mt-6 space-y-3">
                            {FOOTER_EXPLORE.map((item) => (
                                <li key={item.link}>
                                    <a
                                        href={`${anchorPrefix}${item.link}`}
                                        className={LINK_CLASS}
                                    >
                                        {item.name}
                                    </a>
                                </li>
                            ))}
                            <li>
                                <Link
                                    href={liveTracking().url}
                                    className={LINK_CLASS}
                                >
                                    Live Tracking
                                </Link>
                            </li>
                        </ul>
                    </nav>
                </Reveal>

                <Reveal
                    as="section"
                    delay={0.11}
                    className="col-span-1 lg:col-span-2"
                >
                    <nav aria-label="Insight">
                        <ColumnHeading>Insight</ColumnHeading>
                        <ul className="mt-6 space-y-3">
                            {FOOTER_INSIGHT.map((item) => (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        className={LINK_CLASS}
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                </Reveal>

                <Reveal
                    as="section"
                    delay={0.14}
                    className="col-span-1 lg:col-span-2"
                >
                    <nav aria-label="Perusahaan">
                        <ColumnHeading>Perusahaan</ColumnHeading>
                        <ul className="mt-6 space-y-3">
                            <li>
                                {trialExternal ? (
                                    <a
                                        href={trial}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={LINK_CLASS}
                                    >
                                        Coba Avana
                                    </a>
                                ) : (
                                    <Link href={trial} className={LINK_CLASS}>
                                        Coba Avana
                                    </Link>
                                )}
                            </li>
                            <li>
                                <Link href={loginUrl} className={LINK_CLASS}>
                                    Masuk
                                </Link>
                            </li>
                            <li>
                                <a
                                    href={`${anchorPrefix}#kontak`}
                                    className={LINK_CLASS}
                                >
                                    Hubungi Kami
                                </a>
                            </li>
                        </ul>
                    </nav>
                </Reveal>

                {(website.apps.playstore_url || website.apps.appstore_url) && (
                    <Reveal
                        delay={0.16}
                        className="col-span-1 flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-12"
                    >
                        {website.apps.playstore_url && (
                            <a
                                href={website.apps.playstore_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex h-11 items-center gap-2.5 rounded-xl border border-white/15 bg-white/5 px-4 transition-colors hover:border-white/30 hover:bg-white/10"
                            >
                                <PlayStoreGlyph className="h-5 w-5 shrink-0" />
                                <span className="text-left leading-tight">
                                    <span className="block text-[9.5px] text-blue-200/70">
                                        GET IT ON
                                    </span>
                                    <span className="block text-[13px] font-bold text-white">
                                        Google Play
                                    </span>
                                </span>
                            </a>
                        )}
                        {website.apps.appstore_url && (
                            <a
                                href={website.apps.appstore_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex h-11 items-center gap-2.5 rounded-xl border border-white/15 bg-white/5 px-4 transition-colors hover:border-white/30 hover:bg-white/10"
                            >
                                <AppleGlyph className="h-5 w-5 shrink-0" />
                                <span className="text-left leading-tight">
                                    <span className="block text-[9.5px] text-blue-200/70">
                                        Download on the
                                    </span>
                                    <span className="block text-[13px] font-bold text-white">
                                        App Store
                                    </span>
                                </span>
                            </a>
                        )}
                    </Reveal>
                )}
            </Container>

            <div className="relative border-t border-white/10">
                <Container className="flex flex-col items-center justify-between gap-4 py-6 sm:flex-row">
                    <p className="order-3 text-[13px] text-blue-200/60 sm:order-1">
                        &copy; {new Date().getFullYear()} {brand}. Seluruh hak
                        cipta dilindungi.
                    </p>

                    <div className="order-2 flex items-center gap-1.5 text-[13px] text-blue-200/60">
                        <Link
                            href="/kebijakan-privasi"
                            className="transition-colors hover:text-white"
                        >
                            Kebijakan Privasi
                        </Link>
                        <span aria-hidden>&middot;</span>
                        <Link
                            href="/syarat-ketentuan"
                            className="transition-colors hover:text-white"
                        >
                            Syarat &amp; Ketentuan
                        </Link>
                    </div>

                    <a
                        href="#top"
                        aria-label="Kembali ke atas"
                        className="order-1 grid h-9 w-9 place-items-center rounded-full border border-white/15 text-blue-200 transition-colors hover:border-avana-blue hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60 sm:order-3"
                    >
                        <ArrowUp className="h-4 w-4" aria-hidden />
                    </a>
                </Container>
            </div>
        </footer>
    );
}
