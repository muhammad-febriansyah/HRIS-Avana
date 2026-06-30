import { router } from '@inertiajs/react';
import { useState } from 'react';
import PayrollConfigController from '@/actions/App/Http/Controllers/Avana/PayrollConfigController';
import { C } from '@/lib/avana';
import { ConfigModal, ConfirmModal, SectionTable } from './components';
import { flattenTerRate, SECTIONS } from './types';
import type { FlatRecord, TerRate } from './types';

const SECTION = SECTIONS.find((item) => item.key === 'pph21') ?? SECTIONS[1];

/** Tarif PPh 21 (TER) tab — table + create/edit modal + delete confirm. */
export default function Pph21Tab({ terRates }: { terRates: TerRate[] }) {
    const [openMenu, setOpenMenu] = useState<number | null>(null);
    const [editing, setEditing] = useState<FlatRecord | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [confirm, setConfirm] = useState<FlatRecord | null>(null);

    const rows: FlatRecord[] = terRates.map(flattenTerRate);

    const openCreate = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (row: FlatRecord) => {
        setEditing(row);
        setModalOpen(true);
        setOpenMenu(null);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
    };

    const requestDelete = (row: FlatRecord) => {
        setConfirm(row);
        setOpenMenu(null);
    };

    const deleteRecord = () => {
        if (!confirm) {
            return;
        }

        router.delete(PayrollConfigController.destroyTerRate(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <SectionTable
                section={SECTION}
                rows={rows}
                openMenu={openMenu}
                setOpenMenu={setOpenMenu}
                onCreate={openCreate}
                onEdit={openEdit}
                onDelete={requestDelete}
            />

            {modalOpen && (
                <ConfigModal
                    key={`${SECTION.key}-${editing?.id ?? 'new'}`}
                    section={SECTION}
                    record={editing}
                    onClose={closeModal}
                />
            )}

            {confirm && (
                <ConfirmModal
                    title="Hapus data?"
                    body={
                        <>
                            Data{' '}
                            <strong style={{ color: C.text }}>
                                {String(
                                    confirm.name ??
                                        confirm.category ??
                                        confirm.code ??
                                        '',
                                )}
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
