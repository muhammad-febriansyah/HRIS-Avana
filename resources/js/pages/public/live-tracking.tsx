import { Head, usePage } from '@inertiajs/react';
import { FaqSection } from '@/components/marketing/faq-section';
import { FinalCta } from '@/components/marketing/final-cta';
import { TRACKING_FAQS } from '@/components/marketing/live-tracking/content';
import { DistanceShowcase } from '@/components/marketing/live-tracking/distance-showcase';
import { LiveMapShowcase } from '@/components/marketing/live-tracking/live-map-showcase';
import { MobileTrackingShowcase } from '@/components/marketing/live-tracking/mobile-tracking-showcase';
import { RouteHistoryShowcase } from '@/components/marketing/live-tracking/route-history-showcase';
import { TrackingFlow } from '@/components/marketing/live-tracking/tracking-flow';
import { TrackingHero } from '@/components/marketing/live-tracking/tracking-hero';
import { TrackingInsights } from '@/components/marketing/live-tracking/tracking-insights';
import { TrackingIntegration } from '@/components/marketing/live-tracking/tracking-integration';
import { TrackingPrivacy } from '@/components/marketing/live-tracking/tracking-privacy';
import { TrackingProblem } from '@/components/marketing/live-tracking/tracking-problem';
import { TrackingUseCases } from '@/components/marketing/live-tracking/tracking-use-cases';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

const DESCRIPTION =
    'Pantau posisi, perjalanan, jarak dan riwayat aktivitas karyawan selama jam kerja melalui Live Tracking AvanaHR.';

/**
 * Public feature page for Live Tracking.
 *
 * Chrome (navbar, footer, floating WhatsApp action) and the closing CTA and FAQ
 * blocks are the same components the landing page uses, so the page reads as
 * part of AvanaHR rather than a second site.
 */
export default function LiveTracking() {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const title = `Live Tracking Karyawan — ${brand}`;

    return (
        <>
            <Head title="Live Tracking Karyawan">
                <meta name="description" content={DESCRIPTION} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={title} />
                <meta property="og:description" content={DESCRIPTION} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={title} />
                <meta name="twitter:description" content={DESCRIPTION} />
            </Head>

            <div
                id="top"
                className="min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333] antialiased"
            >
                <SiteNavbar
                    brand={brand}
                    logo={logo}
                    anchorPrefix="/"
                    activePage="live-tracking"
                />

                <main>
                    <TrackingHero />
                    <TrackingProblem />
                    <TrackingFlow />
                    <LiveMapShowcase />
                    <RouteHistoryShowcase />
                    <DistanceShowcase />
                    <TrackingInsights />
                    <MobileTrackingShowcase />
                    <TrackingPrivacy />
                    <TrackingUseCases />
                    <TrackingIntegration />
                    <FaqSection
                        items={TRACKING_FAQS}
                        title="Pertanyaan Seputar Live Tracking"
                    />
                    <FinalCta
                        title="Lihat Tim Anda. Saat Mereka Bekerja di Lapangan."
                        body="Hubungkan attendance dengan Live Tracking dan dapatkan gambaran aktivitas employee lapangan langsung dari AvanaHR."
                        supporting="Lihat bagaimana AvanaHR Live Tracking dapat disesuaikan dengan kebutuhan perusahaan Anda."
                        showPrice={false}
                    />
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />

                <WhatsAppFab />
            </div>
        </>
    );
}
