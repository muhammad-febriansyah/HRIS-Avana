import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';

/**
 * Set to a public path (e.g. `/avana/loading.gif`) to show an animated image
 * instead of the built-in spinner.
 */
const LOADING_IMAGE: string | null = null;

/** Visits that finish faster than this never show the loader, so it cannot flicker. */
const SHOW_AFTER_MS = 200;

/**
 * Loading indicator for page visits, replacing the thin top progress bar.
 * Prefetches and background (async) visits stay silent.
 */
export function NavigationLoader() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | undefined;

        const stop = () => {
            clearTimeout(timer);
            setVisible(false);
        };

        const offStart = router.on('start', (event) => {
            const { prefetch, async } = event.detail.visit;

            if (prefetch || async) {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => setVisible(true), SHOW_AFTER_MS);
        });
        const offFinish = router.on('finish', stop);

        return () => {
            offStart();
            offFinish();
            stop();
        };
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <div
            role="status"
            aria-label="Memuat halaman"
            className="pointer-events-none fixed inset-0 z-[100] flex items-center justify-center bg-white/40 backdrop-blur-[1px]"
        >
            {LOADING_IMAGE ? (
                <img src={LOADING_IMAGE} alt="" className="h-16 w-16" />
            ) : (
                <Spinner className="size-9 text-[#2F54C9]" />
            )}
        </div>
    );
}
