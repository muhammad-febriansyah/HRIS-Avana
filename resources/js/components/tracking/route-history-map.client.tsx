import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { useEffect } from 'react';
import {
    CircleMarker,
    MapContainer,
    Polyline,
    Popup,
    TileLayer,
    useMap,
} from 'react-leaflet';
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

    return (
        <div
            className="overflow-hidden rounded-xl border border-slate-200"
            style={{ height }}
        >
            <MapContainer
                center={center}
                zoom={14}
                style={{ height: '100%', width: '100%' }}
                scrollWheelZoom
            >
                <FitRoute points={points} />
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {route.length > 1 && (
                    <Polyline
                        positions={route}
                        pathOptions={{
                            color: '#2563EB',
                            weight: 5,
                            opacity: 0.8,
                        }}
                    />
                )}
                {points[0] && (
                    <CircleMarker
                        center={center}
                        radius={9}
                        pathOptions={{
                            color: '#FFF',
                            weight: 3,
                            fillColor: '#16A34A',
                            fillOpacity: 1,
                        }}
                    >
                        <Popup>
                            Mulai ·{' '}
                            {new Date(points[0].recorded_at).toLocaleTimeString(
                                'id-ID',
                            )}
                        </Popup>
                    </CircleMarker>
                )}
                {points.length > 1 && (
                    <CircleMarker
                        center={[
                            points.at(-1)!.latitude,
                            points.at(-1)!.longitude,
                        ]}
                        radius={9}
                        pathOptions={{
                            color: '#FFF',
                            weight: 3,
                            fillColor: '#DC2626',
                            fillOpacity: 1,
                        }}
                    >
                        <Popup>
                            Selesai ·{' '}
                            {new Date(
                                points.at(-1)!.recorded_at,
                            ).toLocaleTimeString('id-ID')}
                        </Popup>
                    </CircleMarker>
                )}
            </MapContainer>
        </div>
    );
}
