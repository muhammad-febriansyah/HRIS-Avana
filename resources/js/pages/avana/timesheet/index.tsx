import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import TimesheetController from '@/actions/App/Http/Controllers/Avana/TimesheetController';
import { ActionBtn, AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    KpiRow,
    PageHeader,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import {
    emptyEntryForm,
    emptyProjectForm,
    projectStatusLabel,
} from './types';
import type {
    EntryFormData,
    FlashProps,
    ProjectFormData,
    TimesheetEntry,
    TimesheetIndexProps,
} from './types';

const cellStyle = { padding: '13px 16px', fontSize: 13, color: C.text } as const;

export default function TimesheetIndex({
    entries,
    projects,
    employees,
    filters,
    kpis,
}: TimesheetIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [projectModalOpen, setProjectModalOpen] = useState(false);
    const [entryModalOpen, setEntryModalOpen] = useState(false);
    const [confirm, setConfirm] = useState<TimesheetEntry | null>(null);

    const projectForm = useForm<ProjectFormData>({ ...emptyProjectForm });
    const entryForm = useForm<EntryFormData>({ ...emptyEntryForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const applyFilter = (key: 'project_id' | 'employee_id', value: string) => {
        router.get(
            TimesheetController.index().url,
            {
                project_id: key === 'project_id' ? value : (filters.project_id ?? ''),
                employee_id: key === 'employee_id' ? value : (filters.employee_id ?? ''),
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openProjectModal = () => {
        projectForm.clearErrors();
        projectForm.setData({ ...emptyProjectForm });
        setProjectModalOpen(true);
    };

    const closeProjectModal = () => {
        setProjectModalOpen(false);
        projectForm.reset();
        projectForm.clearErrors();
    };

    const submitProject = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        projectForm.submit(TimesheetController.storeProject(), {
            onSuccess: () => closeProjectModal(),
        });
    };

    const openEntryModal = () => {
        entryForm.clearErrors();
        entryForm.setData({
            ...emptyEntryForm,
            project_id: projects[0] ? String(projects[0].id) : '',
            employee_id: employees[0] ? String(employees[0].id) : '',
            date: new Date().toISOString().slice(0, 10),
        });
        setEntryModalOpen(true);
    };

    const closeEntryModal = () => {
        setEntryModalOpen(false);
        entryForm.reset();
        entryForm.clearErrors();
    };

    const submitEntry = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        entryForm.submit(TimesheetController.store(), {
            onSuccess: () => closeEntryModal(),
        });
    };

    const deleteEntry = () => {
        if (!confirm) {
            return;
        }

        router.delete(TimesheetController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Timesheet" />
            <div style={{ padding: '28px 32px' }}>
                <PageHeader
                    crumb="Manajemen"
                    title="Timesheet"
                    subtitle="Catat jam kerja karyawan per proyek."
                    actions={
                        <>
                            <button onClick={openProjectModal} style={btnOut}>
                                <AIcon name="folder-plus" size={16} color={C.text} />
                                Tambah Proyek
                            </button>
                            <button
                                onClick={openEntryModal}
                                disabled={projects.length === 0 || employees.length === 0}
                                style={{
                                    ...btnP,
                                    opacity:
                                        projects.length === 0 || employees.length === 0 ? 0.6 : 1,
                                    cursor:
                                        projects.length === 0 || employees.length === 0
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                <AIcon name="plus" size={16} color="#fff" />
                                Tambah Entri
                            </button>
                        </>
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
                            label: 'Proyek Aktif',
                            value: kpis.active_projects,
                            icon: 'folder-kanban',
                            color: C.sky,
                        },
                        {
                            label: 'Total Jam',
                            value: kpis.total_hours,
                            icon: 'timer',
                            color: C.amber,
                        },
                        {
                            label: 'Total Entri',
                            value: kpis.total_entries,
                            icon: 'list-checks',
                            color: C.green,
                        },
                    ]}
                />

                {/* Projects */}
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 12 }}>
                    Daftar Proyek
                </div>
                <div style={{ ...card, overflow: 'hidden', marginBottom: 28 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 560 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Nama Proyek</th>
                                    <th style={thCell}>Kode</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>Entri</th>
                                </tr>
                            </thead>
                            <tbody>
                                {projects.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            colSpan={4}
                                            style={{
                                                padding: '40px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            Belum ada proyek.
                                        </td>
                                    </tr>
                                )}
                                {projects.map((project) => (
                                    <tr key={project.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {project.name}
                                        </td>
                                        <td style={cellStyle}>{project.code ?? '—'}</td>
                                        <td style={cellStyle}>
                                            <span
                                                style={{
                                                    display: 'inline-block',
                                                    padding: '3px 10px',
                                                    borderRadius: 100,
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color:
                                                        project.status === 'active'
                                                            ? C.green
                                                            : C.muted,
                                                    background:
                                                        project.status === 'active'
                                                            ? 'rgba(22,163,74,.1)'
                                                            : 'rgba(107,114,128,.12)',
                                                }}
                                            >
                                                {projectStatusLabel(project.status)}
                                            </span>
                                        </td>
                                        <td style={cellStyle}>{project.timesheets_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Entries */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 12,
                        marginBottom: 12,
                    }}
                >
                    <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                        Entri Timesheet
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <select
                            value={filters.project_id ?? ''}
                            onChange={(event) => applyFilter('project_id', event.target.value)}
                            style={{ ...selectStyle, width: 200, height: 38 }}
                        >
                            <option value="">Semua proyek</option>
                            {projects.map((project) => (
                                <option key={project.id} value={String(project.id)}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={filters.employee_id ?? ''}
                            onChange={(event) => applyFilter('employee_id', event.target.value)}
                            style={{ ...selectStyle, width: 200, height: 38 }}
                        >
                            <option value="">Semua karyawan</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={String(employee.id)}>
                                    {employee.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 760 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Proyek</th>
                                    <th style={thCell}>Tanggal</th>
                                    <th style={thCell}>Jam</th>
                                    <th style={thCell}>Tugas</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                            padding: '12px 18px',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
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
                                                <AIcon name="clock" size={28} color={C.faint} />
                                                <div>Belum ada entri timesheet.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {entries.map((entry) => (
                                    <tr key={entry.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {entry.employee ?? '—'}
                                        </td>
                                        <td style={cellStyle}>{entry.project ?? '—'}</td>
                                        <td style={cellStyle}>{entry.date ?? '—'}</td>
                                        <td style={{ ...cellStyle, fontWeight: 600 }}>
                                            {entry.hours} jam
                                        </td>
                                        <td style={cellStyle}>{entry.task ?? '—'}</td>
                                        <td style={{ padding: '13px 18px', textAlign: 'right' }}>
                                            <ActionBtn
                                                icon="trash-2"
                                                label="Hapus"
                                                variant="danger"
                                                onClick={() => setConfirm(entry)}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            {entries.length > 0 && (
                                <tfoot>
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.border}`,
                                            background: '#FAFBFD',
                                        }}
                                    >
                                        <td
                                            colSpan={3}
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.muted,
                                                textAlign: 'right',
                                            }}
                                        >
                                            Total Jam
                                        </td>
                                        <td style={{ ...cellStyle, fontWeight: 700, color: C.navy }}>
                                            {kpis.total_hours} jam
                                        </td>
                                        <td colSpan={2} />
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>
            </div>

            {/* Add project modal */}
            {projectModalOpen && (
                <ModalShell onClose={closeProjectModal} title="Tambah Proyek" onSubmit={submitProject}>
                    <div>
                        <label style={fieldLabelStyle}>
                            Nama Proyek <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={projectForm.data.name}
                            onChange={(event) => projectForm.setData('name', event.target.value)}
                            placeholder="Nama proyek"
                            style={withError(inputStyle, !!projectForm.errors.name)}
                        />
                        <FieldError message={projectForm.errors.name} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        <div>
                            <label style={fieldLabelStyle}>Kode</label>
                            <input
                                type="text"
                                value={projectForm.data.code}
                                onChange={(event) => projectForm.setData('code', event.target.value)}
                                placeholder="PRJ-01"
                                style={withError(inputStyle, !!projectForm.errors.code)}
                            />
                            <FieldError message={projectForm.errors.code} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Status</label>
                            <select
                                value={projectForm.data.status}
                                onChange={(event) => projectForm.setData('status', event.target.value)}
                                style={withError(selectStyle, !!projectForm.errors.status)}
                            >
                                <option value="active">Aktif</option>
                                <option value="archived">Arsip</option>
                            </select>
                            <FieldError message={projectForm.errors.status} />
                        </div>
                    </div>
                    <ModalActions
                        processing={projectForm.processing}
                        onCancel={closeProjectModal}
                    />
                </ModalShell>
            )}

            {/* Add entry modal */}
            {entryModalOpen && (
                <ModalShell onClose={closeEntryModal} title="Tambah Entri Timesheet" onSubmit={submitEntry}>
                    <div>
                        <label style={fieldLabelStyle}>
                            Karyawan <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={entryForm.data.employee_id}
                            onChange={(event) => entryForm.setData('employee_id', event.target.value)}
                            style={withError(selectStyle, !!entryForm.errors.employee_id)}
                        >
                            <option value="">Pilih karyawan</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={String(employee.id)}>
                                    {employee.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={entryForm.errors.employee_id} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>
                            Proyek <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={entryForm.data.project_id}
                            onChange={(event) => entryForm.setData('project_id', event.target.value)}
                            style={withError(selectStyle, !!entryForm.errors.project_id)}
                        >
                            <option value="">Pilih proyek</option>
                            {projects.map((project) => (
                                <option key={project.id} value={String(project.id)}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={entryForm.errors.project_id} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        <div>
                            <label style={fieldLabelStyle}>
                                Tanggal <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="date"
                                value={entryForm.data.date}
                                onChange={(event) => entryForm.setData('date', event.target.value)}
                                style={withError(inputStyle, !!entryForm.errors.date)}
                            />
                            <FieldError message={entryForm.errors.date} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>
                                Jam <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="number"
                                step="0.5"
                                min="0.5"
                                max="24"
                                value={entryForm.data.hours}
                                onChange={(event) => entryForm.setData('hours', event.target.value)}
                                placeholder="8"
                                style={withError(inputStyle, !!entryForm.errors.hours)}
                            />
                            <FieldError message={entryForm.errors.hours} />
                        </div>
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Tugas</label>
                        <input
                            type="text"
                            value={entryForm.data.task}
                            onChange={(event) => entryForm.setData('task', event.target.value)}
                            placeholder="Deskripsi singkat pekerjaan"
                            style={withError(inputStyle, !!entryForm.errors.task)}
                        />
                        <FieldError message={entryForm.errors.task} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Catatan</label>
                        <textarea
                            value={entryForm.data.notes}
                            onChange={(event) => entryForm.setData('notes', event.target.value)}
                            placeholder="Catatan (opsional)"
                            style={withError(textareaStyle, !!entryForm.errors.notes)}
                        />
                        <FieldError message={entryForm.errors.notes} />
                    </div>
                    <ModalActions processing={entryForm.processing} onCancel={closeEntryModal} />
                </ModalShell>
            )}

            {/* Confirm delete entry */}
            {confirm && (
                <ConfirmModal
                    title="Hapus entri?"
                    body={
                        <>
                            Entri timesheet{' '}
                            <strong style={{ color: C.text }}>{confirm.employee}</strong> pada{' '}
                            {confirm.date} akan dihapus.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteEntry}
                />
            )}
        </>
    );
}

/* ---------- local modal helpers ---------- */

interface ModalShellProps {
    title: string;
    onClose: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    children: React.ReactNode;
}

function ModalShell({ title, onClose, onSubmit, children }: ModalShellProps) {
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
                style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }}
            />
            <form
                onSubmit={onSubmit}
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 460,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 14,
                }}
            >
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>{title}</div>
                {children}
            </form>
        </div>
    );
}

function ModalActions({ processing, onCancel }: { processing: boolean; onCancel: () => void }) {
    return (
        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
            <button
                type="button"
                onClick={onCancel}
                style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}
            >
                Batal
            </button>
            <button
                type="submit"
                disabled={processing}
                style={{
                    ...btnP,
                    flex: 1,
                    height: 44,
                    justifyContent: 'center',
                    opacity: processing ? 0.7 : 1,
                    cursor: processing ? 'not-allowed' : 'pointer',
                }}
            >
                <AIcon name="check" size={16} color="#fff" />
                Simpan
            </button>
        </div>
    );
}
