import { motion, useReducedMotion } from 'motion/react';
import type { RefObject } from 'react';
import { useEffect, useId, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * A curved line between two elements with a light travelling along it —
 * adapted from Magic UI's Animated Beam for this codebase (typed props, the
 * project's `motion` package, brand colours, and a reduced-motion path that
 * leaves the line drawn but still).
 *
 * Both elements and the container are measured on mount and on resize, so the
 * path follows the real layout instead of hard-coded coordinates. Nothing is
 * rendered until the first measurement, which keeps the server-rendered markup
 * and the first client render identical.
 */
export function AnimatedBeam({
    containerRef,
    fromRef,
    toRef,
    curvature = 0,
    reverse = false,
    duration = 4,
    delay = 0,
    pathColor = '#D6DFF1',
    pathWidth = 2,
    gradientStartColor = '#2F54C9',
    gradientStopColor = '#6E9BE6',
    className,
}: {
    containerRef: RefObject<HTMLElement | null>;
    fromRef: RefObject<HTMLElement | null>;
    toRef: RefObject<HTMLElement | null>;
    /** Bend of the curve in pixels; negative bends upward. */
    curvature?: number;
    /** Send the light from `toRef` to `fromRef` instead. */
    reverse?: boolean;
    duration?: number;
    delay?: number;
    pathColor?: string;
    pathWidth?: number;
    gradientStartColor?: string;
    gradientStopColor?: string;
    className?: string;
}) {
    const id = useId();
    const reduceMotion = useReducedMotion();
    const [path, setPath] = useState('');
    const [size, setSize] = useState({ width: 0, height: 0 });

    useEffect(() => {
        const measure = () => {
            const container = containerRef.current;
            const from = fromRef.current;
            const to = toRef.current;

            if (!container || !from || !to) {
                return;
            }

            const box = container.getBoundingClientRect();
            const fromBox = from.getBoundingClientRect();
            const toBox = to.getBoundingClientRect();

            const startX = fromBox.left - box.left + fromBox.width / 2;
            const startY = fromBox.top - box.top + fromBox.height / 2;
            const endX = toBox.left - box.left + toBox.width / 2;
            const endY = toBox.top - box.top + toBox.height / 2;

            setSize({ width: box.width, height: box.height });
            setPath(
                `M ${startX},${startY} Q ${(startX + endX) / 2},${
                    (startY + endY) / 2 - curvature
                } ${endX},${endY}`,
            );
        };

        measure();

        const observer = new ResizeObserver(measure);

        if (containerRef.current) {
            observer.observe(containerRef.current);
        }

        window.addEventListener('resize', measure);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', measure);
        };
    }, [containerRef, fromRef, toRef, curvature]);

    if (!path) {
        return null;
    }

    const gradientId = `beam-${id}`;

    return (
        <svg
            aria-hidden
            fill="none"
            width={size.width}
            height={size.height}
            viewBox={`0 0 ${size.width} ${size.height}`}
            className={cn(
                'pointer-events-none absolute top-0 left-0 transform-gpu',
                className,
            )}
        >
            <path
                d={path}
                stroke={pathColor}
                strokeWidth={pathWidth}
                strokeOpacity={0.9}
                strokeLinecap="round"
            />
            <path
                d={path}
                strokeWidth={pathWidth}
                stroke={`url(#${gradientId})`}
                strokeOpacity={1}
                strokeLinecap="round"
            />
            <defs>
                <motion.linearGradient
                    id={gradientId}
                    gradientUnits="userSpaceOnUse"
                    // Reduced motion keeps the gradient parked over the line
                    // instead of running along it.
                    initial={{
                        x1: '0%',
                        x2: reduceMotion ? '100%' : '0%',
                        y1: '0%',
                        y2: '0%',
                    }}
                    animate={
                        reduceMotion
                            ? undefined
                            : {
                                  x1: reverse
                                      ? ['90%', '-10%']
                                      : ['10%', '110%'],
                                  x2: reverse ? ['100%', '0%'] : ['0%', '100%'],
                                  y1: ['0%', '0%'],
                                  y2: ['0%', '0%'],
                              }
                    }
                    transition={{
                        delay,
                        duration,
                        ease: 'linear',
                        repeat: Infinity,
                        repeatDelay: 0.6,
                    }}
                >
                    <stop stopColor={gradientStartColor} stopOpacity="0" />
                    <stop stopColor={gradientStartColor} />
                    <stop offset="32.5%" stopColor={gradientStopColor} />
                    <stop
                        offset="100%"
                        stopColor={gradientStopColor}
                        stopOpacity="0"
                    />
                </motion.linearGradient>
            </defs>
        </svg>
    );
}
