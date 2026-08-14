const FALLBACK = '/avana/logo-full.png';

/**
 * Brand logo with a bundled fallback.
 *
 * The configured logo lives on the public storage disk, so a missing file (or
 * a broken storage symlink) would otherwise leave the marketing page with a
 * broken image. The tag is server-rendered, which means its `error` event can
 * fire before React hydrates and attaches the handler — the ref callback
 * therefore re-checks a already-settled image when the node mounts.
 */
export function BrandLogo({
    src,
    alt,
    className,
    loading,
}: {
    src: string;
    alt: string;
    className?: string;
    loading?: 'lazy' | 'eager';
}) {
    const applyFallback = (image: HTMLImageElement) => {
        if (!image.src.endsWith(FALLBACK)) {
            image.src = FALLBACK;
        }
    };

    return (
        <img
            ref={(image) => {
                if (image?.complete && image.naturalWidth === 0) {
                    applyFallback(image);
                }
            }}
            src={src}
            alt={alt}
            loading={loading}
            className={className}
            onError={(event) => applyFallback(event.currentTarget)}
        />
    );
}
