import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ReactFlow, { Background, Controls, Position } from 'reactflow';
import type { Edge, Node } from 'reactflow';
import 'reactflow/dist/style.css';
import { AIcon, C } from '@/lib/avana';

interface OrgNode {
    id: number;
    name: string;
    employee_number: string;
    email: string | null;
    position: string | null;
    department: string | null;
    branch: string | null;
    join_date: string | null;
    manager_id: number | null;
    manager_name: string | null;
}

interface OrgChartProps {
    nodes: OrgNode[];
}

const COL_WIDTH = 220;
const ROW_HEIGHT = 132;
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
    const parts = name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

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

/** The card body shown inside each org-chart node. */
function nodeLabel(node: OrgNode) {
    const color = hashColor(node.department ?? node.name);

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 9,
                textAlign: 'left',
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
                {node.department && (
                    <div
                        style={{
                            fontSize: 10.5,
                            color,
                            fontWeight: 500,
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}
                    >
                        {node.department}
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Tidy-tree layout: leaves are packed left-to-right, and every parent is
 * centred above the span of its own children — so sibling subtrees stay
 * grouped and edges don't cross. Depth maps to the vertical row.
 */
function layout(nodes: OrgNode[]): { nodes: Node[]; edges: Edge[] } {
    const byId = new Map(nodes.map((n) => [n.id, n]));
    const childrenOf = new Map<number | null, OrgNode[]>();

    for (const node of nodes) {
        const parent =
            node.manager_id !== null && byId.has(node.manager_id)
                ? node.manager_id
                : null;
        const list = childrenOf.get(parent) ?? [];
        list.push(node);
        childrenOf.set(parent, list);
    }

    const flowNodes: Node[] = [];
    const edges: Edge[] = [];
    const visited = new Set<number>();
    let nextLeafX = 0;

    /** Place a node (post-order) and return its horizontal centre in pixels. */
    const place = (node: OrgNode, depth: number): number => {
        visited.add(node.id);
        const children = (childrenOf.get(node.id) ?? []).filter(
            (child) => !visited.has(child.id),
        );

        let x: number;
        if (children.length === 0) {
            x = nextLeafX * COL_WIDTH;
            nextLeafX += 1;
        } else {
            const childXs = children.map((child) => place(child, depth + 1));
            x = (childXs[0] + childXs[childXs.length - 1]) / 2;
        }

        flowNodes.push({
            id: String(node.id),
            position: { x, y: depth * ROW_HEIGHT },
            data: { label: nodeLabel(node) },
            style: {
                border: `1px solid ${C.border}`,
                borderRadius: 10,
                background: '#fff',
                padding: 8,
                width: NODE_WIDTH,
                cursor: 'pointer',
            },
            sourcePosition: Position.Bottom,
            targetPosition: Position.Top,
        });

        if (node.manager_id !== null && byId.has(node.manager_id)) {
            edges.push({
                id: `${node.manager_id}-${node.id}`,
                source: String(node.manager_id),
                target: String(node.id),
            });
        }

        return x;
    };

    for (const root of childrenOf.get(null) ?? []) {
        place(root, 0);
    }

    return { nodes: flowNodes, edges };
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

export default function OrgChart({ nodes }: OrgChartProps) {
    const { nodes: flowNodes, edges } = useMemo(() => layout(nodes), [nodes]);
    const [selected, setSelected] = useState<OrgNode | null>(null);

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
                    lihat detail karyawan.
                </div>
                <div
                    style={{
                        position: 'relative',
                        height: '70vh',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        background: '#F8FAFC',
                        overflow: 'hidden',
                    }}
                >
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
                            fitView
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

                    {selected && (
                        <aside
                            style={{
                                position: 'absolute',
                                top: 0,
                                left: 0,
                                bottom: 0,
                                width: 320,
                                zIndex: 5,
                                background: '#fff',
                                borderRight: `1px solid ${C.border}`,
                                boxShadow: '2px 0 18px rgba(15,26,58,.10)',
                                display: 'flex',
                                flexDirection: 'column',
                            }}
                        >
                            <div
                                style={{
                                    padding: '16px 18px',
                                    borderBottom: `1px solid ${C.border}`,
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                }}
                            >
                                <div
                                    style={{
                                        width: 44,
                                        height: 44,
                                        borderRadius: '50%',
                                        flex: 'none',
                                        background: hashColor(
                                            selected.department ?? selected.name,
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
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div
                                        style={{
                                            fontSize: 15,
                                            fontWeight: 600,
                                            color: C.navy,
                                            whiteSpace: 'nowrap',
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                        }}
                                    >
                                        {selected.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            whiteSpace: 'nowrap',
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                        }}
                                    >
                                        {selected.position ?? '—'}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelected(null)}
                                    aria-label="Tutup"
                                    style={{
                                        border: 'none',
                                        background: 'transparent',
                                        cursor: 'pointer',
                                        color: C.faint,
                                        display: 'flex',
                                    }}
                                >
                                    <AIcon name="x" size={18} />
                                </button>
                            </div>
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
                                    label="Departemen"
                                    value={selected.department}
                                />
                                <DetailRow
                                    label="Cabang"
                                    value={selected.branch}
                                />
                                <DetailRow
                                    label="Atasan"
                                    value={selected.manager_name}
                                />
                                <DetailRow
                                    label="Tanggal Masuk"
                                    value={selected.join_date}
                                />
                            </div>
                            <div
                                style={{
                                    padding: '12px 18px',
                                    borderTop: `1px solid ${C.border}`,
                                }}
                            >
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
                            </div>
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
}
