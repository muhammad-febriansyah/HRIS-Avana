/**
 * Shared types for the AvanaHR website settings page. These mirror the
 * `WebsiteSettingController` payloads (`edit`, `update`).
 */

export type { FlashProps } from '../employees/types';

/** Settings serialized by `WebsiteSettingController::edit`. */
export interface Settings {
    site_name: string | null;
    tagline: string | null;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    social_facebook: string | null;
    social_instagram: string | null;
    social_twitter: string | null;
    social_youtube: string | null;
    social_linkedin: string | null;
    social_tiktok: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_whatsapp: string | null;
    contact_address: string | null;
    logo_url: string | null;
    favicon_url: string | null;
    og_image_url: string | null;
}

/** Props for the website settings page (`index.tsx`). */
export interface PageProps {
    settings: Settings;
}

/** Image upload form fields. */
export type ImageField = 'logo' | 'favicon' | 'og_image';

/** Flat form payload backing the settings editor. */
export interface FormData {
    site_name: string;
    tagline: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    social_facebook: string;
    social_instagram: string;
    social_twitter: string;
    social_youtube: string;
    social_linkedin: string;
    social_tiktok: string;
    contact_email: string;
    contact_phone: string;
    contact_whatsapp: string;
    contact_address: string;
    logo: File | null;
    favicon: File | null;
    og_image: File | null;
    remove_logo: boolean;
    remove_favicon: boolean;
    remove_og_image: boolean;
}

/** Tab definitions for the settings editor. */
export const TABS: { id: string; label: string; icon: string }[] = [
    { id: 'umum', label: 'Umum', icon: 'settings' },
    { id: 'brand', label: 'Brand & Logo', icon: 'image' },
    { id: 'seo', label: 'SEO', icon: 'search' },
    { id: 'sosial', label: 'Sosial Media', icon: 'share-2' },
    { id: 'kontak', label: 'Kontak', icon: 'phone' },
];
