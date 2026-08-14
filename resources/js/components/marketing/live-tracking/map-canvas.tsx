import { motion } from 'motion/react';
import { cn } from '@/lib/utils';

/**
 * Map mockup for the Live Tracking page.
 *
 * Drawn as inline SVG rather than a real Leaflet map: the marketing page only
 * needs to show what the screen looks like, and shipping a tile layer plus its
 * JavaScript for a decorative visual would cost far more than it returns. The
 * streets, the route and every marker are static demo geometry.
 *
 * The route draws itself once when it scrolls into view; `data-draw` lets the
 * reduced-motion rule in `app.css` show the finished line instead.
 */

export type MapMarker = {
    id: string;
    /** Percentage coordinates inside the canvas, 0–100. */
    x: number;
    y: number;
    label?: string;
    tone?: 'brand' | 'muted' | 'start' | 'end';
    pulse?: boolean;
};

const ROUTE_LIVE = 'M 96 300 L 180 300 L 180 216 L 300 216 L 300 150 L 396 150';
const ROUTE_HISTORY =
    'M 84 108 L 190 108 L 190 210 L 320 210 L 320 300 L 470 300 L 470 372 L 640 372';

const TONES: Record<string, string> = {
    brand: '#2F54C9',
    muted: '#8A93A6',
    start: '#16A34A',
    end: '#2F54C9',
};

/** Street grid + blocks, so the canvas reads as a map and not as a chart. */
function MapBackdrop() {
    return (
        <g aria-hidden>
            <rect width="720" height="440" fill="#E6ECF4" />
            {/* Blocks */}
            {[
                [24, 24, 150, 120],
                [210, 24, 190, 96],
                [430, 40, 120, 100],
                [580, 24, 116, 150],
                [24, 190, 120, 130],
                [190, 150, 160, 110],
                [400, 180, 150, 90],
                [24, 360, 180, 60],
                [250, 300, 200, 110],
                [500, 320, 196, 96],
            ].map(([x, y, w, h], i) => (
                <rect
                    key={i}
                    x={x}
                    y={y}
                    width={w}
                    height={h}
                    rx={6}
                    fill="#F3F6FB"
                />
            ))}
            {/* Green space + water, for a little map-like variation */}
            <rect
                x={566}
                y={190}
                width={130}
                height={100}
                rx={10}
                fill="#E3F0E7"
            />
            <path
                d="M 0 420 C 120 400, 220 448, 340 424 C 460 400, 560 440, 720 416 L 720 440 L 0 440 Z"
                fill="#DCE9F5"
            />
            {/* Streets */}
            {[80, 176, 286, 350, 470].map((y) => (
                <line
                    key={`h${y}`}
                    x1={0}
                    y1={y}
                    x2={720}
                    y2={y}
                    stroke="#FFFFFF"
                    strokeWidth={7}
                />
            ))}
            {[96, 190, 320, 470, 560].map((x) => (
                <line
                    key={`v${x}`}
                    x1={x}
                    y1={0}
                    x2={x}
                    y2={440}
                    stroke="#FFFFFF"
                    strokeWidth={7}
                />
            ))}
        </g>
    );
}

function Marker({ marker }: { marker: MapMarker }) {
    const color = TONES[marker.tone ?? 'brand'];

    return (
        <div
            className="absolute -translate-x-1/2 -translate-y-full"
            style={{ left: `${marker.x}%`, top: `${marker.y}%` }}
        >
            <div className="flex flex-col items-center">
                {marker.label && (
                    <span className="mb-1.5 rounded-full border border-[#E3E9F5] bg-white px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap text-[#0E1A3A] shadow-soft">
                        {marker.label}
                    </span>
                )}
                <span className="relative grid place-items-center">
                    {marker.pulse && (
                        <span
                            aria-hidden
                            className="avn-ping absolute h-8 w-8 rounded-full"
                            style={{ backgroundColor: `${color}33` }}
                        />
                    )}
                    <span
                        className="relative h-4 w-4 rounded-full border-[3px] border-white shadow-[0_2px_6px_rgba(14,26,58,0.35)]"
                        style={{ backgroundColor: color }}
                    />
                </span>
            </div>
        </div>
    );
}

export function MapCanvas({
    markers,
    variant = 'live',
    className,
}: {
    markers: MapMarker[];
    variant?: 'live' | 'history';
    className?: string;
}) {
    const route = variant === 'history' ? ROUTE_HISTORY : ROUTE_LIVE;

    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-xl bg-[#E6ECF4]',
                className,
            )}
        >
            <svg
                viewBox="0 0 720 440"
                preserveAspectRatio="xMidYMid slice"
                className="h-full w-full"
                role="img"
                aria-label={
                    variant === 'history'
                        ? 'Peta rute perjalanan satu sesi kerja'
                        : 'Peta posisi karyawan yang sedang menjalankan sesi kerja'
                }
            >
                <MapBackdrop />
                <motion.path
                    data-draw
                    d={route}
                    fill="none"
                    stroke="#2F54C9"
                    strokeWidth={5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeOpacity={0.9}
                    initial={{ pathLength: 0 }}
                    whileInView={{ pathLength: 1 }}
                    viewport={{ once: true, amount: 0.4 }}
                    transition={{ duration: 1.1, ease: 'easeInOut' }}
                />
            </svg>

            {markers.map((marker) => (
                <Marker key={marker.id} marker={marker} />
            ))}

            {/* Attribution, because the styling follows OpenStreetMap's look. */}
            <span className="absolute right-2 bottom-1.5 rounded bg-white/75 px-1.5 py-0.5 text-[9px] text-[#8A93A6]">
                Tampilan peta bergaya OpenStreetMap
            </span>
        </div>
    );
}
