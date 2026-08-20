import { X } from 'lucide-react';
import { useState } from 'react';
import { useCtaTargets } from './use-cta';

/** WhatsApp glyph — the official mark, drawn as a single path. */
export function WhatsAppIcon({ className }: { className?: string }) {
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
 * the landing page never invents a contact channel. A dismissible tooltip
 * badge sits beside the button on larger screens, and the button itself
 * carries a pulsing "online" ring to draw the eye without being obnoxious.
 */
export function WhatsAppFab() {
    const { whatsapp } = useCtaTargets();
    const [showTooltip, setShowTooltip] = useState(true);

    if (!whatsapp) {
        return null;
    }

    return (
        <div className="pointer-events-auto fixed right-4 bottom-4 z-50 flex items-end gap-3 sm:right-6 sm:bottom-6">
            {showTooltip && (
                <div className="hidden animate-in fade-in-50 slide-in-from-bottom-2 items-center gap-2.5 rounded-2xl border border-avana-border bg-white p-3 pr-3.5 shadow-avana-hover sm:flex">
                    <span className="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-[#25D366]" />
                    <div className="text-xs font-semibold text-avana-navy">
                        <span>Butuh konsultasi cepat? </span>
                        <a
                            href={whatsapp}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-bold text-[#25D366] hover:underline"
                        >
                            Chat via WhatsApp
                        </a>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowTooltip(false)}
                        className="ml-1 shrink-0 p-0.5 text-gray-400 hover:text-gray-600"
                        aria-label="Tutup"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>
            )}

            <a
                href={whatsapp}
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Chat dengan AvanaHR di WhatsApp"
                className="group relative flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-xl shadow-emerald-700/30 transition-all duration-300 hover:scale-105 hover:bg-[#1EBE5B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0E1A3A] sm:h-16 sm:w-16"
            >
                <span className="absolute -top-1 -right-1 h-4 w-4 animate-ping rounded-full border-2 border-white bg-emerald-300" />
                <span className="absolute -top-1 -right-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-300" />
                <WhatsAppIcon className="h-7 w-7 sm:h-8 sm:w-8" />
            </a>
        </div>
    );
}
