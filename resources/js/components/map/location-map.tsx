import { lazy, Suspense, useEffect, useState } from 'react';

export interface MapPoint {
    lat: number;
    lng: number;
    label?: string;
}

export interface LocationMapProps {
    /** Pins to render (e.g. clock-in point, work-location center). */
    points: MapPoint[];
    /** Optional geofence circle center + radius (meters). */
    area?: { lat: number; lng: number; radius: number } | null;
    height?: number;
    /** Initial zoom level (ignored when `fit` fits to bounds). */
    zoom?: number;
    /** Auto-fit the viewport to every point (for multi-location monitors). */
    fit?: boolean;
    /**
     * Pin each point's label to the map instead of hiding it behind a click.
     * For multi-branch views where the labels are the point of the map.
     */
    labels?: boolean;
}

// Leaflet reaches for `window` at import time, so the actual map lives in a
// separate chunk that is only imported on the client, after mount. This keeps
// the module tree server-render safe (Inertia SSR).
const LocationMapClient = lazy(() => import('./location-map.client'));

/**
 * Read-only map used to visualize attendance points against a work location's
 * geofence. Renders a placeholder on the server and until the client mounts,
 * then swaps in the Leaflet map.
 */
export function LocationMap(props: LocationMapProps) {
    const height = props.height ?? 300;
    const [mounted, setMounted] = useState(false);

    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMounted(true), []);

    const frame: React.CSSProperties = {
        height,
        borderRadius: 10,
        overflow: 'hidden',
        border: '1px solid #E2E8F0',
    };

    const placeholder = (
        <div
            style={{
                ...frame,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: '#F1F5F9',
                color: '#94A3B8',
                fontSize: 13,
            }}
        >
            Memuat peta…
        </div>
    );

    if (!mounted) {
        return placeholder;
    }

    return (
        <Suspense fallback={placeholder}>
            <LocationMapClient {...props} />
        </Suspense>
    );
}

export default LocationMap;
