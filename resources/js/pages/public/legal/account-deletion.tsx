import { Head, usePage } from '@inertiajs/react';
import { Container, Reveal } from '@/components/marketing/reveal';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

export default function AccountDeletion({ content }: { content: string }) {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';

    return (
        <>
            <Head title={`Penghapusan Akun dan Data — ${brand}`} />
            <div className="min-h-dvh bg-white font-sans text-[#1A2333] antialiased">
                <SiteNavbar brand={brand} logo={logo} anchorPrefix="/" />
                <main className="py-16 lg:py-20">
                    <Container className="max-w-3xl">
                        <Reveal>
                            <h1 className="text-3xl font-bold text-avana-navy sm:text-4xl">
                                Penghapusan Akun dan Data
                            </h1>
                            <p className="mt-3 text-sm text-avana-muted">
                                Informasi pengajuan penghapusan akun AvanaHR.
                            </p>
                        </Reveal>
                        <div
                            className="article-body"
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
