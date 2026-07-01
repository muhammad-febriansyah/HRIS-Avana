import { useEffect, useMemo, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';

export interface SelectOption {
    value: string;
    label: string;
}

interface SearchableSelectProps {
    value: string;
    options: SelectOption[];
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    allowClear?: boolean;
    style?: React.CSSProperties;
    /** Show the search box only past this many options (default 8). */
    searchThreshold?: number;
}

/**
 * A lightweight, dependency-free select2-style combobox: click to open a panel
 * with a search box that filters options. Falls back to a plain list for short
 * option sets. Keyboard: type to filter, Esc to close, Enter to pick the first.
 */
export function SearchableSelect({
    value,
    options,
    onChange,
    placeholder = 'Pilih…',
    searchPlaceholder = 'Cari…',
    disabled = false,
    allowClear = false,
    style,
    searchThreshold = 8,
}: SearchableSelectProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const boxRef = useRef<HTMLDivElement>(null);

    const selected = options.find((o) => o.value === value) ?? null;
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
        onChange(v);
        setOpen(false);
        setQuery('');
    };

    return (
        <div ref={boxRef} style={{ position: 'relative', ...style }}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((o) => !o)}
                style={{
                    width: '100%',
                    height: 40,
                    padding: '0 34px 0 12px',
                    border: `1px solid ${open ? C.primary : C.border}`,
                    borderRadius: 8,
                    background: disabled ? '#F1F5F9' : '#fff',
                    fontSize: 13,
                    color: selected ? C.text : C.faint,
                    textAlign: 'left',
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    position: 'relative',
                    whiteSpace: 'nowrap',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                }}
            >
                {selected ? selected.label : placeholder}
                <span style={{ position: 'absolute', right: 10, top: '50%', transform: 'translateY(-50%)', display: 'inline-flex', gap: 4 }}>
                    {allowClear && selected && (
                        <span
                            onClick={(e) => {
                                e.stopPropagation();
                                pick('');
                            }}
                            style={{ cursor: 'pointer', color: C.faint }}
                        >
                            <AIcon name="x" size={14} color={C.faint} />
                        </span>
                    )}
                    <AIcon name="chevrons-up-down" size={14} color={C.faint} />
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
                        <div style={{ padding: 8, borderBottom: `1px solid ${C.line}` }}>
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
                                style={{ width: '100%', height: 34, padding: '0 10px', border: `1px solid ${C.border}`, borderRadius: 6, fontSize: 13, outline: 'none' }}
                            />
                        </div>
                    )}
                    <div style={{ maxHeight: 240, overflowY: 'auto' }}>
                        {filtered.length === 0 && (
                            <div style={{ padding: '12px 14px', fontSize: 12.5, color: C.faint }}>Tidak ada hasil.</div>
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
                                    background: o.value === value ? '#EEF2FF' : '#fff',
                                    color: o.value === value ? C.primary : C.text,
                                    fontWeight: o.value === value ? 600 : 400,
                                    cursor: 'pointer',
                                }}
                            >
                                {o.label}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
