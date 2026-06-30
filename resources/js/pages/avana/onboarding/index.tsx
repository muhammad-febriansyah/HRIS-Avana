import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import OnboardingController from '@/actions/App/Http/Controllers/Avana/OnboardingController';
import { AIcon, ActionBtn, btnOut, btnP, C, card } from '@/lib/avana';

/* ============================================================
 * Onboarding board: new-hire programs with progress + checklist.
 * ============================================================ */

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

interface TaskRow {
    id: number;
    title: string;
    category: string | null;
    due_date: string | null;
    is_done: boolean;
}

interface ProgramCard {
    id: number;
    employee_id: number;
    employee: { name: string; employee_number: string } | null;
    start_date: string | null;
    status: string;
    tasks: TaskRow[];
    tasks_total: number;
    tasks_done: number;
}

interface OnboardingKpis {
    active: number;
    completed: number;
    pending_tasks: number;
}

interface OnboardingIndexProps {
    programs: ProgramCard[];
    employees: EmployeeOption[];
    kpis: OnboardingKpis;
}

type FlashProps = { flash?: { success?: string } };

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const kpiCardStyle: CSSProperties = { ...card, padding: '18px 20px', flex: '1 1 180px' };

export default function OnboardingIndex({ programs, employees, kpis }: OnboardingIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [programModalOpen, setProgramModalOpen] = useState(false);
    const [expanded, setExpanded] = useState<number | null>(programs[0]?.id ?? null);
    const [confirm, setConfirm] = useState<ProgramCard | null>(null);

    const programForm = useForm({ employee_id: '', start_date: '' });
    const taskForm = useForm({ title: '', category: '', due_date: '' });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openProgramModal = () => {
        programForm.clearErrors();
        programForm.setData({ employee_id: employees[0] ? String(employees[0].id) : '', start_date: new Date().toISOString().slice(0, 10) });
        setProgramModalOpen(true);
    };

    const closeProgramModal = () => {
        setProgramModalOpen(false);
        programForm.reset();
        programForm.clearErrors();
    };

    const submitProgram = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        programForm.submit(OnboardingController.store(), { onSuccess: () => closeProgramModal() });
    };

    const submitTask = (event: FormEvent<HTMLFormElement>, program: ProgramCard) => {
        event.preventDefault();
        taskForm.submit(OnboardingController.storeTask(program.id), {
            preserveScroll: true,
            onSuccess: () => taskForm.reset(),
        });
    };

    const toggleTask = (task: TaskRow) => {
        router.post(OnboardingController.toggleTask(task.id).url, {}, { preserveScroll: true });
    };

    const deleteProgram = () => {
        if (!confirm) {
            return;
        }
        router.delete(OnboardingController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const kpiItems = [
        { label: 'Program Aktif', value: kpis.active, icon: 'loader', color: C.primary },
        { label: 'Selesai', value: kpis.completed, icon: 'circle-check', color: C.green },
        { label: 'Tugas Pending', value: kpis.pending_tasks, icon: 'list-todo', color: C.amber },
    ];

    return (
        <>
            <Head title="Onboarding" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Onboarding</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Onboarding Karyawan</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Kelola program orientasi karyawan baru &amp; checklist tugasnya.</div>
                    </div>
                    <button onClick={openProgramModal} disabled={employees.length === 0} style={{ ...btnP, opacity: employees.length === 0 ? 0.6 : 1, cursor: employees.length === 0 ? 'not-allowed' : 'pointer' }}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Program
                    </button>
                </div>

                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                                <div style={{ width: 34, height: 34, borderRadius: 9, background: `${item.color}1a`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name={item.icon} size={17} color={item.color} />
                                </div>
                                <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>{item.label}</span>
                            </div>
                            <div style={{ fontSize: 26, fontWeight: 700, color: C.navy, letterSpacing: '-.02em' }}>{item.value}</div>
                        </div>
                    ))}
                </div>

                {programs.length === 0 && (
                    <div style={{ ...card, padding: '48px 18px', textAlign: 'center', color: C.muted, fontSize: 13.5 }}>
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                            <AIcon name="clipboard-list" size={28} color={C.faint} />
                            <div>Belum ada program onboarding.</div>
                        </div>
                    </div>
                )}

                <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                    {programs.map((program) => {
                        const pct = program.tasks_total > 0 ? Math.round((program.tasks_done / program.tasks_total) * 100) : 0;
                        const open = expanded === program.id;

                        return (
                            <div key={program.id} style={{ ...card, overflow: 'hidden' }}>
                                <div style={{ padding: '16px 20px', display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                                    <div style={{ flex: '1 1 220px', minWidth: 0 }}>
                                        <div style={{ fontSize: 14.5, fontWeight: 600, color: C.navy }}>{program.employee?.name ?? '—'}</div>
                                        <div style={{ fontSize: 12.5, color: C.muted, marginTop: 2 }}>
                                            {program.employee?.employee_number ?? '—'}
                                            {program.start_date ? ` · Mulai ${program.start_date}` : ''}
                                        </div>
                                    </div>
                                    <div style={{ flex: '1 1 240px', minWidth: 160 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: C.muted, marginBottom: 6 }}>
                                            <span>{program.tasks_done}/{program.tasks_total} tugas</span>
                                            <span>{pct}%</span>
                                        </div>
                                        <div style={{ height: 8, borderRadius: 100, background: C.surface, overflow: 'hidden' }}>
                                            <div style={{ height: '100%', width: `${pct}%`, background: program.status === 'completed' ? C.green : C.primary, transition: 'width .2s' }} />
                                        </div>
                                    </div>
                                    <StatusBadge status={program.status} />
                                    <div style={{ display: 'inline-flex', gap: 6 }}>
                                        <ActionBtn icon={open ? 'chevron-up' : 'chevron-down'} label={open ? 'Tutup' : 'Checklist'} variant="neutral" onClick={() => setExpanded(open ? null : program.id)} />
                                        <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => setConfirm(program)} />
                                    </div>
                                </div>

                                {open && (
                                    <div style={{ borderTop: `1px solid ${C.line}`, padding: '14px 20px', background: '#FAFBFD' }}>
                                        {program.tasks.length === 0 && <div style={{ fontSize: 13, color: C.faint, padding: '8px 0' }}>Belum ada tugas.</div>}
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                            {program.tasks.map((task) => (
                                                <div key={task.id} style={{ display: 'flex', alignItems: 'center', gap: 12, background: '#fff', border: `1px solid ${C.border}`, borderRadius: 9, padding: '10px 12px' }}>
                                                    <div style={{ flex: 1, minWidth: 0 }}>
                                                        <div style={{ fontSize: 13.5, fontWeight: 500, color: task.is_done ? C.faint : C.text, textDecoration: task.is_done ? 'line-through' : 'none' }}>{task.title}</div>
                                                        <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>
                                                            {task.category ?? 'Umum'}
                                                            {task.due_date ? ` · Tenggat ${task.due_date}` : ''}
                                                        </div>
                                                    </div>
                                                    <ActionBtn icon={task.is_done ? 'rotate-ccw' : 'check'} label={task.is_done ? 'Batalkan' : 'Selesai'} variant={task.is_done ? 'neutral' : 'success'} onClick={() => toggleTask(task)} />
                                                </div>
                                            ))}
                                        </div>

                                        <form onSubmit={(event) => submitTask(event, program)} style={{ display: 'flex', gap: 8, marginTop: 12, flexWrap: 'wrap' }}>
                                            <input type="text" value={taskForm.data.title} onChange={(e) => taskForm.setData('title', e.target.value)} placeholder="Tugas baru" style={{ ...inputStyle, flex: '2 1 200px', height: 38 }} />
                                            <input type="text" value={taskForm.data.category} onChange={(e) => taskForm.setData('category', e.target.value)} placeholder="Kategori" style={{ ...inputStyle, flex: '1 1 120px', height: 38 }} />
                                            <input type="date" value={taskForm.data.due_date} onChange={(e) => taskForm.setData('due_date', e.target.value)} style={{ ...inputStyle, flex: '1 1 140px', height: 38 }} />
                                            <button type="submit" disabled={taskForm.processing || taskForm.data.title.trim() === ''} style={{ ...btnP, height: 38, opacity: taskForm.processing || taskForm.data.title.trim() === '' ? 0.6 : 1 }}>
                                                <AIcon name="plus" size={15} color="#fff" />
                                                Tambah
                                            </button>
                                        </form>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {programModalOpen && (
                <ModalShell onClose={closeProgramModal} title="Tambah Program Onboarding" subtitle="Mulai program orientasi untuk karyawan baru.">
                    <form onSubmit={submitProgram}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <div>
                                <label style={labelStyle}>Karyawan <span style={{ color: C.red }}>*</span></label>
                                <select value={programForm.data.employee_id} onChange={(e) => programForm.setData('employee_id', e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((emp) => (
                                        <option key={emp.id} value={String(emp.id)}>{emp.name} ({emp.employee_number})</option>
                                    ))}
                                </select>
                                {programForm.errors.employee_id && <FieldError message={programForm.errors.employee_id} />}
                            </div>
                            <div>
                                <label style={labelStyle}>Tanggal Mulai</label>
                                <input type="date" value={programForm.data.start_date} onChange={(e) => programForm.setData('start_date', e.target.value)} style={inputStyle} />
                                {programForm.errors.start_date && <FieldError message={programForm.errors.start_date} />}
                            </div>
                        </div>
                        <ModalActions processing={programForm.processing} onCancel={closeProgramModal} />
                    </form>
                </ModalShell>
            )}

            {confirm && (
                <ConfirmModal
                    title="Hapus program?"
                    body={<>Program onboarding <strong style={{ color: C.text }}>{confirm.employee?.name}</strong> beserta seluruh tugasnya akan dihapus.</>}
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteProgram}
                />
            )}
        </>
    );
}

function StatusBadge({ status }: { status: string }) {
    const done = status === 'completed';
    return (
        <span style={{ display: 'inline-block', padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: done ? C.green : C.primary, background: done ? 'rgba(22,163,74,.1)' : 'rgba(47,84,201,.1)' }}>
            {done ? 'Selesai' : 'Berjalan'}
        </span>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }
    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

function ModalShell({ title, subtitle, onClose, children }: { title: string; subtitle: string; onClose: () => void; children: React.ReactNode }) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: 460, maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>{title}</div>
                <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>{subtitle}</div>
                {children}
            </div>
        </div>
    );
}

function ModalActions({ processing, onCancel }: { processing: boolean; onCancel: () => void }) {
    return (
        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
            <button type="button" onClick={onCancel} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>Batal</button>
            <button type="submit" disabled={processing} style={{ ...btnP, flex: 1, height: 44, justifyContent: 'center', opacity: processing ? 0.7 : 1 }}>
                <AIcon name="check" size={16} color="#fff" />
                Simpan
            </button>
        </div>
    );
}

function ConfirmModal({ title, body, onCancel, onConfirm }: { title: string; body: React.ReactNode; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
            <div onClick={onCancel} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(220,38,38,.1)', color: C.red, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>{title}</div>
                <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>{body}</div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button onClick={onCancel} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>Batal</button>
                    <button onClick={onConfirm} style={{ flex: 1, height: 44, background: C.red, color: '#fff', border: 'none', borderRadius: 9, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}
