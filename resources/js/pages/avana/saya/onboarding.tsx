import { Head, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, C, card } from '@/lib/avana';
import {
    EmptyState,
    formatDate,
    PageHeader,
    PageShell,
    Panel,
    Pill,
} from './components';

interface Task {
    id: number;
    title: string;
    category: string | null;
    due_date: string | null;
    is_done: boolean;
}

interface Program {
    id: number;
    start_date: string | null;
    status: string;
    total: number;
    done: number;
    percent: number;
    tasks: Task[];
}

type FlashProps = { flash?: { success?: string } };

export default function SayaOnboarding({
    program,
}: {
    program: Program | null;
}) {
    const { flash } = usePage<FlashProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const toggle = (task: Task) =>
        router.patch(
            `/avana/saya/onboarding/tugas/${task.id}`,
            { is_done: !task.is_done },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Onboarding Saya" />
            <PageShell>
                <PageHeader
                    title="Onboarding Saya"
                    subtitle="Checklist masa bergabungmu. Centang tugas yang sudah selesai."
                />

                {program === null ? (
                    <Panel padded={false}>
                        <EmptyState
                            icon="clipboard-check"
                            message="Belum ada program onboarding untukmu."
                        />
                    </Panel>
                ) : (
                    <>
                        <div style={{ ...card, padding: '22px 24px', marginBottom: 16 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    flexWrap: 'wrap',
                                    gap: 12,
                                    marginBottom: 14,
                                }}
                            >
                                <div>
                                    <div
                                        style={{
                                            fontSize: 15,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        Progres Onboarding
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginTop: 2,
                                        }}
                                    >
                                        Mulai {formatDate(program.start_date)}
                                    </div>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                    }}
                                >
                                    <Pill
                                        label={
                                            program.status === 'completed'
                                                ? 'Selesai'
                                                : 'Berjalan'
                                        }
                                        color={
                                            program.status === 'completed'
                                                ? C.green
                                                : C.amber
                                        }
                                    />
                                    <span
                                        style={{
                                            fontSize: 22,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {program.percent}%
                                    </span>
                                </div>
                            </div>

                            <div
                                style={{
                                    height: 9,
                                    borderRadius: 999,
                                    background: C.line,
                                    overflow: 'hidden',
                                }}
                            >
                                <div
                                    style={{
                                        width: `${program.percent}%`,
                                        height: '100%',
                                        background:
                                            program.percent === 100
                                                ? C.green
                                                : C.primary,
                                        transition: 'width .2s',
                                    }}
                                />
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.faint,
                                    marginTop: 8,
                                }}
                            >
                                {program.done} dari {program.total} tugas
                                selesai
                            </div>
                        </div>

                        <Panel title="Daftar Tugas" padded={false}>
                            {program.tasks.length === 0 ? (
                                <EmptyState
                                    icon="clipboard-check"
                                    message="Belum ada tugas di program ini."
                                />
                            ) : (
                                program.tasks.map((task, index) => (
                                    <label
                                        key={task.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 12,
                                            padding: '14px 18px',
                                            borderTop:
                                                index === 0
                                                    ? 'none'
                                                    : `1px solid ${C.line}`,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={task.is_done}
                                            onChange={() => toggle(task)}
                                            style={{
                                                width: 17,
                                                height: 17,
                                                cursor: 'pointer',
                                                accentColor: C.primary,
                                            }}
                                        />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 500,
                                                    color: task.is_done
                                                        ? C.faint
                                                        : C.text,
                                                    textDecoration: task.is_done
                                                        ? 'line-through'
                                                        : 'none',
                                                }}
                                            >
                                                {task.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {task.category ?? 'Umum'}
                                                {task.due_date &&
                                                    ` · tenggat ${formatDate(task.due_date)}`}
                                            </div>
                                        </div>
                                        {task.is_done && (
                                            <AIcon
                                                name="circle-check"
                                                size={17}
                                                color={C.green}
                                            />
                                        )}
                                    </label>
                                ))
                            )}
                        </Panel>
                    </>
                )}
            </PageShell>
        </>
    );
}
