import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet-routing-machine';
import { useEffect, useRef } from 'react';
import { useMap } from 'react-leaflet';

// Vite doesn't resolve Leaflet's default marker images relative to the page
// URL — leaflet-routing-machine's default waypoint pins rely on
// L.Icon.Default, so point it at the bundled assets once.
delete (L.Icon.Default.prototype as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

export interface RoadRouteSummary {
    distanceMeters: number;
    durationSeconds: number;
}

interface RoadRouteProps {
    /** [latitude, longitude] pairs, already sampled down to a manageable waypoint count. */
    waypoints: [number, number][];
    color?: string;
    /** Label for the first waypoint's marker. */
    startLabel?: string;
    /** Label for the last waypoint's marker — the point this route is really about. */
    endLabel?: string;
    /** Set false when the caller already renders its own "current position"
     * marker on top of this route (e.g. the live map's employee dot) — avoids
     * stacking two markers on the same spot. */
    showEndpoints?: boolean;
    onSummary?: (summary: RoadRouteSummary) => void;
    onError?: () => void;
}

function endpointIcon(size: number, color: string): L.DivIcon {
    return L.divIcon({
        className: '',
        html: `<div style="width:${size}px;height:${size}px;border-radius:9999px;background:${color};border:3px solid #fff;box-shadow:0 1px 6px rgba(15,23,42,.45)"></div>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
}

/**
 * Draws a road-snapped route line through the given waypoints using a public
 * OSRM router via leaflet-routing-machine.
 *
 * Only the first and last waypoints get a marker — the first is a small,
 * muted dot ("Mulai"); the last is a bigger dot in the route's own colour
 * ("Terkini"/"Selesai"), so it reads as the point that actually matters at a
 * glance instead of blending into a row of identical pins. Points in between
 * still show up in the turn-by-turn panel, just without cluttering the map.
 *
 * NOTE: `routing.openstreetmap.de` is a community-run free OSRM demo (foot
 * profile — closer to how employees actually move than car routing, and it
 * avoids the one-way-street detour loops car routing forces on closely spaced
 * waypoints). Same caveat as OSRM's own demo server: no uptime/rate-limit
 * guarantees, not for production. Swap `serviceUrl` for a self-hosted OSRM
 * instance before relying on this in production.
 */
export function RoadRoute({
    waypoints,
    color = '#2563EB',
    startLabel = 'Mulai',
    endLabel = 'Terkini',
    showEndpoints = true,
    onSummary,
    onError,
}: RoadRouteProps) {
    const map = useMap();
    const onSummaryRef = useRef(onSummary);
    const onErrorRef = useRef(onError);

    useEffect(() => {
        onSummaryRef.current = onSummary;
        onErrorRef.current = onError;
    });

    const waypointsKey = JSON.stringify(waypoints);

    useEffect(() => {
        if (waypoints.length < 2) {
            return;
        }

        const latLngs = waypoints.map(([lat, lng]) => L.latLng(lat, lng));
        // Only the router's `language` is set to 'id' below — the per-step
        // turn-by-turn text comes from osrm-text-instructions, which does
        // ship an 'id' locale. leaflet-routing-machine's OWN localization
        // module (its few UI chrome strings) has no 'id' entry and throws
        // "No localization for language" if asked for one, so that one stays
        // unset/English — it's not user-facing here anyway (no plan/geocoder
        // UI is shown, just the route line + itinerary panel).
        const control = L.Routing.control({
            waypoints: latLngs,
            router: L.Routing.osrmv1({
                serviceUrl: 'https://routing.openstreetmap.de/routed-foot/route/v1',
                profile: 'foot',
                language: 'id',
            }),
            plan: L.Routing.plan(latLngs, {
                draggableWaypoints: false,
                addWaypoints: false,
                createMarker: (index, waypoint, total) => {
                    if (!showEndpoints) {
                        return false;
                    }

                    const isStart = index === 0;
                    const isEnd = index === total - 1;

                    if (!isStart && !isEnd) {
                        return false;
                    }

                    const marker = L.marker(waypoint.latLng, {
                        icon: endpointIcon(isEnd ? 24 : 14, isEnd ? color : '#94A3B8'),
                    });
                    marker.bindTooltip(isEnd ? endLabel : startLabel, {
                        direction: 'top',
                        offset: [0, isEnd ? -14 : -8],
                    });

                    return marker;
                },
            }),
            show: true,
            collapsible: false,
            addWaypoints: false,
            routeWhileDragging: false,
            fitSelectedRoutes: true,
            lineOptions: {
                styles: [{ color, weight: 5, opacity: 0.85 }],
                extendToWaypoints: true,
                missingRouteTolerance: 0,
            },
        }).addTo(map);

        control.on('routesfound', (event) => {
            const route = event.routes?.[0];

            if (route) {
                onSummaryRef.current?.({
                    distanceMeters: route.summary.totalDistance,
                    durationSeconds: route.summary.totalTime,
                });
            }
        });

        control.on('routingerror', () => {
            onErrorRef.current?.();
        });

        return () => {
            map.removeControl(control);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map, waypointsKey, color, startLabel, endLabel, showEndpoints]);

    return null;
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

/**
 * Thins a raw GPS trail down to at most `maxWaypoints` points for routing.
 *
 * A naive even-index sample can still leave waypoints just a few metres
 * apart (GPS noise, a person standing still), and OSRM routing point-to-point
 * through near-duplicate waypoints produces ugly little backtrack loops. So
 * first drop points closer than `minSpacingMeters` to the last kept point,
 * *then* thin the remainder evenly if it's still over `maxWaypoints` — always
 * keeping the first and last point either way.
 */
export function sampleWaypoints(
    points: { latitude: number; longitude: number }[],
    maxWaypoints = 8,
    minSpacingMeters = 120,
): [number, number][] {
    const coords = points.map((point) => [point.latitude, point.longitude] as [number, number]);

    if (coords.length <= 2) {
        return coords;
    }

    const spaced: [number, number][] = [coords[0]];

    for (let i = 1; i < coords.length - 1; i++) {
        if (haversineMeters(spaced[spaced.length - 1], coords[i]) >= minSpacingMeters) {
            spaced.push(coords[i]);
        }
    }

    spaced.push(coords[coords.length - 1]);

    if (spaced.length <= maxWaypoints) {
        return spaced;
    }

    const step = (spaced.length - 1) / (maxWaypoints - 1);
    const thinned: [number, number][] = [];

    for (let i = 0; i < maxWaypoints; i++) {
        thinned.push(spaced[Math.round(i * step)]);
    }

    return thinned;
}
