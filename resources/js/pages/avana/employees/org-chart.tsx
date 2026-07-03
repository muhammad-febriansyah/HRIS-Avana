import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import ReactFlow, { Background, Controls, Position } from 'reactflow';
import type { Edge, Node } from 'reactflow';
import 'reactflow/dist/style.css';
import { AIcon, C } from '@/lib/avana';

interface OrgNode {
    id: number;
    name: string;
    position: string | null;
    department: string | null;
    manager_id: number | null;
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

export default function OrgChart({ nodes }: OrgChartProps) {
    const { nodes: flowNodes, edges } = useMemo(() => layout(nodes), [nodes]);

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
                    Bagan hierarki pelaporan karyawan aktif.
                </div>
                <div
                    style={{
                        height: '70vh',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        background: '#F8FAFC',
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
                        >
                            <Background />
                            <Controls showInteractive={false} />
                        </ReactFlow>
                    )}
                </div>
            </div>
        </>
    );
}
