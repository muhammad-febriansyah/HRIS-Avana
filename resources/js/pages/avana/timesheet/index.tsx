import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import TimesheetController from '@/actions/App/Http/Controllers/Avana/TimesheetController';
import { AIcon, btnP, C } from '@/lib/avana';
import {
    ConfirmModal,
    KpiRow,
    PageHeader,
    TabBar,
} from './components';
import { EntriesTab } from './entries-tab';
import { EntryModal } from './entry-modal';
import { ProjectModal } from './project-modal';
import { ProjectsTab } from './projects-tab';
import { ReportTab } from './report-tab';
import { rupiah } from './types';
import type {
    FlashProps,
    ProjectRow,
    TabKey,
    TimesheetEntry,
    TimesheetFilters,
    TimesheetIndexProps,
} from './types';

export default function TimesheetIndex({
    entries,
    projects,
    employees,
    filters,
    kpis,
    report,
    can,
}: TimesheetIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [tab, setTab] = useState<TabKey>('entries');
    const [projectModal, setProjectModal] = useState<{
        open: boolean;
        project: ProjectRow | null;
    }>({ open: false, project: null });
    const [entryModal, setEntryModal] = useState<{
        open: boolean;
        entry: TimesheetEntry | null;
    }>({ open: false, entry: null });
    const [confirmEntry, setConfirmEntry] = useState<TimesheetEntry | null>(
        null,
    );
    const [confirmProject, setConfirmProject] = useState<ProjectRow | null>(
        null,
    );
    const [selected, setSelected] = useState<number[]>([]);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    useEffect(() => {
        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash?.error]);

    // A decision or a filter change replaces the rows under the selection, so
    // anything no longer pending must drop out of it rather than be re-decided.
    useEffect(() => {
        const pendingIds = entries
            .filter((entry) => entry.status === 'pending')
            .map((entry) => entry.id);

        setSelected((current) =>
            current.filter((id) => pendingIds.includes(id)),
        );
    }, [entries]);

    const pendingIds = useMemo(
        () =>
            entries
                .filter((entry) => entry.status === 'pending')
                .map((entry) => entry.id),
        [entries],
    );

    const query = useMemo(
        () => ({
            project_id: filters.project_id ?? '',
            employee_id: filters.employee_id ?? '',
            status: filters.status ?? '',
            from: filters.from,
            to: filters.to,
        }),
        [filters],
    );

    const applyFilter = (key: keyof TimesheetFilters, value: string) => {
        router.get(
            TimesheetController.index().url,
            { ...query, [key]: value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const decide = (ids: number[], action: 'approve' | 'reject') => {
        if (ids.length === 0) {
            return;
        }

        const target =
            action === 'approve'
                ? TimesheetController.approve()
                : TimesheetController.reject();

        router.post(
            target.url,
            { ids },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            },
        );
    };

    const deleteEntry = () => {
        if (!confirmEntry) {
            return;
        }

        router.delete(TimesheetController.destroy(confirmEntry.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirmEntry(null),
        });
    };

    const deleteProject = () => {
        if (!confirmProject) {
            return;
        }

        router.delete(
            TimesheetController.destroyProject(confirmProject.id).url,
            {
                preserveScroll: true,
                onFinish: () => setConfirmProject(null),
            },
        );
    };

    const exportUrl =
        TimesheetController.export().url +
        '?' +
        new URLSearchParams(query).toString();

    return (
        <>
            <Head title="Timesheet" />
            <div style={{ padding: '28px 32px' }}>
                <PageHeader
                    crumb="Manajemen"
                    title="Timesheet"
                    subtitle="Catat jam kerja per proyek, setujui yang masuk dari aplikasi, dan pantau margin proyek."
                    actions={
                        can.create && (
                            <button
                                onClick={() =>
                                    setEntryModal({ open: true, entry: null })
                                }
                                disabled={
                                    projects.length === 0 ||
                                    employees.length === 0
                                }
                                style={{
                                    ...btnP,
                                    opacity:
                                        projects.length === 0 ||
                                        employees.length === 0
                                            ? 0.6
                                            : 1,
                                    cursor:
                                        projects.length === 0 ||
                                        employees.length === 0
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                <AIcon name="plus" size={16} color="#fff" />
                                Tambah Entri
                            </button>
                        )
                    }
                />

                <KpiRow
                    items={[
                        {
                            label: 'Jam Minggu Ini',
                            value: kpis.week_hours,
                            icon: 'clock',
                            color: C.primary,
                        },
                        {
                            label: 'Menunggu Persetujuan',
                            value: kpis.pending_entries,
                            icon: 'hourglass',
                            color: C.amber,
                        },
                        {
                            label: 'Nilai Jual Periode',
                            value: rupiah(kpis.bill_amount),
                            icon: 'wallet',
                            color: C.sky,
                        },
                        {
                            label: 'Margin Periode',
                            value: rupiah(kpis.margin),
                            icon: 'trending-up',
                            color: kpis.margin < 0 ? C.red : C.green,
                        },
                    ]}
                />

                <TabBar<TabKey>
                    active={tab}
                    onChange={setTab}
                    tabs={[
                        {
                            key: 'entries',
                            label: 'Entri',
                            badge: kpis.pending_entries,
                        },
                        { key: 'projects', label: 'Proyek' },
                        { key: 'report', label: 'Laporan' },
                    ]}
                />

                {tab === 'entries' && (
                    <EntriesTab
                        entries={entries}
                        projects={projects}
                        employees={employees}
                        filters={filters}
                        can={can}
                        selected={selected}
                        exportUrl={exportUrl}
                        onFilter={applyFilter}
                        onToggle={(id) =>
                            setSelected((current) =>
                                current.includes(id)
                                    ? current.filter((value) => value !== id)
                                    : [...current, id],
                            )
                        }
                        onToggleAll={() =>
                            setSelected((current) =>
                                current.length === pendingIds.length
                                    ? []
                                    : pendingIds,
                            )
                        }
                        onEdit={(entry) =>
                            setEntryModal({ open: true, entry })
                        }
                        onDelete={setConfirmEntry}
                        onDecide={decide}
                    />
                )}

                {tab === 'projects' && (
                    <ProjectsTab
                        projects={projects}
                        can={can}
                        onCreate={() =>
                            setProjectModal({ open: true, project: null })
                        }
                        onEdit={(project) =>
                            setProjectModal({ open: true, project })
                        }
                        onDelete={setConfirmProject}
                    />
                )}

                {tab === 'report' && (
                    <ReportTab
                        report={report}
                        from={filters.from}
                        to={filters.to}
                    />
                )}
            </div>

            {projectModal.open && (
                <ProjectModal
                    project={projectModal.project}
                    employees={employees}
                    onClose={() =>
                        setProjectModal({ open: false, project: null })
                    }
                />
            )}

            {entryModal.open && (
                <EntryModal
                    entry={entryModal.entry}
                    projects={projects}
                    employees={employees}
                    onClose={() => setEntryModal({ open: false, entry: null })}
                />
            )}

            {confirmEntry && (
                <ConfirmModal
                    title="Hapus entri?"
                    body={
                        <>
                            Entri timesheet{' '}
                            <strong style={{ color: C.text }}>
                                {confirmEntry.employee}
                            </strong>{' '}
                            pada {confirmEntry.date} akan dihapus.
                        </>
                    }
                    onCancel={() => setConfirmEntry(null)}
                    onConfirm={deleteEntry}
                />
            )}

            {confirmProject && (
                <ConfirmModal
                    title="Hapus proyek?"
                    body={
                        <>
                            Proyek{' '}
                            <strong style={{ color: C.text }}>
                                {confirmProject.name}
                            </strong>{' '}
                            akan dihapus. Proyek yang sudah punya entri tidak
                            bisa dihapus — arsipkan saja.
                        </>
                    }
                    onCancel={() => setConfirmProject(null)}
                    onConfirm={deleteProject}
                />
            )}
        </>
    );
}
