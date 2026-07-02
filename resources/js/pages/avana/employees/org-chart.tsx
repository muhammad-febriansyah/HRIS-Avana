import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import ReactFlow, {
    Background,
    Controls,
    
    
    Position
} from 'reactflow';
import type {Edge, Node} from 'reactflow';
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

const COL_WIDTH = 240;
const ROW_HEIGHT = 130;

/**
 * Assign each node an (x, y) using a simple layered layout: depth by distance
 * from a root (no/absent manager), ordered left-to-right within each level.
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
    const rowCursor: number[] = [];

    const place = (node: OrgNode, depth: number) => {
        const col = rowCursor[depth] ?? 0;
        rowCursor[depth] = col + 1;

        flowNodes.push({
            id: String(node.id),
            position: { x: col * COL_WIDTH, y: depth * ROW_HEIGHT },
            data: {
                label: (
                    <div style={{ textAlign: 'center', lineHeight: 1.35 }}>
                        <div style={{ fontWeight: 600, fontSize: 12.5 }}>
                            {node.name}
                        </div>
                        <div style={{ fontSize: 11, color: C.faint }}>
                            {node.position ?? '—'}
                        </div>
                    </div>
                ),
            },
            style: {
                border: `1px solid ${C.border}`,
                borderRadius: 10,
                background: '#fff',
                padding: 8,
                width: 200,
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

        for (const child of childrenOf.get(node.id) ?? []) {
            place(child, depth + 1);
        }
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
