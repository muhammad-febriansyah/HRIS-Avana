import { useEffect, useMemo, useRef, useState } from 'react';
import { loadGoogleMaps } from '@/lib/google-maps';
import type { LocationMapProps, MapPoint } from './location-map';

type MapOverlay = {
    setMap?: (map: google.maps.Map | null) => void;
    close?: () => void;
};

function boundsForPoints(
    maps: typeof google,
    points: MapPoint[],
): google.maps.LatLngBounds {
    const bounds = new maps.maps.LatLngBounds();

    points.forEach((point) =>
        bounds.extend({ lat: point.lat, lng: point.lng }),
    );

    return bounds;
}

export default function LocationMapClient({
    points,
    area,
    height = 300,
    zoom = 16,
    fit = false,
    labels = false,
}: LocationMapProps) {
    const mapElement = useRef<HTMLDivElement>(null);
    const overlaysRef = useRef<MapOverlay[]>([]);
    const [map, setMap] = useState<google.maps.Map | null>(null);
    const [error, setError] = useState<string | null>(null);
    const center = useMemo(() => {
        if (area) {
            return { lat: area.lat, lng: area.lng };
        }

        return points[0]
            ? { lat: points[0].lat, lng: points[0].lng }
            : { lat: -6.1754, lng: 106.8272 };
    }, [area, points]);

    useEffect(() => {
        let active = true;

        void loadGoogleMaps()
            .then((maps) => {
                if (!active || !mapElement.current) {
                    return;
                }

                setMap(
                    new maps.maps.Map(mapElement.current, {
                        center,
                        zoom,
                        mapTypeControl: false,
                        streetViewControl: false,
                        fullscreenControl: false,
                    }),
                );
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
            overlaysRef.current.forEach((overlay) => {
                overlay.setMap?.(null);
                overlay.close?.();
            });
            overlaysRef.current = [];
        };
        // Map instance initializes once per mounted component.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!map || !window.google?.maps) {
            return;
        }

        overlaysRef.current.forEach((overlay) => {
            overlay.setMap?.(null);
            overlay.close?.();
        });
        overlaysRef.current = [];

        if (fit && points.length > 0) {
            map.fitBounds(boundsForPoints(window.google, points), 40);
        } else {
            map.setCenter(center);
            map.setZoom(zoom);
        }

        if (area && area.radius > 0) {
            overlaysRef.current.push(
                new google.maps.Circle({
                    map,
                    center: { lat: area.lat, lng: area.lng },
                    radius: area.radius,
                    strokeColor: '#2563EB',
                    fillColor: '#3B82F6',
                    fillOpacity: 0.12,
                }),
            );
        }

        const infoWindow = new google.maps.InfoWindow();

        points.forEach((point) => {
            const marker = new google.maps.Marker({
                map,
                position: { lat: point.lat, lng: point.lng },
                label: labels && point.label ? point.label : undefined,
            });

            if (point.label) {
                const content = document.createElement('div');
                const title = document.createElement('strong');
                title.textContent = point.label;
                title.style.display = 'block';
                title.style.marginBottom = '4px';
                content.append(title);

                point.details?.forEach((detail) => {
                    const row = document.createElement('div');
                    row.textContent = detail;
                    row.style.color = '#64748B';
                    row.style.fontSize = '12px';
                    content.append(row);
                });

                marker.addListener('click', () => {
                    infoWindow.setContent(content);
                    infoWindow.open({ map, anchor: marker });
                });
            }

            overlaysRef.current.push(marker);
        });

        overlaysRef.current.push(infoWindow);
    }, [area, center, fit, labels, map, points, zoom]);

    const frame: React.CSSProperties = {
        height,
        borderRadius: 10,
        overflow: 'hidden',
        border: '1px solid #E2E8F0',
    };

    return (
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
    );
}
