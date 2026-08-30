import { AIcon, ActionBtn, btnOut, C, card, thCell } from '@/lib/avana';
import { cellStyle, EmptyRow, SectionTitle } from './components';
import { projectStatusLabel, rupiah } from './types';
import type { ProjectRow, TimesheetAbilities } from './types';

interface ProjectsTabProps {
    projects: ProjectRow[];
    can: TimesheetAbilities;
    onCreate: () => void;
    onEdit: (project: ProjectRow) => void;
    onDelete: (project: ProjectRow) => void;
}

/**
 * The project master: who the work is for, what it is budgeted at, and the
 * default rates every entry logged against it is priced with.
 */
export function ProjectsTab({
    projects,
    can,
    onCreate,
    onEdit,
    onDelete,
}: ProjectsTabProps) {
    return (
        <>
            <SectionTitle
                actions={
                    can.create && (
                        <button onClick={onCreate} style={btnOut}>
                            <AIcon name="folder-plus" size={16} color={C.text} />
                            Tambah Proyek
                        </button>
                    )
                }
            >
                Daftar Proyek
            </SectionTitle>

            <div style={{ ...card, overflow: 'hidden' }}>
                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            minWidth: 980,
                        }}
                    >
                        <thead>
                            <tr style={{ background: '#FAFBFD' }}>
                                <th style={thCell}>Nama Proyek</th>
                                <th style={thCell}>Klien</th>
                                <th style={thCell}>Periode</th>
                                <th style={thCell}>Tarif Jual / Jam</th>
                                <th style={thCell}>Budget</th>
                                <th style={thCell}>Anggota</th>
                                <th style={thCell}>Status</th>
                                <th style={thCell}>Entri</th>
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
                            {projects.length === 0 && (
                                <EmptyRow
                                    colSpan={9}
                                    icon="folder-kanban"
                                    message="Belum ada proyek. Tambah proyek dulu sebelum mencatat jam."
                                />
                            )}
                            {projects.map((project) => (
                                <tr
                                    key={project.id}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {project.name}
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                fontWeight: 500,
                                                color: C.muted,
                                                marginTop: 2,
                                            }}
                                        >
                                            {project.code ?? '—'}
                                            {!project.is_billable &&
                                                ' · Internal'}
                                        </div>
                                    </td>
                                    <td style={cellStyle}>
                                        {project.client_name ?? '—'}
                                    </td>
                                    <td style={cellStyle}>
                                        {project.start_date ?? '—'}
                                        {project.end_date
                                            ? ' – ' + project.end_date
                                            : ''}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(project.default_bill_rate)}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(project.budget_amount)}
                                        {project.budget_hours ? (
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.muted,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {project.budget_hours} jam
                                            </div>
                                        ) : null}
                                    </td>
                                    <td style={cellStyle}>
                                        {project.members.length > 0
                                            ? project.members.length + ' orang'
                                            : 'Semua karyawan'}
                                    </td>
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
                                    <td style={cellStyle}>
                                        {project.timesheets_count}
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                            textAlign: 'right',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {can.update && (
                                            <ActionBtn
                                                icon="pencil"
                                                label="Ubah"
                                                onClick={() => onEdit(project)}
                                            />
                                        )}
                                        {can.archive && (
                                            <ActionBtn
                                                icon="trash-2"
                                                label="Hapus"
                                                variant="danger"
                                                onClick={() =>
                                                    onDelete(project)
                                                }
                                            />
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
