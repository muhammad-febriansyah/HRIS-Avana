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
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';

// Vite bundles Leaflet's marker images under hashed URLs; wire them back so the
// default pin renders instead of a broken image. Dropping `_getIconUrl` stops
// Leaflet from auto-prefixing its imagePath onto these already-resolved URLs.
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })
    ._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

/** Jakarta (Monas) — a sensible default when no pin has been set yet. */
const DEFAULT_CENTER: [number, number] = [-6.1754, 106.8272];

interface LocationPickerProps {
    latitude: string;
    longitude: string;
    radiusMeter?: string;
    onChange: (coords: { latitude: string; longitude: string }) => void;
    height?: number;
}

function parseCoord(value: string): number | null {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : null;
}

/** Places / moves the pin on every map click. */
function ClickCapture({
    onPick,
}: {
    onPick: (lat: number, lng: number) => void;
}) {
    useMapEvents({
        click(event) {
            onPick(event.latlng.lat, event.latlng.lng);
        },
    });

    return null;
}

/** Recenters the map when the pin jumps from outside the map (e.g. geolocate). */
function Recenter({ position }: { position: [number, number] }) {
    const map = useMap();

    useEffect(() => {
        map.setView(position, map.getZoom());
    }, [map, position]);

    return null;
}

/**
 * Interactive Leaflet map for picking a work-location pin. Click the map or drag
 * the marker to set the coordinate; the radius circle previews the geofence.
 */
export function LocationPicker({
    latitude,
    longitude,
    radiusMeter,
    onChange,
    height = 280,
}: LocationPickerProps) {
    const [mounted, setMounted] = useState(false);

    // Leaflet touches `window`, so defer the map to the client after hydration.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMounted(true), []);

    const lat = parseCoord(latitude);
    const lng = parseCoord(longitude);
    const hasPin = lat !== null && lng !== null;
    const position = useMemo<[number, number]>(
        () => (hasPin ? [lat, lng] : DEFAULT_CENTER),
        [hasPin, lat, lng],
    );

    const radius = Number.parseFloat(radiusMeter ?? '');
    const circleRadius = Number.isFinite(radius) && radius > 0 ? radius : 0;

    const emit = (nextLat: number, nextLng: number) =>
        onChange({
            latitude: nextLat.toFixed(6),
            longitude: nextLng.toFixed(6),
        });

    const useMyLocation = () => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition((pos) =>
            emit(pos.coords.latitude, pos.coords.longitude),
        );
    };

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
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={frame}>
                <MapContainer
                    center={position}
                    zoom={hasPin ? 16 : 12}
                    style={{ height: '100%', width: '100%' }}
                    scrollWheelZoom
                >
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    <ClickCapture onPick={emit} />
                    {hasPin && (
                        <>
                            <Recenter position={position} />
                            <Marker
                                position={position}
                                draggable
                                eventHandlers={{
                                    dragend(event) {
                                        const { lat: dLat, lng: dLng } =
                                            event.target.getLatLng();
                                        emit(dLat, dLng);
                                    },
                                }}
                            />
                            {circleRadius > 0 && (
                                <Circle
                                    center={position}
                                    radius={circleRadius}
                                    pathOptions={{
                                        color: '#2563EB',
                                        fillColor: '#3B82F6',
                                        fillOpacity: 0.12,
                                    }}
                                />
                            )}
                        </>
                    )}
                </MapContainer>
            </div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    fontSize: 12,
                    color: '#64748B',
                }}
            >
                <span>
                    {hasPin
                        ? `Titik: ${lat.toFixed(6)}, ${lng.toFixed(6)}`
                        : 'Klik peta untuk menaruh titik lokasi.'}
                </span>
                <button
                    type="button"
                    onClick={useMyLocation}
                    style={{
                        border: '1px solid #CBD5E1',
                        background: '#fff',
                        borderRadius: 7,
                        padding: '5px 10px',
                        fontSize: 12,
                        color: '#334155',
                        cursor: 'pointer',
                    }}
                >
                    Pakai lokasi saya
                </button>
            </div>
        </div>
    );
}

export default LocationPicker;
