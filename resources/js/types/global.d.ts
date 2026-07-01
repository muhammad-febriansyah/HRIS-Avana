import type { Auth } from '@/types/auth';

/** Database-driven branding shared with every page (see `WebsiteSetting`). */
export interface Website {
    site_name: string | null;
    tagline: string | null;
    logo_url: string | null;
}

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            website: Website;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
