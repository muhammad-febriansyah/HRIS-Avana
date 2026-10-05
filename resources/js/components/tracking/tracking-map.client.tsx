import { useEffect, useMemo, useRef, useState } from 'react';
import { loadGoogleMaps } from '@/lib/google-maps';
import {
    FullscreenButton,
    useFullscreenToggle,
} from './fullscreen-control.client';
import { RoadRoute, sampleWaypoints } from './road-route.client';
import type { LiveTrackingEmployee } from './tracking-map';

type MapOverlay = {
    setMap?: (map: google.maps.Map | null) => void;
    close?: () => void;
};

interface TrackingMapClientProps {
    employees: LiveTrackingEmployee[];
    selectedId: number | null;
    onSelect: (employeeId: number) => void;
    height: number;
}

function markerColor(status: LiveTrackingEmployee['status']): string {
    if (status === 'moving') {
        return '#16A34A';
    }

    if (status === 'active') {
        return '#22C55E';
    }

    if (status === 'stale') {
        return '#D97706';
    }

    return '#64748B';
}

export default function TrackingMapClient({
    employees,
    selectedId,
    onSelect,
    height,
}: TrackingMapClientProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapElement = useRef<HTMLDivElement>(null);
    const mapRef = useRef<google.maps.Map | null>(null);
    const overlaysRef = useRef<MapOverlay[]>([]);
    const [map, setMap] = useState<google.maps.Map | null>(null);
    const { isFullscreen, toggle } = useFullscreenToggle(containerRef);
    const visible = useMemo(
        () =>
            employees.filter(
                (employee) =>
                    employee.latitude != null && employee.longitude != null,
            ),
        [employees],
    );
    const selectedEmployee = visible.find(
        (employee) => employee.employee_id === selectedId,
    );
    const selectedWaypoints = useMemo(
        () =>
            selectedEmployee && selectedEmployee.trail.length > 1
                ? sampleWaypoints(selectedEmployee.trail, 8)
                : [],
        [selectedEmployee],
    );

    useEffect(() => {
        let active = true;

        void loadGoogleMaps().then((maps) => {
            if (!active || !mapElement.current) {
                return;
            }

            const instance = new maps.maps.Map(mapElement.current, {
                center: { lat: -6.1754, lng: 106.8272 },
                zoom: 11,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });
            mapRef.current = instance;
            setMap(instance);
        });

        return () => {
            active = false;
            overlaysRef.current.forEach((overlay) => {
                overlay.setMap?.(null);
                overlay.close?.();
            });
            overlaysRef.current = [];
            mapRef.current = null;
        };
    }, []);

    useEffect(() => {
        if (!map) {
            return;
        }

        overlaysRef.current.forEach((overlay) => {
            overlay.setMap?.(null);
            overlay.close?.();
        });
        overlaysRef.current = [];

        const bounds = new google.maps.LatLngBounds();

        visible.forEach((employee) => {
            const position = {
                lat: employee.latitude!,
                lng: employee.longitude!,
            };
            bounds.extend(position);
            const selected = employee.employee_id === selectedId;
            const marker = new google.maps.Marker({
                map,
                position,
                title: employee.name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: selected ? 11 : 8,
                    fillColor: markerColor(employee.status),
                    fillOpacity: 1,
                    strokeColor: '#FFFFFF',
                    strokeWeight: selected ? 4 : 3,
                },
            });
            marker.addListener('click', () => onSelect(employee.employee_id));
            const infoContent = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = employee.name;
            infoContent.append(name, document.createElement('br'));
            infoContent.append(
                document.createTextNode(
                    employee.department ?? 'Tanpa departemen',
                ),
            );
            const infoWindow = new google.maps.InfoWindow({
                content: infoContent,
            });
            marker.addListener('mouseover', () =>
                infoWindow.open({ map, anchor: marker }),
            );
            marker.addListener('mouseout', () => infoWindow.close());
            overlaysRef.current.push(marker, infoWindow);
        });

        if (selectedId) {
            const selected = visible.find(
                (employee) => employee.employee_id === selectedId,
            );

            if (selected?.latitude != null && selected.longitude != null) {
                map.panTo({ lat: selected.latitude, lng: selected.longitude });
                map.setZoom(16);

                return;
            }
        }

        if (visible.length > 0) {
            map.fitBounds(bounds, 48);

            if (visible.length === 1) {
                map.setZoom(15);
            }
        }
    }, [map, onSelect, selectedId, visible]);

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
            {map && selectedWaypoints.length > 1 && (
                <RoadRoute
                    map={map}
                    waypoints={selectedWaypoints}
                    color={markerColor(selectedEmployee!.status)}
                    showEndpoints={false}
                />
            )}
            <FullscreenButton isFullscreen={isFullscreen} onToggle={toggle} />
        </div>
    );
}
