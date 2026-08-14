import { Head, usePage } from '@inertiajs/react';
import { AiAssistantSection } from '@/components/marketing/ai-assistant-section';
import { AnalyticsSection } from '@/components/marketing/analytics-section';
import { ApprovalSection } from '@/components/marketing/approval-section';
import { ControlSection } from '@/components/marketing/control-section';
import { EmployeeJourney } from '@/components/marketing/employee-journey';
import { FaqSection } from '@/components/marketing/faq-section';
import { FinalCta } from '@/components/marketing/final-cta';
import { HeroSection } from '@/components/marketing/hero-section';
import { MobileSection } from '@/components/marketing/mobile-section';
import { PayrollJourney } from '@/components/marketing/payroll-journey';
import { PlatformComparison } from '@/components/marketing/platform-comparison';
import { ProblemSection } from '@/components/marketing/problem-section';
import { ProductTour } from '@/components/marketing/product-tour';
import { RoleSection } from '@/components/marketing/role-section';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { SolutionBento } from '@/components/marketing/solution-bento';
import { TalentSection } from '@/components/marketing/talent-section';
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
                    <ProblemSection />
                    <EmployeeJourney />
                    <SolutionBento />
                    <PayrollJourney />
                    <ApprovalSection />
                    <TalentSection />
                    <AnalyticsSection />
                    <AiAssistantSection />
                    <MobileSection />
                    <RoleSection />
                    <PlatformComparison brand={brand} logo={logo} />
                    <ControlSection />
                    <ProductTour />
                    <FaqSection />
                    <FinalCta />
                </main>

                <SiteFooter brand={brand} logo={logo} />

                <WhatsAppFab />
            </div>
        </>
    );
}
