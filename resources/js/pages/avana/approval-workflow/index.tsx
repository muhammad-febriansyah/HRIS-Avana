import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ApprovalWorkflowController from '@/actions/App/Http/Controllers/Avana/ApprovalWorkflowController';
import { AIcon, ActionBtn, btnP, C, card, thCell } from '@/lib/avana';
import type {
    ApproverTypeDef,
    ModuleDef,
    WizardOptions,
    WorkflowRow,
} from './types';
import Wizard from './wizard';

interface IndexProps {
    workflows: WorkflowRow[];
    modules: ModuleDef[];
    approverTypes: ApproverTypeDef[];
    options: WizardOptions;
    kpis: { total: number; active: number };
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

const headTh: CSSProperties = { ...thCell, padding: '11px 16px' };

export default function ApprovalWorkflowIndex({
    workflows,
    modules,
    approverTypes,
    options,
}: IndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [view, setView] = useState<'list' | 'wizard'>('list');
    const [editing, setEditing] = useState<WorkflowRow | null>(null);
    const [preview, setPreview] = useState<WorkflowRow | null>(null);
    const [confirmDelete, setConfirmDelete] = useState<WorkflowRow | null>(
        null,
    );
    const [menuOpen, setMenuOpen] = useState<{
        id: number;
        top: number;
        left: number;
    } | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const openCreate = () => {
        setEditing(null);
        setView('wizard');
    };
    const openEdit = (row: WorkflowRow) => {
        setEditing(row);
        setView('wizard');
    };
    const closeWizard = () => {
        setView('list');
        setEditing(null);
    };

    const toggle = (row: WorkflowRow) =>
        router.post(
            ApprovalWorkflowController.toggle(row.id).url,
            {},
            { preserveScroll: true },
        );

    const remove = () => {
        if (!confirmDelete) {
            return;
        }

        router.delete(
            ApprovalWorkflowController.destroy(confirmDelete.id).url,
            {
                preserveScroll: true,
                onSuccess: () => setConfirmDelete(null),
            },
        );
    };

    if (view === 'wizard') {
        return (
            <>
                <Head title="Setup Approval Workflow" />
                <Wizard
                    mode={editing ? 'edit' : 'create'}
                    workflow={editing}
                    modules={modules}
                    approverTypes={approverTypes}
                    options={options}
                    onClose={closeWizard}
                />
            </>
        );
    }

    return (
        <>
            <Head title="Approval Workflow" />
            <div style={{ padding: '28px 32px' }}>
                {/* header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
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
                            <span>Sistem</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Alur Persetujuan
                            </span>
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
                            Approval Workflow
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola alur persetujuan untuk setiap jenis
                            pengajuan.
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Buat Workflow Baru
                    </button>
                </div>

                {/* table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 880,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headTh}>Modul</th>
                                    <th style={headTh}>Tipe Workflow</th>
                                    <th style={headTh}>Jumlah Step</th>
                                    <th style={headTh}>Status</th>
                                    <th style={headTh}>Terakhir Diubah</th>
                                    <th
                                        style={{
                                            ...headTh,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {workflows.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={6}
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon
                                                    name="git-branch"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada alur persetujuan.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {workflows.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td style={{ padding: '13px 16px' }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        width: 32,
                                                        height: 32,
                                                        borderRadius: 8,
                                                        background: `${row.module_color}1a`,
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        flex: 'none',
                                                    }}
                                                >
                                                    <AIcon
                                                        name={row.module_icon}
                                                        size={16}
                                                        color={row.module_color}
                                                    />
                                                </div>
                                                <span
                                                    style={{
                                                        fontSize: 13,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {row.module_label}
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {row.approval_mode_label}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {row.step_count} Step
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusPill
                                                active={row.is_active}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 12.5,
                                                color: C.muted,
                                            }}
                                        >
                                            {row.updated_at ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                    alignItems: 'center',
                                                    justifyContent: 'flex-end',
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Edit"
                                                    variant="primary"
                                                    onClick={() =>
                                                        openEdit(row)
                                                    }
                                                />
                                                <button
                                                    title="Aksi lainnya"
                                                    onClick={(e) => {
                                                        if (
                                                            menuOpen?.id ===
                                                            row.id
                                                        ) {
                                                            setMenuOpen(null);

                                                            return;
                                                        }

                                                        const r =
                                                            e.currentTarget.getBoundingClientRect();
                                                        setMenuOpen({
                                                            id: row.id,
                                                            top: r.bottom + 6,
                                                            left: r.right - 176,
                                                        });
                                                    }}
                                                    style={{
                                                        height: 32,
                                                        width: 32,
                                                        border: `1px solid ${C.border}`,
                                                        borderRadius: 7,
                                                        background: '#fff',
                                                        cursor: 'pointer',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="ellipsis-vertical"
                                                        size={16}
                                                        color={C.muted}
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {menuOpen &&
                (() => {
                    const row = workflows.find((w) => w.id === menuOpen.id);

                    if (!row) {
                        return null;
                    }

                    return (
                        <>
                            <div
                                onClick={() => setMenuOpen(null)}
                                style={{
                                    position: 'fixed',
                                    inset: 0,
                                    zIndex: 60,
                                }}
                            />
                            <div
                                style={{
                                    position: 'fixed',
                                    top: menuOpen.top,
                                    left: menuOpen.left,
                                    zIndex: 61,
                                    minWidth: 176,
                                    background: '#fff',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 10,
                                    boxShadow: '0 10px 30px rgba(15,23,42,.14)',
                                    padding: 6,
                                }}
                            >
                                <KebabItem
                                    icon="eye"
                                    label="Preview"
                                    onClick={() => {
                                        setPreview(row);
                                        setMenuOpen(null);
                                    }}
                                />
                                <KebabItem
                                    icon={row.is_active ? 'pause' : 'play'}
                                    label={
                                        row.is_active
                                            ? 'Nonaktifkan'
                                            : 'Aktifkan'
                                    }
                                    onClick={() => {
                                        toggle(row);
                                        setMenuOpen(null);
                                    }}
                                />
                                <KebabItem
                                    icon="trash-2"
                                    label="Hapus"
                                    danger
                                    onClick={() => {
                                        setConfirmDelete(row);
                                        setMenuOpen(null);
                                    }}
                                />
                            </div>
                        </>
                    );
                })()}

            {preview && (
                <PreviewModal
                    workflow={preview}
                    options={options}
                    approverTypes={approverTypes}
                    onClose={() => setPreview(null)}
                />
            )}

            {confirmDelete && (
                <DeleteModal
                    workflow={confirmDelete}
                    onCancel={() => setConfirmDelete(null)}
                    onConfirm={remove}
                />
            )}
        </>
    );
}

function KebabItem({
    icon,
    label,
    onClick,
    danger,
}: {
    icon: string;
    label: string;
    onClick: () => void;
    danger?: boolean;
}) {
    const color = danger ? C.red : C.text;

    return (
        <button
            onClick={onClick}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 9,
                width: '100%',
                padding: '8px 10px',
                border: `1px solid ${danger ? 'rgba(220,38,38,.28)' : C.border}`,
                borderRadius: 7,
                background: danger ? 'rgba(220,38,38,.06)' : C.surface,
                color,
                fontSize: 13,
                fontWeight: 500,
                cursor: 'pointer',
            }}
            onMouseEnter={(e) => {
                e.currentTarget.style.background = C.surface;
            }}
            onMouseLeave={(e) => {
                e.currentTarget.style.background = 'transparent';
            }}
        >
            <AIcon name={icon} size={15} color={color} />
            {label}
        </button>
    );
}

function StatusPill({ active }: { active: boolean }) {
    const color = active ? C.green : C.muted;
    const bg = active ? 'rgba(22,163,74,.1)' : 'rgba(107,114,128,.12)';

    return (
        <span
            style={{
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color,
                background: bg,
            }}
        >
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

const PREVIEW_FIELD: Record<string, string> = {
    days: 'jumlah hari',
    amount: 'nominal',
    leave_type: 'jenis cuti',
};

function PreviewModal({
    workflow,
    options,
    approverTypes,
    onClose,
}: {
    workflow: WorkflowRow;
    options: WizardOptions;
    approverTypes: ApproverTypeDef[];
    onClose: () => void;
}) {
    const refName = (type: string, id: number | null): string | undefined => {
        const ref = approverTypes.find((a) => a.key === type)?.ref;
        const list =
            ref === 'role'
                ? options.roles
                : ref === 'department'
                  ? options.departments
                  : ref === 'position'
                    ? options.positions
                    : ref === 'employee'
                      ? options.employees
                      : [];

        return list.find((o) => o.value === id)?.label;
    };

    const conditionText = (c: WorkflowRow['conditions'][number]): string => {
        const fieldPart =
            c.field === 'leave_type'
                ? `jenis cuti ${options.leaveTypes.find((t) => String(t.value) === c.value)?.label ?? c.value}`
                : `${PREVIEW_FIELD[c.field] ?? c.field} ${c.operator} ${c.value}${c.field === 'days' ? ' hari' : ''}`;
        const typeLabel =
            approverTypes.find((a) => a.key === c.extra_approver_type)?.label ??
            c.extra_approver_type;
        const approver =
            refName(c.extra_approver_type, c.extra_approver_ref) ?? typeLabel;

        return `Jika ${fieldPart} → tambah approver ${approver}`;
    };

    return (
        <Overlay onClose={onClose}>
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 780,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 24,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        fontSize: 18,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 18,
                    }}
                >
                    Preview Alur — {workflow.module_label}
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 20,
                        flexWrap: 'wrap',
                    }}
                >
                    {/* left: flow */}
                    <div style={{ flex: '1 1 340px', minWidth: 300 }}>
                        {workflow.steps.map((s, i) => (
                            <div key={s.step_order}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        padding: '12px 14px',
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 10,
                                        background: '#FAFBFD',
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 28,
                                            height: 28,
                                            borderRadius: 100,
                                            background: 'rgba(47,84,201,.12)',
                                            color: C.primary,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: 12,
                                            fontWeight: 700,
                                            flex: 'none',
                                        }}
                                    >
                                        {s.step_order}
                                    </span>
                                    <div>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            Step {s.step_order} —{' '}
                                            {s.approver_label}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.muted,
                                            }}
                                        >
                                            {s.approver_type_label}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    style={{
                                        width: 2,
                                        height: 14,
                                        background: C.border,
                                        margin: '0 0 0 27px',
                                    }}
                                />
                            </div>
                        ))}

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                                padding: '12px 14px',
                                border: `1px solid rgba(22,163,74,.3)`,
                                borderRadius: 10,
                                background: 'rgba(22,163,74,.06)',
                            }}
                        >
                            <span
                                style={{
                                    width: 28,
                                    height: 28,
                                    borderRadius: 100,
                                    background: 'rgba(22,163,74,.15)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    flex: 'none',
                                }}
                            >
                                <AIcon name="check" size={15} color={C.green} />
                            </span>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Selesai — Pengajuan Disetujui
                            </div>
                        </div>
                    </div>

                    {/* right: detail */}
                    <div
                        style={{
                            flex: '1 1 260px',
                            minWidth: 240,
                            padding: 16,
                            borderRadius: 12,
                            background: C.surface,
                            border: `1px solid ${C.border}`,
                            alignSelf: 'flex-start',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 13.5,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 12,
                            }}
                        >
                            Detail Konfigurasi
                        </div>
                        {[
                            ['Modul', workflow.module_label],
                            ['Tipe Workflow', workflow.approval_mode_label],
                            ['Jumlah Step', `${workflow.step_count}`],
                        ].map(([k, v]) => (
                            <div
                                key={k}
                                style={{
                                    display: 'flex',
                                    fontSize: 12.5,
                                    marginBottom: 8,
                                }}
                            >
                                <span
                                    style={{
                                        width: 110,
                                        color: C.muted,
                                        flex: 'none',
                                    }}
                                >
                                    {k}
                                </span>
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {v}
                                </span>
                            </div>
                        ))}
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kondisi Khusus
                        </div>
                        {workflow.conditions.length === 0 ? (
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.faint,
                                    marginTop: 4,
                                }}
                            >
                                Tidak ada
                            </div>
                        ) : (
                            <ul
                                style={{
                                    margin: '6px 0 0',
                                    paddingLeft: 16,
                                    fontSize: 12,
                                    color: C.text,
                                    lineHeight: 1.7,
                                }}
                            >
                                {workflow.conditions.map((c, i) => (
                                    <li key={i}>{conditionText(c)}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        marginTop: 20,
                    }}
                >
                    <button
                        onClick={onClose}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 8,
                            height: 40,
                            padding: '0 20px',
                            border: `1px solid ${C.border}`,
                            borderRadius: 9,
                            background: '#fff',
                            color: C.text,
                            fontSize: 13.5,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="x" size={15} color={C.text} />
                        Tutup
                    </button>
                </div>
            </div>
        </Overlay>
    );
}

function DeleteModal({
    workflow,
    onCancel,
    onConfirm,
}: {
    workflow: WorkflowRow;
    onCancel: () => void;
    onConfirm: () => void;
}) {
    return (
        <Overlay onClose={onCancel}>
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 400,
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 12,
                        background: 'rgba(220,38,38,.1)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                    Hapus alur persetujuan?
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.muted,
                        marginTop: 8,
                        lineHeight: 1.55,
                    }}
                >
                    Alur <strong>{workflow.module_label}</strong> akan dihapus.
                </div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        onClick={onCancel}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                            flex: 1,
                            height: 44,
                            border: `1px solid ${C.border}`,
                            borderRadius: 9,
                            background: '#fff',
                            color: C.text,
                            fontSize: 14,
                            fontWeight: 500,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="x" size={15} color={C.text} />
                        Batal
                    </button>
                    <button
                        onClick={onConfirm}
                        style={{
                            flex: 1,
                            height: 44,
                            background: C.red,
                            color: '#fff',
                            border: 'none',
                            borderRadius: 9,
                            fontSize: 14,
                            fontWeight: 600,
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                        }}
                    >
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </Overlay>
    );
}

function Overlay({
    children,
    onClose,
}: {
    children: React.ReactNode;
    onClose: () => void;
}) {
    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            {children}
        </div>
    );
}
