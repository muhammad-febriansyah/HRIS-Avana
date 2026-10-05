import { useEffect, useMemo, useRef, useState } from 'react';
import { loadGoogleMaps } from '@/lib/google-maps';
import {
    FullscreenButton,
    useFullscreenToggle,
} from './fullscreen-control.client';
import { RoadRoute, sampleWaypoints } from './road-route.client';
import type { RoutePoint } from './route-history-map';

type MapOverlay = { setMap?: (map: google.maps.Map | null) => void };

export default function RouteHistoryMapClient({
    points,
    height,
}: {
    points: RoutePoint[];
    height: number;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapElement = useRef<HTMLDivElement>(null);
    const mapRef = useRef<google.maps.Map | null>(null);
    const overlaysRef = useRef<MapOverlay[]>([]);
    const [map, setMap] = useState<google.maps.Map | null>(null);
    const { isFullscreen, toggle } = useFullscreenToggle(containerRef);
    const route = useMemo(
        () =>
            points.map((point) => ({
                lat: point.latitude,
                lng: point.longitude,
            })),
        [points],
    );
    const waypoints = useMemo(() => sampleWaypoints(points, 8), [points]);

    useEffect(() => {
        let active = true;

        void loadGoogleMaps().then((maps) => {
            if (!active || !mapElement.current) {
                return;
            }

            const instance = new maps.maps.Map(mapElement.current, {
                center: route[0] ?? { lat: -6.1754, lng: 106.8272 },
                zoom: 14,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });
            mapRef.current = instance;
            setMap(instance);
        });

        return () => {
            active = false;
            overlaysRef.current.forEach((overlay) => overlay.setMap?.(null));
            overlaysRef.current = [];
            mapRef.current = null;
        };
        // Map instance initializes once after browser mount.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!map) {
            return;
        }

        overlaysRef.current.forEach((overlay) => overlay.setMap?.(null));
        overlaysRef.current = [];

        if (route.length > 0) {
            const bounds = new google.maps.LatLngBounds();
            route.forEach((point) => bounds.extend(point));
            map.fitBounds(bounds, 48);
        }

        if (route.length > 1) {
            overlaysRef.current.push(
                new google.maps.Polyline({
                    map,
                    path: route,
                    strokeColor: '#93C5FD',
                    strokeOpacity: 0.6,
                    strokeWeight: 3,
                    icons: [
                        {
                            icon: {
                                path: 'M 0,-1 0,1',
                                strokeOpacity: 1,
                                scale: 2,
                            },
                            offset: '0',
                            repeat: '12px',
                        },
                    ],
                }),
            );
        }
    }, [map, route]);

    useEffect(() => {
        if (!map) {
            return;
        }

        const timer = window.setTimeout(() => {
            google.maps.event.trigger(map, 'resize');
        }, 80);

        return () => window.clearTimeout(timer);
    }, [isFullscreen, map]);

    return (
        <div
            ref={containerRef}
            className="relative overflow-hidden rounded-xl border border-slate-200 bg-white"
            style={{ height: isFullscreen ? '100vh' : height }}
        >
            <div ref={mapElement} className="h-full w-full" />
            {map && waypoints.length > 1 && (
                <RoadRoute
                    map={map}
                    waypoints={waypoints}
                    color="#2563EB"
                    startLabel="Mulai"
                    endLabel="Selesai"
                />
            )}
            <FullscreenButton isFullscreen={isFullscreen} onToggle={toggle} />
        </div>
    );
}
