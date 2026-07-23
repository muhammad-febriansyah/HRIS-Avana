import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { useEffect, useMemo } from 'react';
import {
    Circle,
    MapContainer,
    Marker,
    Popup,
    TileLayer,
    Tooltip,
    useMap,
} from 'react-leaflet';
import type { LocationMapProps, MapPoint } from './location-map';

// Dropping `_getIconUrl` stops Leaflet from auto-prefixing its imagePath onto
// these already-resolved Vite asset URLs (which 404s the marker images).
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })
    ._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

/** Fits the map viewport to every provided point once, on mount/update. */
function FitBounds({ points }: { points: MapPoint[] }) {
    const map = useMap();

    useEffect(() => {
        if (points.length === 0) {
            return;
        }

        const bounds = L.latLngBounds(
            points.map((p) => [p.lat, p.lng] as [number, number]),
        );
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    }, [map, points]);

    return null;
}

/**
 * Browser-only Leaflet map. Split out from `location-map.tsx` because Leaflet
 * touches `window` at import time, which crashes server-side rendering. The
 * wrapper lazy-loads this chunk only after the client has mounted.
 */
export default function LocationMapClient({
    points,
    area,
    height = 300,
    zoom = 16,
    fit = false,
    labels = false,
}: LocationMapProps) {
    const center = useMemo<[number, number]>(() => {
        if (area) {
            return [area.lat, area.lng];
        }

        if (points.length > 0) {
            return [points[0].lat, points[0].lng];
        }

        return [-6.1754, 106.8272];
    }, [area, points]);

    const frame: React.CSSProperties = {
        height,
        borderRadius: 10,
        overflow: 'hidden',
        border: '1px solid #E2E8F0',
    };

    return (
        <div style={frame}>
            <MapContainer
                center={center}
                zoom={zoom}
                style={{ height: '100%', width: '100%' }}
                scrollWheelZoom
            >
                {fit && <FitBounds points={points} />}
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {area && area.radius > 0 && (
                    <Circle
                        center={[area.lat, area.lng]}
                        radius={area.radius}
                        pathOptions={{
                            color: '#2563EB',
                            fillColor: '#3B82F6',
                            fillOpacity: 0.12,
                        }}
                    />
                )}
                {points.map((point, index) => (
                    <Marker
                        key={`${point.lat},${point.lng},${index}`}
                        position={[point.lat, point.lng]}
                    >
                        {point.label &&
                            (labels ? (
                                <Tooltip permanent direction="top">
                                    {point.label}
                                </Tooltip>
                            ) : (
                                <Popup>{point.label}</Popup>
                            ))}
                    </Marker>
                ))}
            </MapContainer>
        </div>
    );
}
