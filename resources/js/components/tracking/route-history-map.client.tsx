import 'leaflet/dist/leaflet.css';
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css';
import L from 'leaflet';
import { useEffect, useMemo, useRef } from 'react';
import { MapContainer, Polyline, TileLayer, useMap } from 'react-leaflet';
import {
    FullscreenButton,
    FullscreenSync,
    useFullscreenToggle,
} from './fullscreen-control.client';
import { RoadRoute, sampleWaypoints } from './road-route.client';
import type { RoutePoint } from './route-history-map';

function FitRoute({ points }: { points: RoutePoint[] }) {
    const map = useMap();

    useEffect(() => {
        if (points.length > 0) {
            map.fitBounds(
                L.latLngBounds(
                    points.map((point) => [point.latitude, point.longitude]),
                ),
                { padding: [48, 48], maxZoom: 17 },
            );
        }
    }, [map, points]);

    return null;
}

export default function RouteHistoryMapClient({
    points,
    height,
}: {
    points: RoutePoint[];
    height: number;
}) {
    const center: [number, number] = points[0]
        ? [points[0].latitude, points[0].longitude]
        : [-6.1754, 106.8272];
    const route = points.map(
        (point) => [point.latitude, point.longitude] as [number, number],
    );
    const waypoints = useMemo(() => sampleWaypoints(points, 8), [points]);
    const containerRef = useRef<HTMLDivElement>(null);
    const { isFullscreen, toggle } = useFullscreenToggle(containerRef);

    return (
        <div
            ref={containerRef}
            className="relative overflow-hidden rounded-xl border border-slate-200 bg-white"
            style={{ height: isFullscreen ? '100vh' : height }}
        >
            <MapContainer
                center={center}
                zoom={14}
                style={{ height: '100%', width: '100%' }}
                scrollWheelZoom
            >
                <FitRoute points={points} />
                <FullscreenSync isFullscreen={isFullscreen} />
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {/* Raw GPS trail — actual recorded points, straight lines between them. */}
                {route.length > 1 && (
                    <Polyline
                        positions={route}
                        pathOptions={{
                            color: '#93C5FD',
                            weight: 3,
                            opacity: 0.6,
                            dashArray: '4 6',
                        }}
                    />
                )}
                {/* Road-snapped route through the same trail, via OSRM, with
                    the full turn-by-turn itinerary UI. */}
                {waypoints.length > 1 && (
                    <RoadRoute
                        waypoints={waypoints}
                        color="#2563EB"
                        startLabel="Mulai"
                        endLabel="Selesai"
                    />
                )}
            </MapContainer>
            <FullscreenButton isFullscreen={isFullscreen} onToggle={toggle} />
        </div>
    );
}
