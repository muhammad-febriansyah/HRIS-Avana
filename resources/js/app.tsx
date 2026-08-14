import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AuthLayout from '@/layouts/auth-layout';
import AvanaLayout from '@/layouts/avana-layout';

const platformName = import.meta.env.VITE_APP_NAME || 'AvanaHR';

/**
 * What the browser tab is suffixed with.
 *
 * A tenant's staff see their own company, not ours — the product is
 * white-labelled everywhere else (logo, colours), and the tab should follow.
 * Only the platform side, which belongs to no tenant, keeps "AvanaHR".
 */
interface TenantAuth {
    tenant?: { name?: string; company_name?: string | null } | null;
}

createInertiaApp({
    // Inertia hands the current page to the title callback, so the suffix is
    // derived per render — correct during SSR and after every visit alike,
    // including a super admin stepping into or out of a tenant.
    title: (title, page) => {
        const tenant = (page.props.auth as TenantAuth | undefined)?.tenant;
        const suffix = tenant?.company_name || tenant?.name || platformName;

        return title ? `${title} - ${suffix}` : suffix;
    },
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            // Public marketing pages render without app chrome, same as the
            // landing page they link back to.
            case name.startsWith('public/'):
            // The error page stands alone: a 404 can land on a visitor with no
            // session, and the app chrome would render with nothing behind it.
            case name === 'error':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            default:
                return AvanaLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// AvanaHR is light-only; ensure the document is never left in dark mode.
initializeTheme();
