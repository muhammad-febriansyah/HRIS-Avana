import { lazy, Suspense, useEffect, useState } from 'react';

export interface RoutePoint {
    latitude: number;
    longitude: number;
    accuracy: number;
    speed: number | null;
    is_mocked: boolean;
    is_suspicious: boolean;
    recorded_at: string;
}

const RouteHistoryMapClient = lazy(() => import('./route-history-map.client'));

export function RouteHistoryMap({
    points,
    height = 560,
}: {
    points: RoutePoint[];
    height?: number;
}) {
    const [mounted, setMounted] = useState(false);

    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMounted(true), []);

    const placeholder = (
        <div
            className="flex items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-sm text-slate-400"
            style={{ height }}
        >
            Memuat riwayat rute…
        </div>
    );

    if (!mounted) {
        return placeholder;
    }

    return (
        <Suspense fallback={placeholder}>
            <RouteHistoryMapClient points={points} height={height} />
        </Suspense>
    );
}
