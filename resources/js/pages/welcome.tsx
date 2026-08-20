import { Head, usePage } from '@inertiajs/react';
import { AiInsightSection } from '@/components/marketing/ai-insight-section';
import { BottomCta } from '@/components/marketing/bottom-cta';
import { FinalCta } from '@/components/marketing/final-cta';
import { FreeTrialBanner } from '@/components/marketing/free-trial-banner';
import { HeroSection } from '@/components/marketing/hero-section';
import { ImplementationSection } from '@/components/marketing/implementation-section';
import { ModulesSection } from '@/components/marketing/modules-section';
import { PricingSection } from '@/components/marketing/pricing-section';
import { ProblemSection } from '@/components/marketing/problem-section';
import { ProductDemoSection } from '@/components/marketing/product-demo-section';
import { ProofSection } from '@/components/marketing/proof-section';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { SolutionSection } from '@/components/marketing/solution-section';
import { TrustStrip } from '@/components/marketing/trust-strip';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';

const DESCRIPTION =
    'AvanaHR menyatukan HR Management, Attendance, Payroll, Finance, Talent Management, Employee Self Service dan Analytics dalam satu platform.';

/**
 * Public marketing landing page. Each section lives in
 * `components/marketing/*` and reads its copy from `content.ts`, so this file
 * only owns page-level concerns: document head and section order.
 */
export default function Welcome() {
    const { website } = usePage().props;
    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';

    return (
        <>
            <Head title={`${brand} — Software HRIS & Payroll Terpadu`}>
                <meta name="description" content={DESCRIPTION} />
                <meta property="og:type" content="website" />
                <meta
                    property="og:title"
                    content={`${brand} — Software HRIS & Payroll Terpadu`}
                />
                <meta property="og:description" content={DESCRIPTION} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    name="twitter:title"
                    content={`${brand} — Software HRIS & Payroll Terpadu`}
                />
                <meta name="twitter:description" content={DESCRIPTION} />
            </Head>

            <div
                id="top"
                className="min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333] antialiased"
            >
                <SiteNavbar brand={brand} logo={logo} />

                <main>
                    <HeroSection />
                    <TrustStrip />
                    <ProblemSection />
                    <SolutionSection />
                    <ModulesSection />
                    <AiInsightSection />
                    <ProductDemoSection />
                    <ProofSection />
                    <ImplementationSection />
                    <PricingSection />
                    <FreeTrialBanner />
                    <FinalCta />
                </main>

                <BottomCta />
                <SiteFooter brand={brand} logo={logo} />

                <WhatsAppFab />
            </div>
        </>
    );
}
