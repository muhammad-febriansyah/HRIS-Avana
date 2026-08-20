import { Facebook, Link2, Linkedin, Check } from 'lucide-react';
import { useState } from 'react';
import { WhatsAppIcon } from './whatsapp-fab';

/** X (formerly Twitter) glyph — the official mark, drawn as a single path. */
export function XIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden className={className}>
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
        </svg>
    );
}

function buildShareLinks(url: string, title: string) {
    const encodedUrl = encodeURIComponent(url);
    const encodedTitle = encodeURIComponent(title);

    return {
        whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
        x: `https://twitter.com/intent/tweet?text=${encodedTitle}&url=${encodedUrl}`,
        linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
    };
}

/**
 * Social share row for an article — WhatsApp, Facebook, X, LinkedIn (all
 * standard web share intents, no app SDK/API key needed) plus a copy-link
 * button. `url` is the absolute article URL, resolved server-side
 * (`route('berita.show', $news)`) so it works the same rendered via SSR.
 */
export function ShareButtons({ url, title }: { url: string; title: string }) {
    const [copied, setCopied] = useState(false);
    const links = buildShareLinks(url, title);

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard API unavailable (e.g. insecure context) — silently no-op,
            // the visible link buttons still work.
        }
    };

    const ICON_CLASS =
        'grid h-9 w-9 place-items-center rounded-full border border-avana-border text-avana-navy transition-colors hover:border-avana-blue hover:bg-avana-light hover:text-avana-blue';

    return (
        <div className="flex flex-wrap items-center gap-2.5">
            <span className="text-[13px] font-semibold text-avana-muted">
                Bagikan:
            </span>

            <a
                href={links.whatsapp}
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Bagikan ke WhatsApp"
                className={ICON_CLASS}
            >
                <WhatsAppIcon className="h-4 w-4" />
            </a>
            <a
                href={links.facebook}
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Bagikan ke Facebook"
                className={ICON_CLASS}
            >
                <Facebook className="h-4 w-4" aria-hidden />
            </a>
            <a
                href={links.x}
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Bagikan ke X"
                className={ICON_CLASS}
            >
                <XIcon className="h-4 w-4" />
            </a>
            <a
                href={links.linkedin}
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Bagikan ke LinkedIn"
                className={ICON_CLASS}
            >
                <Linkedin className="h-4 w-4" aria-hidden />
            </a>
            <button
                type="button"
                onClick={copyLink}
                aria-label="Salin tautan"
                className={ICON_CLASS}
            >
                {copied ? (
                    <Check className="h-4 w-4 text-emerald-600" aria-hidden />
                ) : (
                    <Link2 className="h-4 w-4" aria-hidden />
                )}
            </button>
        </div>
    );
}
