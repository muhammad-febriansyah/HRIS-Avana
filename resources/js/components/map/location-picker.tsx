import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { loadGoogleMaps } from '@/lib/google-maps';

interface LocationPickerProps {
    latitude: string;
    longitude: string;
    radiusMeter?: string;
    onChange: (coords: { latitude: string; longitude: string }) => void;
    height?: number;
}

const DEFAULT_CENTER = { lat: -6.1754, lng: 106.8272 };

function parseCoord(value: string): number | null {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : null;
}

export function LocationPicker({
    latitude,
    longitude,
    radiusMeter,
    onChange,
    height = 280,
}: LocationPickerProps) {
    const mapElement = useRef<HTMLDivElement>(null);
    const markerRef = useRef<google.maps.Marker | null>(null);
    const circleRef = useRef<google.maps.Circle | null>(null);
    const [map, setMap] = useState<google.maps.Map | null>(null);
    const [mounted, setMounted] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const lat = parseCoord(latitude);
    const lng = parseCoord(longitude);
    const hasPin = lat !== null && lng !== null;
    const position = useMemo(
        () => (hasPin ? { lat: lat!, lng: lng! } : DEFAULT_CENTER),
        [hasPin, lat, lng],
    );
    const circleRadius = Number.parseFloat(radiusMeter ?? '');

    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMounted(true), []);

    const emit = useCallback(
        (nextLat: number, nextLng: number) => {
            onChange({
                latitude: nextLat.toFixed(6),
                longitude: nextLng.toFixed(6),
            });
        },
        [onChange],
    );

    useEffect(() => {
        if (!mounted || !mapElement.current) {
            return;
        }

        let active = true;

        void loadGoogleMaps()
            .then((maps) => {
                if (!active || !mapElement.current) {
                    return;
                }

                const map = new maps.maps.Map(mapElement.current, {
                    center: position,
                    zoom: hasPin ? 16 : 12,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                });
                setMap(map);
                map.addListener('click', (event: google.maps.MapMouseEvent) => {
                    if (event.latLng) {
                        emit(event.latLng.lat(), event.latLng.lng());
                    }
                });
            })
            .catch((reason: unknown) => {
                if (active) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Google Maps gagal dimuat.',
                    );
                }
            });

        return () => {
            active = false;
            markerRef.current?.setMap(null);
            circleRef.current?.setMap(null);
            markerRef.current = null;
            circleRef.current = null;
        };
        // Map instance initializes once after browser mount.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mounted]);

    useEffect(() => {
        if (!map || !window.google?.maps) {
            return;
        }

        map.setCenter(position);
        map.setZoom(hasPin ? 16 : 12);
        markerRef.current?.setMap(null);
        circleRef.current?.setMap(null);

        if (!hasPin) {
            return;
        }

        const marker = new google.maps.Marker({
            map,
            position,
            draggable: true,
        });
        marker.addListener('dragend', () => {
            const next = marker.getPosition();

            if (next) {
                emit(next.lat(), next.lng());
            }
        });
        markerRef.current = marker;

        if (Number.isFinite(circleRadius) && circleRadius > 0) {
            circleRef.current = new google.maps.Circle({
                map,
                center: position,
                radius: circleRadius,
                strokeColor: '#2563EB',
                fillColor: '#3B82F6',
                fillOpacity: 0.12,
            });
        }
    }, [circleRadius, emit, hasPin, map, position]);

    const useMyLocation = () => {
        if (!navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition((current) =>
            emit(current.coords.latitude, current.coords.longitude),
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
            <div style={{ ...frame, background: '#F1F5F9' }}>Memuat peta…</div>
        );
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={frame}>
                {error ? (
                    <div className="flex h-full items-center justify-center bg-slate-100 px-4 text-center text-sm text-slate-500">
                        {error}
                    </div>
                ) : (
                    <div
                        ref={mapElement}
                        style={{ height: '100%', width: '100%' }}
                    />
                )}
            </div>
            <div className="flex items-center justify-between text-xs text-slate-500">
                <span>
                    {hasPin
                        ? `Titik: ${lat.toFixed(6)}, ${lng.toFixed(6)}`
                        : 'Klik peta untuk menaruh titik lokasi.'}
                </span>
                <button
                    type="button"
                    onClick={useMyLocation}
                    className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-700"
                >
                    Pakai lokasi saya
                </button>
            </div>
        </div>
    );
}

export default LocationPicker;
