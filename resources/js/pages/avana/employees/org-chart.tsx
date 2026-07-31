import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ReactFlow, { Background, Controls, Handle, Position } from 'reactflow';
import type { Edge, Node, NodeProps, NodeTypes } from 'reactflow';
import 'reactflow/dist/style.css';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { AIcon, C } from '@/lib/avana';
import { NO_MANAGER, UNASSIGNED_MANAGER } from './types';

interface OrgNode {
    id: number;
    name: string;
    employee_number: string;
    email: string | null;
    phone: string | null;
    position: string | null;
    department: string | null;
    branch: string | null;
    join_date: string | null;
    manager_id: number | null;
    is_top_approver: boolean;
    manager_name: string | null;
}

interface OrgChartProps {
    nodes: OrgNode[];
    /**
     * False on the employee self-service copy of this chart: the full profile
     * lives behind EmployeePolicy, so linking there would only 403.
     */
    canOpenProfile?: boolean;
    /**
     * Whether the reader may rearrange the reporting line from the drawer.
     * False on the employee self-service copy, which is read-only.
     */
    canManage?: boolean;
}

const COL_WIDTH = 220;
// Tall enough for the card plus the expand/collapse toggle hanging under it.
const ROW_HEIGHT = 178;
const NODE_WIDTH = 200;

/** Deterministic color-per-department palette. */
const DEPT_PALETTE = [
    '#2F54C9',
    '#6E9BE6',
    '#0E1A3A',
    '#D97706',
    '#16A34A',
    '#7C3AED',
    '#0891B2',
];

/** Up to two uppercase initials from a full name. */
function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean).slice(0, 2);

    return parts.map((word) => word.charAt(0).toUpperCase()).join('') || '?';
}

/** Pick a stable palette color from a string key (e.g. department name). */
function hashColor(key: string): string {
    let hash = 0;

    for (let i = 0; i < key.length; i++) {
        hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
    }

    return DEPT_PALETTE[hash % DEPT_PALETTE.length];
}

/** What a rendered card needs on top of the employee itself. */
interface CardData {
    node: OrgNode;
    /** People reporting straight to this employee. */
    directReports: number;
    /** Everyone further down the chain, excluding the direct reports. */
    indirectReports: number;
    isExpanded: boolean;
    isHighlighted: boolean;
    /**
     * Nobody to report to, and not the company head either. The top approver is
     * meant to sit at the root; anyone else floating there is missing a
     * manager, so only they are flagged — and only when the chart has more than
     * one root to begin with.
     */
    isUnattached: boolean;
    onToggle: (id: number) => void;
}

/**
 * One org-chart card: the employee, their headcount badge, and the toggle that
 * expands or collapses their branch. Clicking the card opens the detail sheet;
 * the toggle stops that click so the two never fight.
 */
function OrgCard({ data }: NodeProps<CardData>) {
    const {
        node,
        directReports,
        indirectReports,
        isExpanded,
        isHighlighted,
        isUnattached,
    } = data;
    const color = hashColor(node.department ?? node.name);

    return (
        <div style={{ width: NODE_WIDTH }}>
            <Handle
                type="target"
                position={Position.Top}
                style={{ opacity: 0 }}
            />

            <div
                style={{
                    border: `1px solid ${isHighlighted ? C.primary : C.border}`,
                    boxShadow: isHighlighted
                        ? `0 0 0 3px ${C.primary}22`
                        : '0 1px 2px rgba(15,26,58,.05)',
                    borderRadius: 10,
                    background: '#fff',
                    padding: 10,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 8,
                    textAlign: 'left',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 9,
                    }}
                >
                    <div
                        style={{
                            width: 34,
                            height: 34,
                            borderRadius: '50%',
                            flex: 'none',
                            background: color,
                            color: '#fff',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 12,
                            fontWeight: 600,
                        }}
                    >
                        {initials(node.name)}
                    </div>
                    <div style={{ minWidth: 0, lineHeight: 1.35 }}>
                        <div
                            style={{
                                fontWeight: 600,
                                fontSize: 12.5,
                                color: C.navy,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {node.name}
                        </div>
                        <div
                            style={{
                                fontSize: 11,
                                color: C.muted,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {node.position ?? '—'}
                        </div>
                    </div>
                </div>

                {isUnattached && (
                    <div
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                            fontSize: 10,
                            fontWeight: 600,
                            color: C.amber,
                            background: `${C.amber}14`,
                            borderRadius: 6,
                            padding: '3px 7px',
                        }}
                    >
                        <AIcon name="unlink" size={10} color={C.amber} />
                        Belum punya atasan
                    </div>
                )}

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 8,
                    }}
                >
                    {node.department ? (
                        <span
                            style={{
                                fontSize: 10,
                                fontWeight: 600,
                                color,
                                background: `${color}1a`,
                                padding: '2px 7px',
                                borderRadius: 999,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                maxWidth: 110,
                            }}
                        >
                            {node.department}
                        </span>
                    ) : (
                        <span />
                    )}
                    <span
                        title={`${directReports} bawahan langsung · ${indirectReports} tidak langsung`}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 4,
                            fontSize: 10.5,
                            fontWeight: 600,
                            color: C.muted,
                            border: `1px solid ${C.border}`,
                            borderRadius: 999,
                            padding: '2px 7px',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        <AIcon name="users" size={11} color={C.faint} />
                        {directReports} / {indirectReports}
                    </span>
                </div>
            </div>

            {directReports > 0 && (
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'center',
                        marginTop: 6,
                    }}
                >
                    <button
                        type="button"
                        className="nodrag nopan"
                        onClick={(event) => {
                            event.stopPropagation();
                            data.onToggle(node.id);
                        }}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                            border: `1px solid ${C.border}`,
                            borderRadius: 6,
                            background: C.surface,
                            padding: '3px 6px',
                            fontSize: 10.5,
                            fontWeight: 700,
                            letterSpacing: '.05em',
                            textTransform: 'uppercase',
                            color: isExpanded ? C.muted : C.primary,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon
                            name={isExpanded ? 'chevron-down' : 'chevron-right'}
                            size={13}
                            color={isExpanded ? C.muted : C.primary}
                        />
                        {isExpanded
                            ? 'Ciutkan'
                            : `Perluas (${directReports.toLocaleString('id-ID')})`}
                    </button>
                </div>
            )}

            <Handle
                type="source"
                position={Position.Bottom}
                style={{ opacity: 0 }}
            />
        </div>
    );
}

const NODE_TYPES: NodeTypes = { orgCard: OrgCard };

/** The reporting tree, indexed for lookups the chart does repeatedly. */
interface OrgTree {
    byId: Map<number, OrgNode>;
    childrenOf: Map<number, OrgNode[]>;
    roots: OrgNode[];
    /** Every descendant, not just the direct reports. */
    descendantsOf: Map<number, number>;
}

/**
 * Index the flat employee list into a reporting tree. An employee whose manager
 * is missing from the list (inactive, other tenant) is treated as a root, and a
 * cycle in the data is broken at the first repeat rather than hanging.
 */
function buildTree(nodes: OrgNode[]): OrgTree {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const childrenOf = new Map<number, OrgNode[]>();
    const roots: OrgNode[] = [];

    for (const node of nodes) {
        const parentId =
            node.manager_id !== null && byId.has(node.manager_id)
                ? node.manager_id
                : null;

        if (parentId === null || parentId === node.id) {
            roots.push(node);

            continue;
        }

        childrenOf.set(parentId, [...(childrenOf.get(parentId) ?? []), node]);
    }

    const descendantsOf = new Map<number, number>();
    const counting = new Set<number>();

    const countDescendants = (id: number): number => {
        const cached = descendantsOf.get(id);

        if (cached !== undefined) {
            return cached;
        }

        if (counting.has(id)) {
            return 0;
        }

        counting.add(id);
        const total = (childrenOf.get(id) ?? []).reduce(
            (sum, child) => sum + 1 + countDescendants(child.id),
            0,
        );
        counting.delete(id);
        descendantsOf.set(id, total);

        return total;
    };

    for (const node of nodes) {
        countDescendants(node.id);
    }

    return { byId, childrenOf, roots, descendantsOf };
}

/**
 * Tidy-tree layout over the *visible* part of the chart: leaves are packed
 * left-to-right, and every parent is centred above the span of its own
 * children — so sibling subtrees stay grouped and edges don't cross. A
 * collapsed node is placed but its branch is not walked.
 */
function layout(
    tree: OrgTree,
    expanded: Set<number>,
    highlighted: Set<number>,
    onToggle: (id: number) => void,
): { nodes: Node<CardData>[]; edges: Edge[] } {
    const flowNodes: Node<CardData>[] = [];
    const edges: Edge[] = [];
    const visited = new Set<number>();
    let nextLeafX = 0;

    /** Place a node (post-order) and return its horizontal centre in pixels. */
    const place = (node: OrgNode, depth: number): number => {
        visited.add(node.id);

        const children = (tree.childrenOf.get(node.id) ?? []).filter(
            (child) => !visited.has(child.id),
        );
        const isExpanded = expanded.has(node.id);

        let x: number;

        if (children.length === 0 || !isExpanded) {
            x = nextLeafX * COL_WIDTH;
            nextLeafX += 1;
        } else {
            const childXs = children.map((child) => place(child, depth + 1));
            x = (childXs[0] + childXs[childXs.length - 1]) / 2;
        }

        const directReports = (tree.childrenOf.get(node.id) ?? []).length;

        flowNodes.push({
            id: String(node.id),
            type: 'orgCard',
            position: { x, y: depth * ROW_HEIGHT },
            data: {
                node,
                directReports,
                indirectReports:
                    (tree.descendantsOf.get(node.id) ?? 0) - directReports,
                isExpanded,
                isHighlighted: highlighted.has(node.id),
                isUnattached:
                    node.manager_id === null &&
                    !node.is_top_approver &&
                    tree.roots.length > 1,
                onToggle,
            },
            style: { width: NODE_WIDTH, cursor: 'pointer' },
            sourcePosition: Position.Bottom,
            targetPosition: Position.Top,
        });

        if (
            node.manager_id !== null &&
            tree.byId.has(node.manager_id) &&
            visited.has(node.manager_id)
        ) {
            edges.push({
                id: `${node.manager_id}-${node.id}`,
                source: String(node.manager_id),
                target: String(node.id),
            });
        }

        return x;
    };

    for (const root of tree.roots) {
        place(root, 0);
    }

    return { nodes: flowNodes, edges };
}

/** Every ancestor of the given employee, nearest first. */
function ancestorsOf(tree: OrgTree, id: number): number[] {
    const chain: number[] = [];
    const seen = new Set<number>([id]);
    let current = tree.byId.get(id)?.manager_id ?? null;

    while (current !== null && tree.byId.has(current) && !seen.has(current)) {
        chain.push(current);
        seen.add(current);
        current = tree.byId.get(current)?.manager_id ?? null;
    }

    return chain;
}

/** A labelled field row inside the detail drawer. */
function DetailRow({ label, value }: { label: string; value: string | null }) {
    return (
        <div style={{ padding: '10px 0', borderBottom: `1px solid ${C.line}` }}>
            <div style={{ fontSize: 11, color: C.faint, marginBottom: 2 }}>
                {label}
            </div>
            <div
                style={{
                    fontSize: 13,
                    color: C.text,
                    wordBreak: 'break-word',
                }}
            >
                {value ?? '—'}
            </div>
        </div>
    );
}

/**
 * The reporting line, editable in place.
 *
 * Subordinates stay in the list rather than being hidden: picking one is how you
 * promote a deputy over the person they reported to, and the server handles it
 * by swapping the two. Hiding them made that move look impossible and left the
 * admin to work out that they had to detach one side first, in the right order.
 */
function ManagerPicker({
    employee,
    nodes,
    onSaved,
}: {
    employee: OrgNode;
    nodes: OrgNode[];
    /** Close the drawer once saved, so the chart redraws in plain view. */
    onSaved: () => void;
}) {
    const current = employee.is_top_approver
        ? NO_MANAGER
        : employee.manager_id !== null
          ? String(employee.manager_id)
          : UNASSIGNED_MANAGER;

    // Seeded once per employee: the drawer mounts this with key={employee.id},
    // so pointing it at somebody else remounts rather than needing a re-sync.
    const [choice, setChoice] = useState(current);
    const [saving, setSaving] = useState(false);

    /** Everyone under this employee — picking one of them means a swap. */
    const below = useMemo(() => {
        const found = new Set<number>([employee.id]);
        let grew = true;

        while (grew) {
            grew = false;

            for (const node of nodes) {
                if (
                    node.manager_id !== null &&
                    found.has(node.manager_id) &&
                    !found.has(node.id)
                ) {
                    found.add(node.id);
                    grew = true;
                }
            }
        }

        found.delete(employee.id);

        return found;
    }, [employee.id, nodes]);

    // Only the employee themselves is left out: nobody reports to themselves.
    const candidates = useMemo(
        () => nodes.filter((node) => node.id !== employee.id),
        [employee.id, nodes],
    );

    const swapWith = below.has(Number(choice))
        ? nodes.find((node) => node.id === Number(choice))
        : undefined;

    const save = () => {
        setSaving(true);
        router.put(
            `/avana/organisasi/${employee.id}/atasan`,
            { manager_id: choice },
            {
                preserveScroll: true,
                onSuccess: onSaved,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div style={{ padding: '10px 0', borderBottom: `1px solid ${C.line}` }}>
            <div style={{ fontSize: 11, color: C.faint, marginBottom: 6 }}>
                Atasan Langsung
            </div>
            <select
                value={choice}
                onChange={(event) => setChoice(event.target.value)}
                style={{
                    width: '100%',
                    height: 36,
                    padding: '0 10px',
                    border: `1px solid ${C.border}`,
                    borderRadius: 8,
                    background: '#fff',
                    fontSize: 13,
                    color: C.text,
                    cursor: 'pointer',
                }}
            >
                <option value={UNASSIGNED_MANAGER}>Belum ditentukan</option>
                <option value={NO_MANAGER}>
                    Tidak ada — Approver Puncak (Direktur / Direksi)
                </option>
                {candidates.map((node) => (
                    <option key={node.id} value={String(node.id)}>
                        {node.name}
                        {node.position ? ` — ${node.position}` : ''}
                        {below.has(node.id) ? ' (bawahan — tukar posisi)' : ''}
                    </option>
                ))}
            </select>
            {swapWith && (
                <div
                    style={{
                        fontSize: 11.5,
                        color: C.text,
                        lineHeight: 1.55,
                        marginTop: 7,
                        padding: '9px 11px',
                        borderRadius: 8,
                        background: `${C.primary}0f`,
                        border: `1px solid ${C.primary}33`,
                    }}
                >
                    <strong>{swapWith.name}</strong> sekarang ada di bawah{' '}
                    {employee.name}. Menyimpan akan menukar posisi keduanya:{' '}
                    {swapWith.name} naik ke posisi {employee.name}, dan{' '}
                    {employee.name} jadi bawahannya. Bawahan {employee.name}{' '}
                    yang lain tetap ikut {employee.name}.
                </div>
            )}
            {choice === NO_MANAGER && (
                <div
                    style={{
                        fontSize: 11.5,
                        color: C.red,
                        lineHeight: 1.5,
                        marginTop: 6,
                    }}
                >
                    Pengajuan cuti, lembur, dan reimbursement orang ini langsung
                    disetujui tanpa diperiksa siapa pun.
                </div>
            )}
            {choice !== current && (
                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    style={{
                        marginTop: 9,
                        width: '100%',
                        height: 36,
                        border: 'none',
                        borderRadius: 8,
                        background: C.primary,
                        color: '#fff',
                        fontSize: 13,
                        fontWeight: 500,
                        cursor: saving ? 'not-allowed' : 'pointer',
                        opacity: saving ? 0.7 : 1,
                    }}
                >
                    {saving ? 'Menyimpan…' : 'Simpan Atasan'}
                </button>
            )}
        </div>
    );
}

export default function OrgChart({
    nodes,
    canOpenProfile = true,
    canManage = false,
}: OrgChartProps) {
    const tree = useMemo(() => buildTree(nodes), [nodes]);

    // Start collapsed at the top: a wide org opens as a handful of roots rather
    // than a wall of cards, and the reader drills into the branch they want.
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<OrgNode | null>(null);
    const chartRef = useRef<HTMLDivElement>(null);
    const [isFullscreen, setIsFullscreen] = useState(false);

    const toggleNode = useCallback((id: number) => {
        setExpanded((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }, []);

    const expandAll = () => setExpanded(new Set(nodes.map((node) => node.id)));
    const collapseAll = () => setExpanded(new Set());

    const query = search.trim().toLowerCase();

    /** Employees matching the search box, by name, position, or department. */
    const matches = useMemo(() => {
        if (query === '') {
            return [] as OrgNode[];
        }

        return nodes.filter((node) =>
            [node.name, node.position, node.department, node.employee_number]
                .filter((field): field is string => Boolean(field))
                .some((field) => field.toLowerCase().includes(query)),
        );
    }, [nodes, query]);

    // A match is useless while its branch is shut, so the path down to every
    // hit counts as open for as long as the search stands. Derived rather than
    // written into state, so clearing the box restores what the user had open.
    const visibleExpanded = useMemo(() => {
        if (matches.length === 0) {
            return expanded;
        }

        const next = new Set(expanded);

        for (const match of matches) {
            for (const ancestor of ancestorsOf(tree, match.id)) {
                next.add(ancestor);
            }
        }

        return next;
    }, [expanded, matches, tree]);

    const highlighted = useMemo(
        () => new Set(matches.map((match) => match.id)),
        [matches],
    );

    const { nodes: flowNodes, edges } = useMemo(
        () => layout(tree, visibleExpanded, highlighted, toggleNode),
        [tree, visibleExpanded, highlighted, toggleNode],
    );

    useEffect(() => {
        const onChange = () =>
            setIsFullscreen(document.fullscreenElement === chartRef.current);
        document.addEventListener('fullscreenchange', onChange);

        return () => document.removeEventListener('fullscreenchange', onChange);
    }, []);

    const toggleFullscreen = () => {
        if (document.fullscreenElement) {
            void document.exitFullscreen();
        } else {
            void chartRef.current?.requestFullscreen();
        }
    };

    return (
        <>
            <Head title="Struktur Organisasi" />
            <div style={{ padding: '22px 26px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 7,
                    }}
                >
                    <span>Beranda</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Struktur Organisasi</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: 0,
                        letterSpacing: '-.01em',
                    }}
                >
                    Struktur Organisasi
                </h1>
                <div
                    style={{
                        fontSize: 14,
                        color: C.muted,
                        marginTop: 4,
                        marginBottom: 16,
                    }}
                >
                    Bagan hierarki pelaporan karyawan aktif. Klik kartu untuk
                    lihat detail, klik Perluas untuk membuka bawahannya.
                </div>

                {/* Toolbar: find someone, or open/close the whole tree at once */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        flexWrap: 'wrap',
                        marginBottom: 12,
                    }}
                >
                    <div
                        style={{ position: 'relative', flex: 1, minWidth: 240 }}
                    >
                        <span
                            style={{
                                position: 'absolute',
                                left: 12,
                                top: '50%',
                                transform: 'translateY(-50%)',
                                display: 'inline-flex',
                            }}
                        >
                            <AIcon name="search" size={15} color={C.faint} />
                        </span>
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama, jabatan, atau departemen…"
                            style={{
                                width: '100%',
                                height: 40,
                                padding: '0 12px 0 34px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 9,
                                fontSize: 13,
                                color: C.text,
                                background: '#fff',
                                outline: 'none',
                            }}
                        />
                    </div>

                    {query !== '' && (
                        <span style={{ fontSize: 12.5, color: C.muted }}>
                            {matches.length.toLocaleString('id-ID')} cocok
                        </span>
                    )}

                    <button
                        type="button"
                        onClick={expandAll}
                        style={toolbarBtn}
                    >
                        <AIcon name="chevrons-down" size={15} color={C.text} />
                        Perluas Semua
                    </button>
                    <button
                        type="button"
                        onClick={collapseAll}
                        style={toolbarBtn}
                    >
                        <AIcon name="chevrons-up" size={15} color={C.text} />
                        Ciutkan Semua
                    </button>
                </div>

                <div
                    ref={chartRef}
                    style={{
                        position: 'relative',
                        height: isFullscreen ? '100vh' : '70vh',
                        border: `1px solid ${C.border}`,
                        borderRadius: isFullscreen ? 0 : 12,
                        background: '#F8FAFC',
                    }}
                >
                    <button
                        type="button"
                        onClick={toggleFullscreen}
                        aria-label={
                            isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'
                        }
                        title={
                            isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'
                        }
                        style={{
                            position: 'absolute',
                            top: 12,
                            right: 12,
                            zIndex: 4,
                            width: 34,
                            height: 34,
                            borderRadius: 8,
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            color: C.muted,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            cursor: 'pointer',
                            boxShadow: '0 1px 3px rgba(15,26,58,.08)',
                        }}
                    >
                        <AIcon
                            name={isFullscreen ? 'minimize' : 'maximize'}
                            size={17}
                        />
                    </button>
                    {flowNodes.length === 0 ? (
                        <div
                            style={{
                                height: '100%',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                color: C.faint,
                                fontSize: 13,
                            }}
                        >
                            Belum ada data hierarki. Tetapkan atasan (manager)
                            pada data karyawan.
                        </div>
                    ) : (
                        <ReactFlow
                            nodes={flowNodes}
                            edges={edges}
                            nodeTypes={NODE_TYPES}
                            fitView
                            // Re-fit whenever the visible set changes, so an
                            // expanded branch does not open off-screen.
                            fitViewOptions={{ padding: 0.15 }}
                            key={flowNodes.length}
                            minZoom={0.1}
                            maxZoom={1.5}
                            nodesDraggable={false}
                            nodesConnectable={false}
                            onNodeClick={(_, node) =>
                                setSelected(
                                    nodes.find(
                                        (n) => String(n.id) === node.id,
                                    ) ?? null,
                                )
                            }
                        >
                            <Background />
                            <Controls showInteractive={false} />
                        </ReactFlow>
                    )}
                </div>
            </div>

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="w-[340px] gap-0 sm:max-w-[340px]"
                >
                    {selected && (
                        <>
                            <SheetHeader className="flex-row items-center gap-3 border-b">
                                <div
                                    style={{
                                        width: 44,
                                        height: 44,
                                        borderRadius: '50%',
                                        flex: 'none',
                                        background: hashColor(
                                            selected.department ??
                                                selected.name,
                                        ),
                                        color: '#fff',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: 15,
                                        fontWeight: 600,
                                    }}
                                >
                                    {initials(selected.name)}
                                </div>
                                <div style={{ minWidth: 0 }}>
                                    <SheetTitle className="truncate">
                                        {selected.name}
                                    </SheetTitle>
                                    <SheetDescription className="truncate">
                                        {selected.position ?? '—'}
                                    </SheetDescription>
                                </div>
                            </SheetHeader>

                            <div
                                style={{
                                    flex: 1,
                                    overflowY: 'auto',
                                    padding: '4px 18px 14px',
                                }}
                            >
                                <DetailRow
                                    label="ID Karyawan"
                                    value={selected.employee_number}
                                />
                                <DetailRow
                                    label="Email"
                                    value={selected.email}
                                />
                                <DetailRow
                                    label="No. HP"
                                    value={selected.phone}
                                />
                                <DetailRow
                                    label="Departemen"
                                    value={selected.department}
                                />
                                <DetailRow
                                    label="Cabang"
                                    value={selected.branch}
                                />
                                {canManage ? (
                                    <ManagerPicker
                                        key={selected.id}
                                        employee={selected}
                                        nodes={nodes}
                                        onSaved={() => setSelected(null)}
                                    />
                                ) : (
                                    <DetailRow
                                        label="Atasan"
                                        value={selected.manager_name}
                                    />
                                )}
                                <DetailRow
                                    label="Tanggal Masuk"
                                    value={selected.join_date}
                                />
                            </div>

                            {canOpenProfile && (
                                <SheetFooter className="border-t">
                                    <Link
                                        href={`/avana/employees/${selected.id}`}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            gap: 7,
                                            width: '100%',
                                            padding: '9px 0',
                                            borderRadius: 8,
                                            background: C.primary,
                                            color: '#fff',
                                            fontSize: 13,
                                            fontWeight: 500,
                                            textDecoration: 'none',
                                        }}
                                    >
                                        <AIcon
                                            name="external-link"
                                            size={15}
                                            color="#fff"
                                        />
                                        Lihat Profil Lengkap
                                    </Link>
                                </SheetFooter>
                            )}
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

const toolbarBtn: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    height: 40,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 9,
    background: '#fff',
    fontSize: 12.5,
    fontWeight: 500,
    color: C.text,
    cursor: 'pointer',
    whiteSpace: 'nowrap',
};
