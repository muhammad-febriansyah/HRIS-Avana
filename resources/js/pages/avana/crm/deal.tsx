import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CrmController from '@/actions/App/Http/Controllers/Avana/CrmController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, ActionBtn, btnP, C, card, rp } from '@/lib/avana';
import {
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    StageBadge,
    textareaStyle,
} from './components';
import {
    ACTIVITY_TYPE_ICONS,
    ACTIVITY_TYPE_LABELS,
    type CrmActivity,
    type CrmDealProps,
    type CrmMember,
    type CrmTask,
    type FlashProps,
} from './types';

const sectionTitle = {
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
} as const;

const cardHead = {
    padding: '15px 20px',
    borderBottom: `1px solid ${C.line}`,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
} as const;

/** Light zone wrapping an inline "add" form so inputs read as one block. */
const composeZone = {
    padding: '16px 20px',
    background: C.surface,
    borderBottom: `1px solid ${C.line}`,
} as const;

const STAGE_LABELS: Record<string, string> = {
    lead: 'Lead',
    qualified: 'Terkualifikasi',
    proposal: 'Proposal',
    won: 'Menang',
    lost: 'Kalah',
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function CrmDealDetail({
    deal,
    activities,
    tasks,
    members,
    employeeOptions,
    projectOptions,
    activityTypes,
}: CrmDealProps) {
    const { flash } = usePage<FlashProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const activityForm = useForm({
        type: 'call',
        note: '',
        activity_date: today(),
        outcome: '',
    });

    const taskForm = useForm({
        title: '',
        due_date: '',
        assignee_id: '',
    });

    const memberForm = useForm({ employee_id: '', role: '' });
    const [projectId, setProjectId] = useState<string>(
        deal.project_id ? String(deal.project_id) : '',
    );

    const submitActivity = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        activityForm.post(CrmController.storeActivity(deal.id).url, {
            preserveScroll: true,
            onSuccess: () => activityForm.reset('note', 'outcome'),
        });
    };

    const submitTask = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        taskForm.post(CrmController.storeTask(deal.id).url, {
            preserveScroll: true,
            onSuccess: () => taskForm.reset(),
        });
    };

    const submitMember = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        memberForm.post(CrmController.storeMember(deal.id).url, {
            preserveScroll: true,
            onSuccess: () => memberForm.reset(),
        });
    };

    const changeProject = (value: string) => {
        setProjectId(value);
        router.post(
            CrmController.linkProject(deal.id).url,
            { project_id: value === '' ? null : value },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Deal · ${deal.title}`} />
            <div style={{ padding: '28px 32px' }}>
                {/* Breadcrumb + back */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href={CrmController.index().url}
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        CRM
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{deal.title}</span>
                </div>

                {/* Deal header */}
                <div
                    style={{
                        ...card,
                        padding: '20px 22px',
                        marginBottom: 20,
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-start',
                        flexWrap: 'wrap',
                        gap: 14,
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                flexWrap: 'wrap',
                            }}
                        >
                            <h1
                                style={{
                                    fontSize: 21,
                                    fontWeight: 600,
                                    color: C.navy,
                                    margin: 0,
                                }}
                            >
                                {deal.title}
                            </h1>
                            <StageBadge
                                stage={deal.stage}
                                label={STAGE_LABELS[deal.stage] ?? deal.stage}
                            />
                        </div>
                        <div
                            style={{
                                fontSize: 24,
                                fontWeight: 700,
                                color: C.navy,
                                marginTop: 10,
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            {rp(deal.value)}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginTop: 12,
                                display: 'flex',
                                gap: '8px 22px',
                                flexWrap: 'wrap',
                            }}
                        >
                            <HeaderMeta
                                icon="building-2"
                                text={
                                    deal.company
                                        ? `${deal.contact ?? 'Tanpa kontak'} · ${deal.company}`
                                        : (deal.contact ?? 'Tanpa kontak')
                                }
                            />
                            {deal.contact_email && (
                                <HeaderMeta
                                    icon="mail"
                                    text={deal.contact_email}
                                />
                            )}
                            {deal.contact_phone && (
                                <HeaderMeta
                                    icon="phone"
                                    text={deal.contact_phone}
                                />
                            )}
                            {deal.owner && (
                                <HeaderMeta
                                    icon="briefcase"
                                    text={`PIC: ${deal.owner}`}
                                />
                            )}
                            {deal.expected_close && (
                                <HeaderMeta
                                    icon="calendar"
                                    text={`Target: ${deal.expected_close}`}
                                />
                            )}
                        </div>
                    </div>
                    <Link
                        href={CrmController.insights().url}
                        style={{
                            ...btnP,
                            textDecoration: 'none',
                            background: C.surface,
                            color: C.text,
                            border: `1px solid ${C.border}`,
                        }}
                    >
                        <AIcon name="chart-pie" size={16} color={C.text} />
                        Insights
                    </Link>
                </div>

                {/* Two-column body */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0,1.6fr) minmax(0,1fr)',
                        gap: 18,
                        alignItems: 'start',
                    }}
                    className="crm-deal-grid"
                >
                    {/* LEFT: activities + tasks */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                            minWidth: 0,
                        }}
                    >
                        {/* Follow-up activities */}
                        <div style={card}>
                            <div style={cardHead}>
                                <div style={sectionTitle}>
                                    Aktivitas &amp; Follow Up
                                </div>
                                <span
                                    style={{
                                        fontSize: 12,
                                        color: C.muted,
                                    }}
                                >
                                    {activities.length} entri
                                </span>
                            </div>
                            <form
                                onSubmit={submitActivity}
                                style={{
                                    ...composeZone,
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 10,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>Tipe</label>
                                    <select
                                        value={activityForm.data.type}
                                        onChange={(e) =>
                                            activityForm.setData(
                                                'type',
                                                e.target.value,
                                            )
                                        }
                                        style={selectStyle}
                                    >
                                        {activityTypes.map((t) => (
                                            <option
                                                key={t.value}
                                                value={t.value}
                                            >
                                                {t.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Tanggal
                                    </label>
                                    <input
                                        type="date"
                                        value={activityForm.data.activity_date}
                                        onChange={(e) =>
                                            activityForm.setData(
                                                'activity_date',
                                                e.target.value,
                                            )
                                        }
                                        style={inputStyle}
                                    />
                                </div>
                                <div style={{ gridColumn: '1/-1' }}>
                                    <label style={fieldLabelStyle}>
                                        Catatan
                                    </label>
                                    <textarea
                                        value={activityForm.data.note}
                                        onChange={(e) =>
                                            activityForm.setData(
                                                'note',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ringkasan interaksi dengan prospek…"
                                        style={{
                                            ...textareaStyle,
                                            minHeight: 60,
                                        }}
                                    />
                                </div>
                                <div style={{ gridColumn: '1/-1' }}>
                                    <label style={fieldLabelStyle}>
                                        Hasil (opsional)
                                    </label>
                                    <input
                                        value={activityForm.data.outcome}
                                        onChange={(e) =>
                                            activityForm.setData(
                                                'outcome',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="mis. Tertarik, minta proposal"
                                        style={inputStyle}
                                    />
                                </div>
                                <div style={{ gridColumn: '1/-1' }}>
                                    <button
                                        type="submit"
                                        disabled={activityForm.processing}
                                        style={btnP}
                                    >
                                        <AIcon
                                            name="plus"
                                            size={15}
                                            color="#fff"
                                        />
                                        Catat Aktivitas
                                    </button>
                                </div>
                            </form>

                            <div style={{ padding: '6px 20px 16px' }}>
                                {activities.length === 0 && (
                                    <div
                                        style={{
                                            padding: '24px 0',
                                            textAlign: 'center',
                                            color: C.faint,
                                            fontSize: 13,
                                        }}
                                    >
                                        Belum ada aktivitas.
                                    </div>
                                )}
                                {activities.map((activity) => (
                                    <ActivityRow
                                        key={activity.id}
                                        activity={activity}
                                    />
                                ))}
                            </div>
                        </div>

                        {/* Sales tasks */}
                        <div style={card}>
                            <div style={cardHead}>
                                <div style={sectionTitle}>Task Sales</div>
                                <span style={{ fontSize: 12, color: C.muted }}>
                                    {
                                        tasks.filter(
                                            (t) => t.status === 'pending',
                                        ).length
                                    }{' '}
                                    terbuka
                                </span>
                            </div>
                            <form
                                onSubmit={submitTask}
                                style={{
                                    ...composeZone,
                                    display: 'grid',
                                    gridTemplateColumns: '2fr 1fr',
                                    gap: 10,
                                }}
                            >
                                <div style={{ gridColumn: '1/-1' }}>
                                    <label style={fieldLabelStyle}>
                                        Judul task
                                    </label>
                                    <input
                                        value={taskForm.data.title}
                                        onChange={(e) =>
                                            taskForm.setData(
                                                'title',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="mis. Kirim proposal harga"
                                        style={inputStyle}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Jatuh tempo
                                    </label>
                                    <input
                                        type="date"
                                        value={taskForm.data.due_date}
                                        onChange={(e) =>
                                            taskForm.setData(
                                                'due_date',
                                                e.target.value,
                                            )
                                        }
                                        style={inputStyle}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Penanggung jawab
                                    </label>
                                    <SearchableSelect
                                        value={taskForm.data.assignee_id}
                                        onChange={(v) =>
                                            taskForm.setData('assignee_id', v)
                                        }
                                        options={employeeOptions.map((o) => ({
                                            value: String(o.id),
                                            label: o.name,
                                        }))}
                                        placeholder="Pilih karyawan…"
                                    />
                                </div>
                                <div style={{ gridColumn: '1/-1' }}>
                                    <button
                                        type="submit"
                                        disabled={taskForm.processing}
                                        style={btnP}
                                    >
                                        <AIcon
                                            name="plus"
                                            size={15}
                                            color="#fff"
                                        />
                                        Tambah Task
                                    </button>
                                </div>
                            </form>
                            <div style={{ padding: '6px 20px 16px' }}>
                                {tasks.length === 0 && (
                                    <div
                                        style={{
                                            padding: '24px 0',
                                            textAlign: 'center',
                                            color: C.faint,
                                            fontSize: 13,
                                        }}
                                    >
                                        Belum ada task.
                                    </div>
                                )}
                                {tasks.map((task) => (
                                    <TaskRow key={task.id} task={task} />
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* RIGHT: team + project */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                            minWidth: 0,
                        }}
                    >
                        {/* Linked project */}
                        <div style={card}>
                            <div style={cardHead}>
                                <div style={sectionTitle}>Proyek Terhubung</div>
                            </div>
                            <div style={{ padding: '16px 20px' }}>
                                <label style={fieldLabelStyle}>
                                    Kaitkan deal ke proyek/task delivery
                                </label>
                                <SearchableSelect
                                    value={projectId}
                                    onChange={changeProject}
                                    options={projectOptions.map((o) => ({
                                        value: String(o.id),
                                        label: o.name,
                                    }))}
                                    placeholder="Tanpa proyek"
                                    allowClear
                                />
                                {deal.project && (
                                    <div
                                        style={{
                                            marginTop: 10,
                                            fontSize: 12.5,
                                            color: C.green,
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 6,
                                        }}
                                    >
                                        <AIcon
                                            name="link"
                                            size={13}
                                            color={C.green}
                                        />
                                        Terhubung: {deal.project}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Team / collaboration */}
                        <div style={card}>
                            <div style={cardHead}>
                                <div style={sectionTitle}>
                                    Tim &amp; Kolaborasi
                                </div>
                                <span style={{ fontSize: 12, color: C.muted }}>
                                    {members.length} anggota
                                </span>
                            </div>
                            {deal.owner && (
                                <div
                                    style={{
                                        padding: '12px 20px',
                                        borderBottom: `1px solid ${C.line}`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        fontSize: 13,
                                    }}
                                >
                                    <span
                                        style={{
                                            padding: '2px 9px',
                                            borderRadius: 100,
                                            fontSize: 11,
                                            fontWeight: 600,
                                            color: C.primary,
                                            background: 'rgba(47,84,201,.1)',
                                        }}
                                    >
                                        PIC Utama
                                    </span>
                                    <span style={{ color: C.text }}>
                                        {deal.owner}
                                    </span>
                                </div>
                            )}
                            <form
                                onSubmit={submitMember}
                                style={{
                                    ...composeZone,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Karyawan
                                    </label>
                                    <SearchableSelect
                                        value={memberForm.data.employee_id}
                                        onChange={(v) =>
                                            memberForm.setData('employee_id', v)
                                        }
                                        options={employeeOptions.map((o) => ({
                                            value: String(o.id),
                                            label: o.name,
                                        }))}
                                        placeholder="Pilih karyawan…"
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Peran (opsional)
                                    </label>
                                    <input
                                        value={memberForm.data.role}
                                        onChange={(e) =>
                                            memberForm.setData(
                                                'role',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="mis. Pre-sales, Teknis"
                                        style={inputStyle}
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={memberForm.processing}
                                    style={btnP}
                                >
                                    <AIcon
                                        name="user-plus"
                                        size={15}
                                        color="#fff"
                                    />
                                    Tambah Anggota
                                </button>
                            </form>
                            <div style={{ padding: '6px 20px 12px' }}>
                                {members.length === 0 && (
                                    <div
                                        style={{
                                            padding: '18px 0',
                                            textAlign: 'center',
                                            color: C.faint,
                                            fontSize: 13,
                                        }}
                                    >
                                        Belum ada anggota tim.
                                    </div>
                                )}
                                {members.map((member) => (
                                    <MemberRow
                                        key={member.id}
                                        member={member}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

/** A labeled icon+text chip in the deal header meta row. */
function HeaderMeta({ icon, text }: { icon: string; text: string }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
            }}
        >
            <AIcon name={icon} size={13} color={C.faint} />
            {text}
        </span>
    );
}

/** A single follow-up activity timeline row. */
function ActivityRow({ activity }: { activity: CrmActivity }) {
    return (
        <div
            style={{
                display: 'flex',
                gap: 12,
                padding: '12px 0',
                borderBottom: '1px solid #F5F7FB',
            }}
        >
            <div
                style={{
                    width: 34,
                    height: 34,
                    borderRadius: 9,
                    background: 'rgba(47,84,201,.08)',
                    color: C.primary,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flex: 'none',
                }}
            >
                <AIcon
                    name={ACTIVITY_TYPE_ICONS[activity.type] ?? 'sticky-note'}
                    size={16}
                    color={C.primary}
                />
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        flexWrap: 'wrap',
                    }}
                >
                    <span
                        style={{
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: C.text,
                        }}
                    >
                        {ACTIVITY_TYPE_LABELS[activity.type] ?? activity.type}
                    </span>
                    <span style={{ fontSize: 12, color: C.faint }}>
                        {activity.activity_date}
                    </span>
                    {activity.author && (
                        <span style={{ fontSize: 12, color: C.faint }}>
                            · {activity.author}
                        </span>
                    )}
                </div>
                <div
                    style={{
                        fontSize: 13,
                        color: C.muted,
                        marginTop: 3,
                        whiteSpace: 'pre-wrap',
                    }}
                >
                    {activity.note}
                </div>
                {activity.outcome && (
                    <div
                        style={{
                            fontSize: 12,
                            color: C.green,
                            marginTop: 4,
                        }}
                    >
                        Hasil: {activity.outcome}
                    </div>
                )}
            </div>
            <ActionBtn
                icon="trash-2"
                label="Hapus"
                variant="danger"
                title="Hapus aktivitas"
                onClick={() =>
                    router.delete(
                        CrmController.destroyActivity(activity.id).url,
                        { preserveScroll: true },
                    )
                }
            />
        </div>
    );
}

/** A single sales-task row with a completion toggle. */
function TaskRow({ task }: { task: CrmTask }) {
    const done = task.status === 'done';

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                padding: '12px 0',
                borderBottom: '1px solid #F5F7FB',
            }}
        >
            <button
                title={done ? 'Buka kembali' : 'Tandai selesai'}
                onClick={() =>
                    router.post(
                        CrmController.toggleTask(task.id).url,
                        {},
                        { preserveScroll: true },
                    )
                }
                style={{
                    width: 22,
                    height: 22,
                    borderRadius: 6,
                    border: `1.5px solid ${done ? C.green : C.border}`,
                    background: done ? C.green : '#fff',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flex: 'none',
                }}
            >
                {done && <AIcon name="check" size={14} color="#fff" />}
            </button>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div
                    style={{
                        fontSize: 13.5,
                        color: done ? C.faint : C.text,
                        textDecoration: done ? 'line-through' : 'none',
                    }}
                >
                    {task.title}
                </div>
                <div
                    style={{
                        fontSize: 12,
                        color: task.is_overdue ? C.red : C.faint,
                        marginTop: 2,
                        display: 'flex',
                        gap: 10,
                        flexWrap: 'wrap',
                    }}
                >
                    {task.due_date && (
                        <span>
                            <AIcon
                                name="clock"
                                size={12}
                                color={task.is_overdue ? C.red : C.faint}
                            />{' '}
                            {task.due_date}
                            {task.is_overdue ? ' · lewat' : ''}
                        </span>
                    )}
                    {task.assignee && <span>{task.assignee}</span>}
                </div>
            </div>
            <ActionBtn
                icon="trash-2"
                label="Hapus"
                variant="danger"
                title="Hapus task"
                onClick={() =>
                    router.delete(CrmController.destroyTask(task.id).url, {
                        preserveScroll: true,
                    })
                }
            />
        </div>
    );
}

/** A single collaborating team-member row. */
function MemberRow({ member }: { member: CrmMember }) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '10px 0',
                borderBottom: '1px solid #F5F7FB',
            }}
        >
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, color: C.text }}>
                    {member.name ?? '—'}
                </div>
                {member.role && (
                    <div style={{ fontSize: 12, color: C.faint }}>
                        {member.role}
                    </div>
                )}
            </div>
            <ActionBtn
                icon="trash-2"
                label="Hapus"
                variant="danger"
                title="Hapus anggota"
                onClick={() =>
                    router.delete(CrmController.destroyMember(member.id).url, {
                        preserveScroll: true,
                    })
                }
            />
        </div>
    );
}
