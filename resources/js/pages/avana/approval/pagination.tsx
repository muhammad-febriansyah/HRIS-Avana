import { AIcon, C } from '@/lib/avana';
import type { PageMeta } from './types';

interface PaginationProps {
    meta: PageMeta;
    /** Label for the unit being counted, e.g. "pengajuan". */
    unit: string;
    onPage: (page: number) => void;
    onPerPage?: (perPage: number) => void;
    perPageOptions?: number[];
}

const stepStyle = (disabled: boolean) => ({
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    height: 32,
    minWidth: 32,
    padding: '0 10px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    background: '#fff',
    fontSize: 12.5,
    fontWeight: 500,
    color: disabled ? C.faint : C.text,
    cursor: disabled ? 'default' : 'pointer',
    opacity: disabled ? 0.55 : 1,
});

/**
 * Page numbers to render: always the first and last page, plus a window
 * around the current one. Everything else collapses to an ellipsis so the
 * control stays one line wide however many pages there are.
 */
function pageWindow(current: number, last: number): (number | 'gap')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set<number>([1, last, current, current - 1, current + 1]);

    const sorted = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    return sorted.flatMap((page, index) =>
        index > 0 && page - sorted[index - 1] > 1
            ? (['gap', page] as (number | 'gap')[])
            : [page],
    );
}

/** Row counter, page size picker and page stepper shown under a table. */
export function Pagination({
    meta,
    unit,
    onPage,
    onPerPage,
    perPageOptions = [10, 25, 50],
}: PaginationProps) {
    if (meta.total === 0) {
        return null;
    }

    const { current_page: current, last_page: last } = meta;

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                flexWrap: 'wrap',
                gap: 12,
                padding: '12px 18px',
                borderTop: `1px solid ${C.line}`,
                background: '#FCFDFF',
            }}
        >
            <div
                style={{
                    fontSize: 12.5,
                    color: C.muted,
                    fontVariantNumeric: 'tabular-nums',
                }}
            >
                Menampilkan{' '}
                <strong style={{ color: C.text }}>{meta.from}</strong>–
                <strong style={{ color: C.text }}>{meta.to}</strong> dari{' '}
                <strong style={{ color: C.text }}>{meta.total}</strong> {unit}
            </div>

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                {onPerPage && (
                    <label
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            color: C.muted,
                        }}
                    >
                        Baris
                        <select
                            value={meta.per_page}
                            onChange={(event) =>
                                onPerPage(Number(event.target.value))
                            }
                            style={{
                                height: 32,
                                padding: '0 8px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 12.5,
                                color: C.text,
                                background: '#fff',
                                cursor: 'pointer',
                            }}
                        >
                            {perPageOptions.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <button
                        type="button"
                        aria-label="Halaman sebelumnya"
                        disabled={current <= 1}
                        onClick={() => onPage(current - 1)}
                        style={stepStyle(current <= 1)}
                    >
                        <AIcon name="chevron-left" size={14} />
                    </button>

                    {pageWindow(current, last).map((page, index) =>
                        page === 'gap' ? (
                            <span
                                key={`gap-${index}`}
                                style={{
                                    fontSize: 12.5,
                                    color: C.faint,
                                    padding: '0 2px',
                                }}
                            >
                                …
                            </span>
                        ) : (
                            <button
                                key={page}
                                type="button"
                                aria-label={`Halaman ${page}`}
                                aria-current={
                                    page === current ? 'page' : undefined
                                }
                                onClick={() => onPage(page)}
                                style={{
                                    ...stepStyle(false),
                                    fontVariantNumeric: 'tabular-nums',
                                    ...(page === current
                                        ? {
                                              background: C.primary,
                                              borderColor: C.primary,
                                              color: '#fff',
                                              fontWeight: 600,
                                          }
                                        : {}),
                                }}
                            >
                                {page}
                            </button>
                        ),
                    )}

                    <button
                        type="button"
                        aria-label="Halaman berikutnya"
                        disabled={current >= last}
                        onClick={() => onPage(current + 1)}
                        style={stepStyle(current >= last)}
                    >
                        <AIcon name="chevron-right" size={14} />
                    </button>
                </div>
            </div>
        </div>
    );
}
