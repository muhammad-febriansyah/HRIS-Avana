import type { Auth } from '@/types/auth';

/** Database-driven branding shared with every page (see `WebsiteSetting`). */
export interface Website {
    site_name: string | null;
    tagline: string | null;
    logo_url: string | null;
    contact: {
        email: string | null;
        phone: string | null;
        whatsapp: string | null;
        address: string | null;
    };
    social: {
        facebook: string | null;
        instagram: string | null;
        twitter: string | null;
        youtube: string | null;
        linkedin: string | null;
        tiktok: string | null;
    };
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
