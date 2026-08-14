import { lazy, Suspense, useEffect, useState } from 'react';

export interface LiveTrackingEmployee {
    id: number;
    employee_id: number;
    name: string;
    employee_number: string | null;
    department: string | null;
    position: string | null;
    shift: string | null;
    status: 'moving' | 'active' | 'stale' | 'offline';
    clock_in_at: string | null;
    started_at: string;
    duration_seconds: number;
    distance_meters: number;
    latitude: number | null;
    longitude: number | null;
    accuracy: number | null;
    speed: number | null;
    battery_level: number | null;
    is_mocked: boolean;
    is_suspicious: boolean;
    recorded_at: string | null;
}

interface TrackingMapProps {
    employees: LiveTrackingEmployee[];
    selectedId: number | null;
    onSelect: (employeeId: number) => void;
    height?: number;
}

const TrackingMapClient = lazy(() => import('./tracking-map.client'));

export function TrackingMap(props: TrackingMapProps) {
    const height = props.height ?? 620;
    const [mounted, setMounted] = useState(false);

    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMounted(true), []);

    const placeholder = (
        <div
            className="flex items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-sm text-slate-400"
            style={{ height }}
        >
            Memuat peta live tracking…
        </div>
    );

    if (!mounted) {
        return placeholder;
    }

    return (
        <Suspense fallback={placeholder}>
            <TrackingMapClient {...props} height={height} />
        </Suspense>
    );
}
