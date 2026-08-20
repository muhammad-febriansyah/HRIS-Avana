import { Head, usePage } from '@inertiajs/react';
import { Container, Reveal } from '@/components/marketing/reveal';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

/**
 * Public privacy policy. Content is edited by a super admin (Website
 * Settings → Kebijakan Privasi) via `PrivacyPolicyController` — this is the
 * URL submitted as the app's privacy policy on Play Console / App Store
 * Connect, so it must reflect what's actually configured, not static copy.
 */
export default function PrivacyPolicy({ content }: { content: string }) {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';

    return (
        <>
            <Head title={`Kebijakan Privasi — ${brand}`} />

            <div className="min-h-dvh bg-white font-sans text-[#1A2333] antialiased">
                <SiteNavbar brand={brand} logo={logo} anchorPrefix="/" />

                <main className="py-16 lg:py-20">
                    <Container className="max-w-3xl">
                        <Reveal>
                            <h1 className="text-3xl font-bold text-avana-navy sm:text-4xl">
                                Kebijakan Privasi
                            </h1>
                            <p className="mt-3 text-sm text-avana-muted">
                                Berlaku untuk {brand} dan aplikasi mobile-nya.
                            </p>
                        </Reveal>

                        <div
                            className="article-body"
                            // Sanitised server-side before storage.
                            dangerouslySetInnerHTML={{ __html: content }}
                        />
                    </Container>
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />

                <WhatsAppFab />
            </div>
        </>
    );
}
