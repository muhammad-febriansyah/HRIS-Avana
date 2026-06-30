import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, C } from '@/lib/avana';
import { ConfirmModal } from './components';
import { EntityModal } from './entity-modal';
import { EntityTable } from './entity-table';
import { TABS } from './types';
import type { EntityRecord, FlashProps, PerusahaanProps } from './types';

export default function Perusahaan(props: PerusahaanProps) {
    const { flash } = usePage<FlashProps>().props;
    const { options } = props;

    const [activeKey, setActiveKey] = useState<string>('branches');
    const [openMenu, setOpenMenu] = useState<number | null>(null);
    const [editing, setEditing] = useState<EntityRecord | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [confirm, setConfirm] = useState<EntityRecord | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const tab = TABS.find((item) => item.key === activeKey) ?? TABS[0];

    const dataMap: Record<string, EntityRecord[]> = {
        branches: props.branches,
        departments: props.departments,
        positions: props.positions,
        'job-levels': props.jobLevels,
        'work-locations': props.workLocations,
        shifts: props.shifts,
    };
    const rows = dataMap[tab.key] ?? [];

    const openCreate = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (row: EntityRecord) => {
        setEditing(row);
        setModalOpen(true);
        setOpenMenu(null);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
    };

    const deleteRecord = () => {
        if (!confirm) {
            return;
        }

        router.delete(`/avana/perusahaan/${tab.key}/${confirm.id}`, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Perusahaan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span>Pengaturan</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Perusahaan</span>
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
                        Pengaturan Perusahaan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Kelola cabang, departemen, jabatan, jenjang, lokasi
                        kerja, dan shift.
                    </div>
                </div>

                {/* Tab bar */}
                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        flexWrap: 'wrap',
                        borderBottom: `1px solid ${C.border}`,
                        marginBottom: 18,
                    }}
                >
                    {TABS.map((item) => {
                        const active = item.key === activeKey;

                        return (
                            <button
                                key={item.key}
                                onClick={() => {
                                    setActiveKey(item.key);
                                    setOpenMenu(null);
                                }}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '11px 14px',
                                    background: 'none',
                                    border: 'none',
                                    borderBottom: `2px solid ${active ? C.primary : 'transparent'}`,
                                    marginBottom: -1,
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    color: active ? C.primary : C.muted,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon
                                    name={item.icon}
                                    size={16}
                                    color={active ? C.primary : C.faint}
                                />
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {/* Entity card */}
                <EntityTable
                    tab={tab}
                    rows={rows}
                    openMenu={openMenu}
                    onToggleMenu={(id) =>
                        setOpenMenu((prev) => (prev === id ? null : id))
                    }
                    onCreate={openCreate}
                    onEdit={openEdit}
                    onDelete={(row) => {
                        setConfirm(row);
                        setOpenMenu(null);
                    }}
                />
            </div>

            {/* Create / edit modal */}
            {modalOpen && (
                <EntityModal
                    key={`${tab.key}-${editing?.id ?? 'new'}`}
                    tab={tab}
                    options={options}
                    record={editing}
                    onClose={closeModal}
                />
            )}

            {/* Confirm delete modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus data?"
                    body={
                        <>
                            Data{' '}
                            <strong style={{ color: C.text }}>
                                {String(confirm.name ?? confirm.code ?? '')}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteRecord}
                />
            )}
        </>
    );
}
