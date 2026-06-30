import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ApprovalController from '@/actions/App/Http/Controllers/Avana/ApprovalController';
import { AIcon, C } from '@/lib/avana';
import { FilterChips } from './filter-chips';
import { HistoryTable } from './history-table';
import { PendingTable } from './pending-table';
import { StatCards } from './stat-cards';
import type { ApprovalItem, ApprovalProps, FilterKey, FlashProps } from './types';

export default function AvanaApproval({ pending, history, counts }: ApprovalProps) {
    const { flash } = usePage<FlashProps>().props;
    const [filter, setFilter] = useState<FilterKey>('all');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const visiblePending =
        filter === 'all' ? pending : pending.filter((item) => item.type === filter);

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

                {/* Stat cards */}
                <StatCards counts={counts} />

                {/* Filter chips */}
                <FilterChips filter={filter} counts={counts} onFilter={setFilter} />

                {/* Pending table */}
                <PendingTable
                    items={visiblePending}
                    onApprove={approve}
                    onReject={reject}
                />

                {/* History table */}
                <HistoryTable items={history} />
            </div>
        </>
    );
}
