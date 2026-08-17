import 'leaflet/dist/leaflet.css';
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css';
import L from 'leaflet';
import { useEffect, useMemo, useRef } from 'react';
import {
    CircleMarker,
    MapContainer,
    Popup,
    TileLayer,
    Tooltip,
    useMap,
} from 'react-leaflet';
import {
    FullscreenButton,
    FullscreenSync,
    useFullscreenToggle,
} from './fullscreen-control.client';
import { RoadRoute, sampleWaypoints } from './road-route.client';
import type { LiveTrackingEmployee } from './tracking-map';

interface TrackingMapClientProps {
    employees: LiveTrackingEmployee[];
    selectedId: number | null;
    onSelect: (employeeId: number) => void;
    height: number;
}

function MapViewport({
    employees,
    selectedId,
}: {
    employees: LiveTrackingEmployee[];
    selectedId: number | null;
}) {
    const map = useMap();

    useEffect(() => {
        const selected = employees.find(
            (employee) => employee.employee_id === selectedId,
        );

        if (selected?.latitude != null && selected.longitude != null) {
            map.flyTo([selected.latitude, selected.longitude], 16, {
                duration: 0.5,
            });

            return;
        }

        const coordinates = employees
            .filter(
                (employee) =>
                    employee.latitude != null && employee.longitude != null,
            )
            .map(
                (employee) =>
                    [employee.latitude!, employee.longitude!] as [
                        number,
                        number,
                    ],
            );

        if (coordinates.length > 0) {
            map.fitBounds(L.latLngBounds(coordinates), {
                padding: [48, 48],
                maxZoom: 15,
            });
        }
    }, [employees, map, selectedId]);

    return null;
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
    const containerRef = useRef<HTMLDivElement>(null);
    const { isFullscreen, toggle } = useFullscreenToggle(containerRef);

    return (
        <div
            ref={containerRef}
            className="relative overflow-hidden rounded-xl border border-slate-200 bg-white"
            style={{ height: isFullscreen ? '100vh' : height }}
        >
            <MapContainer
                center={[-6.1754, 106.8272]}
                zoom={11}
                style={{ height: '100%', width: '100%' }}
                scrollWheelZoom
            >
                <MapViewport employees={visible} selectedId={selectedId} />
                <FullscreenSync isFullscreen={isFullscreen} />
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {/* Road-snapped trail for the selected employee's last 30
                    minutes. No endpoint pins — the employee's own coloured
                    dot below already marks their current position, and a
                    second marker on the same spot would just be confusing. */}
                {selectedWaypoints.length > 1 && (
                    <RoadRoute
                        waypoints={selectedWaypoints}
                        color={markerColor(selectedEmployee!.status)}
                        showEndpoints={false}
                    />
                )}
                {visible.map((employee) => {
                    const selected = employee.employee_id === selectedId;

                    return (
                        <CircleMarker
                            key={employee.employee_id}
                            center={[employee.latitude!, employee.longitude!]}
                            radius={selected ? 11 : 8}
                            pathOptions={{
                                color: '#FFFFFF',
                                weight: selected ? 4 : 3,
                                fillColor: markerColor(employee.status),
                                fillOpacity: 1,
                            }}
                            eventHandlers={{
                                click: () => onSelect(employee.employee_id),
                            }}
                        >
                            <Tooltip direction="top" offset={[0, -8]}>
                                {employee.name}
                            </Tooltip>
                            <Popup>
                                <strong>{employee.name}</strong>
                                <br />
                                {employee.department ?? 'Tanpa departemen'}
                            </Popup>
                        </CircleMarker>
                    );
                })}
            </MapContainer>
            <FullscreenButton isFullscreen={isFullscreen} onToggle={toggle} />
        </div>
    );
}
