import { useEffect, useRef } from 'react';
import { loadGoogleMaps } from '@/lib/google-maps';

export interface RoadRouteSummary {
    distanceMeters: number;
    durationSeconds: number;
}

interface RoadRouteProps {
    map: google.maps.Map;
    waypoints: [number, number][];
    color?: string;
    startLabel?: string;
    endLabel?: string;
    showEndpoints?: boolean;
    onSummary?: (summary: RoadRouteSummary) => void;
    onError?: () => void;
}

type MapOverlay = { setMap?: (map: google.maps.Map | null) => void };

export function RoadRoute({
    map,
    waypoints,
    color = '#2563EB',
    startLabel = 'Mulai',
    endLabel = 'Terkini',
    showEndpoints = true,
    onSummary,
    onError,
}: RoadRouteProps) {
    const onSummaryRef = useRef(onSummary);
    const onErrorRef = useRef(onError);
    const overlaysRef = useRef<MapOverlay[]>([]);
    const waypointsKey = JSON.stringify(waypoints);

    useEffect(() => {
        onSummaryRef.current = onSummary;
        onErrorRef.current = onError;
    });

    useEffect(() => {
        if (waypoints.length < 2) {
            return;
        }

        let active = true;

        void loadGoogleMaps()
            .then((maps) => {
                if (!active) {
                    return;
                }

                const service = new maps.maps.DirectionsService();
                const renderer = new maps.maps.DirectionsRenderer({
                    map,
                    suppressMarkers: true,
                    preserveViewport: false,
                    polylineOptions: {
                        strokeColor: color,
                        strokeOpacity: 0.85,
                        strokeWeight: 5,
                    },
                });
                overlaysRef.current.push(renderer);

                if (showEndpoints) {
                    const start = new maps.maps.Marker({
                        map,
                        position: coordinate(waypoints[0]),
                        title: startLabel,
                        icon: markerIcon('#94A3B8', 14),
                    });
                    const end = new maps.maps.Marker({
                        map,
                        position: coordinate(waypoints[waypoints.length - 1]),
                        title: endLabel,
                        icon: markerIcon(color, 24),
                    });
                    overlaysRef.current.push(start, end);
                }

                service.route(
                    {
                        origin: coordinate(waypoints[0]),
                        destination: coordinate(
                            waypoints[waypoints.length - 1],
                        ),
                        waypoints: waypoints.slice(1, -1).map((point) => ({
                            location: coordinate(point),
                            stopover: false,
                        })),
                        optimizeWaypoints: false,
                        travelMode: maps.maps.TravelMode.WALKING,
                    },
                    (result, status) => {
                        if (!active) {
                            return;
                        }

                        if (
                            status !== maps.maps.DirectionsStatus.OK ||
                            !result
                        ) {
                            onErrorRef.current?.();

                            return;
                        }

                        renderer.setDirections(result);
                        const totals = result.routes[0]?.legs.reduce(
                            (summary, leg) => ({
                                distanceMeters:
                                    summary.distanceMeters +
                                    (leg.distance?.value ?? 0),
                                durationSeconds:
                                    summary.durationSeconds +
                                    (leg.duration?.value ?? 0),
                            }),
                            { distanceMeters: 0, durationSeconds: 0 },
                        );

                        if (totals) {
                            onSummaryRef.current?.(totals);
                        }
                    },
                );
            })
            .catch(() => onErrorRef.current?.());

        return () => {
            active = false;
            overlaysRef.current.forEach((overlay) => overlay.setMap?.(null));
            overlaysRef.current = [];
        };
        // Route is recreated when its waypoints or visual options change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [color, endLabel, map, showEndpoints, startLabel, waypointsKey]);

    return null;
}

function coordinate([lat, lng]: [number, number]): google.maps.LatLngLiteral {
    return { lat, lng };
}

function markerIcon(color: string, size: number): google.maps.Symbol {
    return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: size / 2,
        fillColor: color,
        fillOpacity: 1,
        strokeColor: '#FFFFFF',
        strokeWeight: 3,
    };
}

function haversineMeters(
    [lat1, lng1]: [number, number],
    [lat2, lng2]: [number, number],
): number {
    const R = 6371000;
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLng = ((lng2 - lng1) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos((lat1 * Math.PI) / 180) *
            Math.cos((lat2 * Math.PI) / 180) *
            Math.sin(dLng / 2) ** 2;

    return 2 * R * Math.asin(Math.sqrt(a));
}

export function sampleWaypoints(
    points: { latitude: number; longitude: number }[],
    maxWaypoints = 8,
    minSpacingMeters = 120,
): [number, number][] {
    const coords = points.map(
        (point) => [point.latitude, point.longitude] as [number, number],
    );

    if (coords.length <= 2) {
        return coords;
    }

    const spaced: [number, number][] = [coords[0]];

    for (let i = 1; i < coords.length - 1; i++) {
        if (
            haversineMeters(spaced[spaced.length - 1], coords[i]) >=
            minSpacingMeters
        ) {
            spaced.push(coords[i]);
        }
    }

    spaced.push(coords[coords.length - 1]);

    if (spaced.length <= maxWaypoints) {
        return spaced;
    }

    const step = (spaced.length - 1) / (maxWaypoints - 1);

    return Array.from(
        { length: maxWaypoints },
        (_, index) => spaced[Math.round(index * step)],
    );
}
