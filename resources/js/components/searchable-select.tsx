import { useEffect, useMemo, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';

export interface SelectOption {
    value: string;
    label: string;
}

interface SearchableSelectProps {
    value: string | string[];
    options: SelectOption[];
    onChange: (value: any) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    allowClear?: boolean;
    style?: React.CSSProperties;
    /** Show the search box only past this many options (default 8). */
    searchThreshold?: number;
    multiple?: boolean;
}

/**
 * A lightweight, dependency-free select2-style combobox: click to open a panel
 * with a search box that filters options. Falls back to a plain list for short
 * option sets. Keyboard: type to filter, Esc to close, Enter to pick the first.
 */
export function SearchableSelect({
    value,
    options = [],
    onChange,
    placeholder = 'Pilih…',
    searchPlaceholder = 'Cari…',
    disabled = false,
    allowClear = false,
    style,
    searchThreshold = 8,
    multiple = false,
}: SearchableSelectProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const boxRef = useRef<HTMLDivElement>(null);

    const selectedValues = Array.isArray(value) ? value : [value];
    const selected = options.filter((o) => selectedValues.includes(o.value));
    const showSearch = options.length > searchThreshold;

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        if (!q) {
            return options;
        }

        return options.filter((o) => o.label.toLowerCase().includes(q));
    }, [options, query]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onDocClick = (e: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', onDocClick);

        return () => document.removeEventListener('mousedown', onDocClick);
    }, [open]);

    const pick = (v: string) => {
        if (multiple) {
            onChange(
                selectedValues.includes(v)
                    ? selectedValues.filter(
                          (selectedValue) => selectedValue !== v,
                      )
                    : [...selectedValues, v],
            );

            return;
        }

        onChange(v);

        setOpen(false);
        setQuery('');
    };

    // The open state repaints the border, and the caller's field style supplies
    // one as the `border` shorthand. Mixing the two on one element is what React
    // warns about ("Updating a style property during rerender… when a
    // conflicting property is set"), so the incoming border is pulled out here
    // and a single resolved shorthand is written back.
    const {
        border: fieldBorder,
        borderColor: fieldBorderColor,
        ...fieldStyle
    } = style ?? {};

    const controlBorder = open
        ? `1px solid ${C.primary}`
        : (fieldBorder ??
          (fieldBorderColor !== undefined
              ? `1px solid ${fieldBorderColor}`
              : `1px solid ${C.border}`));

    return (
        <div ref={boxRef} style={{ position: 'relative', width: '100%' }}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((o) => !o)}
                style={{
                    width: '100%',
                    height: 42,
                    borderRadius: 8,
                    background: disabled ? '#F1F5F9' : '#fff',
                    fontSize: 13.5,
                    textAlign: 'left',
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    whiteSpace: 'nowrap',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    // The caller's field style (height, error state) is applied
                    // to the control itself, not a wrapper, so it never
                    // double-borders. Padding/color/border are forced last.
                    ...fieldStyle,
                    position: 'relative',
                    paddingTop: 0,
                    paddingBottom: 0,
                    paddingRight: 34,
                    paddingLeft: 13,
                    color:
                        selected.length > 0
                            ? ((style?.color as string | undefined) ?? C.text)
                            : C.faint,
                    border: controlBorder,
                }}
            >
                {multiple
                    ? selected.length > 0
                        ? `${selected.length} karyawan dipilih`
                        : placeholder
                    : (selected[0]?.label ?? placeholder)}
                <span
                    style={{
                        position: 'absolute',
                        right: 10,
                        top: '50%',
                        transform: 'translateY(-50%)',
                        display: 'inline-flex',
                        gap: 4,
                    }}
                >
                    {allowClear && selected.length > 0 && (
                        <span
                            onClick={(e) => {
                                e.stopPropagation();
                                onChange(multiple ? [] : '');
                                setOpen(false);
                            }}
                            style={{ cursor: 'pointer', color: C.faint }}
                        >
                            <AIcon name="x" size={14} color={C.faint} />
                        </span>
                    )}
                    <AIcon name="chevron-down" size={15} color={C.faint} />
                </span>
            </button>

            {open && (
                <div
                    style={{
                        position: 'absolute',
                        zIndex: 60,
                        top: 'calc(100% + 4px)',
                        left: 0,
                        right: 0,
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 8,
                        boxShadow: '0 12px 32px rgba(15,23,42,.14)',
                        overflow: 'hidden',
                    }}
                >
                    {showSearch && (
                        <div
                            style={{
                                padding: 8,
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <input
                                autoFocus
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Escape') {
                                        setOpen(false);
                                        setQuery('');
                                    }

                                    if (e.key === 'Enter' && filtered[0]) {
                                        e.preventDefault();
                                        pick(filtered[0].value);
                                    }
                                }}
                                placeholder={searchPlaceholder}
                                style={{
                                    width: '100%',
                                    height: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 6,
                                    fontSize: 13,
                                    outline: 'none',
                                }}
                            />
                        </div>
                    )}
                    <div style={{ maxHeight: 240, overflowY: 'auto' }}>
                        {filtered.length === 0 && (
                            <div
                                style={{
                                    padding: '12px 14px',
                                    fontSize: 12.5,
                                    color: C.faint,
                                }}
                            >
                                Tidak ada hasil.
                            </div>
                        )}
                        {filtered.map((o) => (
                            <button
                                key={o.value}
                                type="button"
                                onClick={() => pick(o.value)}
                                style={{
                                    display: 'block',
                                    width: '100%',
                                    textAlign: 'left',
                                    padding: '9px 14px',
                                    fontSize: 13,
                                    border: 'none',
                                    background: selectedValues.includes(o.value)
                                        ? '#EEF2FF'
                                        : '#fff',
                                    color: selectedValues.includes(o.value)
                                        ? C.primary
                                        : C.text,
                                    fontWeight: selectedValues.includes(o.value)
                                        ? 600
                                        : 400,
                                    cursor: 'pointer',
                                }}
                            >
                                {multiple && selectedValues.includes(o.value)
                                    ? '✓ '
                                    : ''}
                                {o.label}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
