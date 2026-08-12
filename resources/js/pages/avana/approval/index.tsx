import { Head, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import ApprovalController from '@/actions/App/Http/Controllers/Avana/ApprovalController';
import { AIcon, C } from '@/lib/avana';
import { HistoryTable } from './history-table';
import { PendingTable } from './pending-table';
import { StatCards } from './stat-cards';
import type {
    ApprovalItem,
    ApprovalProps,
    FilterKey,
    FlashProps,
} from './types';

export default function AvanaApproval({
    pending,
    pendingMeta,
    history,
    historyMeta,
    counts,
    filters,
    historyDays,
}: ApprovalProps) {
    const { flash } = usePage<FlashProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    /**
     * Paging and filtering live in the URL: the tables are paginated
     * server-side over eight merged sources, so the page number has to travel
     * with the request, and a shared link reopens the same view.
     */
    const visit = (params: Record<string, string | number>) =>
        router.get(ApprovalController.index().url, params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });

    const base = { per_page: filters.per_page, jenis: filters.jenis };

    const setFilter = (key: FilterKey) =>
        visit({
            ...base,
            jenis: key,
            // A narrower list makes any page but the first meaningless.
            halaman: 1,
            halaman_riwayat: historyMeta.current_page,
        });

    const setPerPage = (perPage: number) =>
        visit({ ...base, per_page: perPage, halaman: 1, halaman_riwayat: 1 });

    const approve = (item: ApprovalItem) =>
        router.post(
            ApprovalController.approve({ type: item.type, id: item.id }).url,
            {},
            { preserveScroll: true },
        );

    const reject = (item: ApprovalItem) =>
        router.post(
            ApprovalController.reject({ type: item.type, id: item.id }).url,
            {},
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Pusat Persetujuan" />
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
                        <span>Beranda</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Persetujuan</span>
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
                        Pusat Persetujuan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Tinjau dan proses semua pengajuan tim dari satu halaman
                    </div>
                </div>

                {/* Stat cards double as the type filter */}
                <StatCards
                    counts={counts}
                    filter={filters.jenis}
                    onFilter={setFilter}
                />

                {/* Pending table */}
                <PendingTable
                    items={pending}
                    meta={pendingMeta}
                    onApprove={approve}
                    onReject={reject}
                    onPage={(page) =>
                        visit({
                            ...base,
                            halaman: page,
                            halaman_riwayat: historyMeta.current_page,
                        })
                    }
                    onPerPage={setPerPage}
                />

                {/* History table */}
                <HistoryTable
                    items={history}
                    meta={historyMeta}
                    days={historyDays}
                    onPage={(page) =>
                        visit({
                            ...base,
                            halaman: pendingMeta.current_page,
                            halaman_riwayat: page,
                        })
                    }
                    onPerPage={setPerPage}
                />
            </div>
        </>
    );
}
