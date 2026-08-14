import { useCtaTargets } from './use-cta';

/** WhatsApp glyph — the official mark, drawn as a single path. */
function WhatsAppIcon({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden
            className={className}
        >
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.198-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.83 9.83 0 0 1 2.893 6.994c-.003 5.45-4.437 9.886-9.885 9.886m8.413-18.297A11.82 11.82 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.548 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413" />
        </svg>
    );
}

/**
 * Floating WhatsApp action, pinned bottom-right.
 *
 * Only rendered when a WhatsApp number is configured in the website settings —
 * the landing page never invents a contact channel. It rests as a plain circle
 * and widens into a labelled pill on hover or keyboard focus, so it stays out
 * of the way of the page content underneath.
 */
export function WhatsAppFab() {
    const { whatsapp } = useCtaTargets();

    if (!whatsapp) {
        return null;
    }

    return (
        <a
            href={whatsapp}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Hubungi kami lewat WhatsApp"
            className="group fixed right-4 bottom-4 z-50 inline-flex h-14 min-w-14 items-center justify-center rounded-full bg-[#25D366] px-4 text-white shadow-[0_12px_30px_-10px_rgba(37,211,102,0.75)] transition-[transform,box-shadow,background-color] duration-200 hover:-translate-y-0.5 hover:bg-[#1EBE5B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0E1A3A] sm:right-6 sm:bottom-6"
        >
            <WhatsAppIcon className="h-6 w-6 shrink-0" />
            {/* Collapsed to zero width at rest; the label only unfurls on hover
             * or focus. Touch devices never fire hover, so they keep the
             * circle — the aria-label carries the meaning either way. */}
            <span className="ml-0 max-w-0 overflow-hidden text-[14.5px] font-semibold whitespace-nowrap opacity-0 transition-[max-width,opacity,margin] duration-300 ease-out group-hover:ml-2.5 group-hover:max-w-[180px] group-hover:opacity-100 group-focus-visible:ml-2.5 group-focus-visible:max-w-[180px] group-focus-visible:opacity-100">
                Chat via WhatsApp
            </span>
        </a>
    );
}
