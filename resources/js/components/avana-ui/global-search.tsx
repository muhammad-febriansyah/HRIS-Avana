import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';

interface NavItem {
    id: string;
    label: string;
    href?: string;
    icon?: string;
    children?: NavItem[];
}

interface NavGroup {
    title?: string | null;
    items: NavItem[];
}

interface Hit {
    label: string;
    sub?: string | null;
    href: string;
    icon: string;
    group: string;
}

interface ServerRow {
    id: number;
    label: string;
    sub?: string | null;
    href: string;
}

/** Global quick-search palette wired to the topbar input (debounced). */
export function GlobalSearch({ nav }: { nav: NavGroup[] }) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const [server, setServer] = useState<{
        employees: ServerRow[];
        tenants: ServerRow[];
    }>({ employees: [], tenants: [] });

    const inputRef = useRef<HTMLInputElement>(null);
    const boxRef = useRef<HTMLDivElement>(null);

    // Flat, searchable list of navigable menu leaves.
    const menuLeaves = useMemo(() => {
        const out: { label: string; href: string; icon: string }[] = [];
        for (const group of nav) {
            for (const item of group.items) {
                if (item.href) {
                    out.push({
                        label: item.label,
                        href: item.href,
                        icon: item.icon ?? 'square',
                    });
                }
                for (const child of item.children ?? []) {
                    if (child.href) {
                        out.push({
                            label: child.label,
                            href: child.href,
                            icon: child.icon ?? 'chevron-right',
                        });
                    }
                }
            }
        }
        return out;
    }, [nav]);

    // Debounced server fetch for employees / tenants.
    useEffect(() => {
        const term = query.trim();
        if (term.length < 2) {
            setServer({ employees: [], tenants: [] });
            setLoading(false);
            return;
        }

        setLoading(true);
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`/avana/search?q=${encodeURIComponent(term)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((r) => (r.ok ? r.json() : { employees: [], tenants: [] }))
                .then((data) => {
                    setServer({
                        employees: data.employees ?? [],
                        tenants: data.tenants ?? [],
                    });
                    setLoading(false);
                })
                .catch(() => {
                    /* aborted or failed — ignore */
                });
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query]);

    const hits = useMemo<Hit[]>(() => {
        const term = query.trim().toLowerCase();
        const menu: Hit[] =
            term === ''
                ? []
                : menuLeaves
                      .filter((m) => m.label.toLowerCase().includes(term))
                      .slice(0, 6)
                      .map((m) => ({
                          label: m.label,
                          href: m.href,
                          icon: m.icon,
                          group: 'Menu',
                      }));

        const employees: Hit[] = server.employees.map((e) => ({
            label: e.label,
            sub: e.sub,
            href: e.href,
            icon: 'user',
            group: 'Karyawan',
        }));

        const tenants: Hit[] = server.tenants.map((t) => ({
            label: t.label,
            sub: t.sub,
            href: t.href,
            icon: 'building-2',
            group: 'Klien',
        }));

        return [...menu, ...employees, ...tenants];
    }, [query, menuLeaves, server]);

    useEffect(() => setActive(0), [hits.length]);

    // Cmd/Ctrl+K to focus the search.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
                setOpen(true);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Close on outside click.
    useEffect(() => {
        const onClick = (e: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    const go = (href: string) => {
        setOpen(false);
        setQuery('');
        router.visit(href);
    };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => Math.min(a + 1, hits.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => Math.max(a - 1, 0));
        } else if (e.key === 'Enter' && hits[active]) {
            e.preventDefault();
            go(hits[active].href);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    const showPanel = open && query.trim() !== '';

    let flatIndex = -1;

    return (
        <div
            ref={boxRef}
            style={{ position: 'relative', flex: 1, maxWidth: 420 }}
            className="avn-search"
        >
            <span
                style={{
                    position: 'absolute',
                    left: 13,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: C.faint,
                    display: 'flex',
                }}
            >
                <AIcon name="search" size={17} />
            </span>
            <input
                ref={inputRef}
                value={query}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
                placeholder="Cari karyawan, klien, menu…"
                style={{
                    width: '100%',
                    height: 40,
                    padding: '0 14px 0 40px',
                    background: C.surface,
                    border: '1px solid transparent',
                    borderRadius: 8,
                    fontSize: 13.5,
                    outline: 'none',
                }}
            />

            {showPanel && (
                <div
                    style={{
                        position: 'absolute',
                        top: 46,
                        left: 0,
                        right: 0,
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 10,
                        boxShadow: '0 12px 32px rgba(15,23,42,.14)',
                        maxHeight: 380,
                        overflowY: 'auto',
                        zIndex: 60,
                        padding: 6,
                    }}
                >
                    {hits.length === 0 ? (
                        <div
                            style={{
                                padding: '18px 12px',
                                textAlign: 'center',
                                fontSize: 13,
                                color: C.muted,
                            }}
                        >
                            {loading
                                ? 'Mencari…'
                                : `Tidak ada hasil untuk “${query.trim()}”.`}
                        </div>
                    ) : (
                        ['Menu', 'Karyawan', 'Klien'].map((group) => {
                            const rows = hits.filter((h) => h.group === group);
                            if (rows.length === 0) {
                                return null;
                            }
                            return (
                                <div key={group} style={{ marginBottom: 4 }}>
                                    <div
                                        style={{
                                            fontSize: 10.5,
                                            fontWeight: 600,
                                            letterSpacing: '.05em',
                                            color: C.faint,
                                            padding: '8px 10px 4px',
                                            textTransform: 'uppercase',
                                        }}
                                    >
                                        {group}
                                    </div>
                                    {rows.map((hit) => {
                                        flatIndex += 1;
                                        const on = flatIndex === active;
                                        const idx = flatIndex;
                                        return (
                                            <button
                                                key={hit.group + hit.href}
                                                type="button"
                                                onMouseEnter={() =>
                                                    setActive(idx)
                                                }
                                                onClick={() => go(hit.href)}
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                    width: '100%',
                                                    textAlign: 'left',
                                                    padding: '9px 10px',
                                                    borderRadius: 8,
                                                    border: 'none',
                                                    cursor: 'pointer',
                                                    background: on
                                                        ? 'rgba(47,84,201,.08)'
                                                        : 'transparent',
                                                }}
                                            >
                                                <AIcon
                                                    name={hit.icon}
                                                    size={16}
                                                    color={
                                                        on ? C.primary : C.muted
                                                    }
                                                />
                                                <span
                                                    style={{
                                                        display: 'flex',
                                                        flexDirection: 'column',
                                                        minWidth: 0,
                                                        flex: 1,
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            fontSize: 13.5,
                                                            color: C.text,
                                                            fontWeight: 500,
                                                            whiteSpace:
                                                                'nowrap',
                                                            overflow: 'hidden',
                                                            textOverflow:
                                                                'ellipsis',
                                                        }}
                                                    >
                                                        {hit.label}
                                                    </span>
                                                    {hit.sub && (
                                                        <span
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                                whiteSpace:
                                                                    'nowrap',
                                                                overflow:
                                                                    'hidden',
                                                                textOverflow:
                                                                    'ellipsis',
                                                            }}
                                                        >
                                                            {hit.sub}
                                                        </span>
                                                    )}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
}

export default GlobalSearch;
