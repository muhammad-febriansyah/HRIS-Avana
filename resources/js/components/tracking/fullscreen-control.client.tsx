import { useEffect, useState } from 'react';
import type { RefObject } from 'react';
import { useMap } from 'react-leaflet';
import { AIcon } from '@/lib/avana';

/**
 * Tracks whether `containerRef`'s element is the page's current Fullscreen
 * API target, and exposes a toggle. Leaflet keeps its own size cache, so
 * anything switching a map's container size (like this) must follow up with
 * `map.invalidateSize()` — see `FullscreenSync` below.
 */
export function useFullscreenToggle(containerRef: RefObject<HTMLElement | null>) {
    const [isFullscreen, setIsFullscreen] = useState(false);

    useEffect(() => {
        const handleChange = () => {
            setIsFullscreen(document.fullscreenElement === containerRef.current);
        };

        document.addEventListener('fullscreenchange', handleChange);

        return () => document.removeEventListener('fullscreenchange', handleChange);
    }, [containerRef]);

    const toggle = () => {
        if (document.fullscreenElement) {
            void document.exitFullscreen();

            return;
        }

        void containerRef.current?.requestFullscreen();
    };

    return { isFullscreen, toggle };
}

/** Belongs inside <MapContainer>. Nudges Leaflet to re-measure its container
 * after a fullscreen transition, since resizing the container doesn't fire
 * a window `resize` event on its own. */
export function FullscreenSync({ isFullscreen }: { isFullscreen: boolean }) {
    const map = useMap();

    useEffect(() => {
        const id = window.setTimeout(() => map.invalidateSize(), 80);

        return () => window.clearTimeout(id);
    }, [map, isFullscreen]);

    return null;
}

export function FullscreenButton({
    isFullscreen,
    onToggle,
}: {
    isFullscreen: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onToggle}
            title={isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'}
            // Bottom-right: top-right is where leaflet-routing-machine docks
            // its itinerary panel, and top-left has the zoom controls.
            className="absolute right-3 bottom-8 z-[1000] flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
        >
            <AIcon name={isFullscreen ? 'minimize-2' : 'maximize-2'} size={16} />
        </button>
    );
}
