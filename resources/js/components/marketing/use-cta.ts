import { usePage } from '@inertiajs/react';
import { login } from '@/routes';

const DEMO_MESSAGE = 'Halo, saya ingin menjadwalkan demo AvanaHR.';

export type CtaTargets = {
    /** "Jadwalkan Demo" — WhatsApp, then email, then the contact block in the footer. */
    demo: { href: string; external: boolean };
    /**
     * "Coba Avana" — points at the login screen, not registration: self-serve
     * sign-up is not open yet, so sending visitors there would dead-end them.
     */
    trial: string;
    login: string;
    /** Direct WhatsApp link with the demo message, or null when no number is configured. */
    whatsapp: string | null;
    /** Builds a WhatsApp link with your own prefilled message; null without a number. */
    whatsappWith: (message: string) => string | null;
};

/**
 * Resolves the marketing CTAs against destinations that actually exist.
 *
 * Nothing here invents a contact channel: the WhatsApp number and e-mail
 * address come from the website settings, and the demo CTA falls back to
 * scrolling to the footer contact block when neither has been filled in.
 */
export function useCtaTargets(): CtaTargets {
    const { website } = usePage().props;
    const number = website.contact.whatsapp?.replace(/[^\d]/g, '');
    const email = website.contact.email;

    const whatsappWith = (message: string) =>
        number
            ? `https://wa.me/${number}?text=${encodeURIComponent(message)}`
            : null;

    const whatsapp = whatsappWith(DEMO_MESSAGE);

    const demo = whatsapp
        ? { href: whatsapp, external: true }
        : email
          ? {
                href: `mailto:${email}?subject=${encodeURIComponent(
                    'Permintaan demo AvanaHR',
                )}`,
                external: true,
            }
          : { href: '#kontak', external: false };

    return {
        demo,
        trial: login().url,
        login: login().url,
        whatsapp,
        whatsappWith,
    };
}
