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
import { FOOTER_EXPLORE, FOOTER_PRODUCT } from './content';
import { Container, Reveal } from './reveal';
import { useCtaTargets } from './use-cta';

type Social = {
    href: string;
    icon: ComponentType<{ className?: string }>;
    label: string;
};

const LINK_CLASS =
    'inline-block rounded-sm text-[14px] text-blue-100/70 transition-[color,transform] duration-200 hover:translate-x-1 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60';

const SOCIAL_CLASS =
    'grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-blue-200 transition-all duration-200 hover:-translate-y-0.5 hover:border-avana-blue hover:bg-avana-blue hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60';

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
    const { login: loginUrl, trial } = useCtaTargets();

    const socials = (
        [
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
                        className="h-9 w-auto rounded-xl bg-white p-2 object-contain"
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
                    className="col-span-1 lg:col-span-2 lg:col-start-6"
                >
                    <nav aria-label="Produk">
                        <ColumnHeading>Produk &amp; Fitur</ColumnHeading>
                        <ul className="mt-6 space-y-3">
                            {FOOTER_PRODUCT.map((item) => (
                                <li key={item.label}>
                                    <a
                                        href={`${anchorPrefix}${item.href}`}
                                        className={LINK_CLASS}
                                    >
                                        {item.label}
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

                <Reveal as="section" delay={0.1} className="col-span-1 lg:col-span-2">
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
                        </ul>
                    </nav>
                </Reveal>

                <Reveal as="section" delay={0.15} className="col-span-1 lg:col-span-2">
                    <nav aria-label="Akun">
                        <ColumnHeading>Akun</ColumnHeading>
                        <ul className="mt-6 space-y-3">
                            <li>
                                <Link href={trial} className={LINK_CLASS}>
                                    Coba Avana
                                </Link>
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
            </Container>

            <div className="relative border-t border-white/10">
                <Container className="flex flex-col items-center justify-between gap-4 py-6 sm:flex-row">
                    <p className="order-2 text-[13px] text-blue-200/60 sm:order-1">
                        &copy; {new Date().getFullYear()} {brand}. Seluruh
                        hak cipta dilindungi.
                    </p>

                    <a
                        href="#top"
                        aria-label="Kembali ke atas"
                        className="order-1 grid h-9 w-9 place-items-center rounded-full border border-white/15 text-blue-200 transition-colors hover:border-avana-blue hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60 sm:order-2"
                    >
                        <ArrowUp className="h-4 w-4" aria-hidden />
                    </a>
                </Container>
            </div>
        </footer>
    );
}
