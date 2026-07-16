import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { useEffect, useMemo, useState } from 'react';
import {
    Circle,
    MapContainer,
    Marker,
    Popup,
    TileLayer,
    Tooltip,
    useMap,
} from 'react-leaflet';

// Dropping `_getIconUrl` stops Leaflet from auto-prefixing its imagePath onto
// these already-resolved Vite asset URLs (which 404s the marker images).
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })
    ._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

export interface MapPoint {
    lat: number;
    lng: number;
    label?: string;
}

interface LocationMapProps {
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
 * Read-only Leaflet map used to visualize attendance points against a work
 * location's geofence. Auto-fits to show every point plus the area circle.
 */
export function LocationMap({
    points,
    area,
    height = 300,
    zoom = 16,
    fit = false,
    labels = false,
}: LocationMapProps) {
    const [mounted, setMounted] = useState(false);

    // Leaflet touches `window`, so defer the map to the client after hydration.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMounted(true), []);

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

    if (!mounted) {
        return (
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
    }

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

export default LocationMap;
