import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import HelpdeskController from '@/actions/App/Http/Controllers/Avana/HelpdeskController';
import { AIcon, btnP, C, card } from '@/lib/avana';
import { StatusBadge, textareaStyle } from './components';
import { HelpdeskForm } from './helpdesk-form';
import type {
    EmployeeOption,
    FlashProps,
    TicketDetail,
    TicketFormData,
    UserOption,
} from './types';

interface HelpdeskEditProps {
    ticket: TicketDetail;
    employees: EmployeeOption[];
    users: UserOption[];
}

export default function HelpdeskEdit({
    ticket,
    employees,
    users,
}: HelpdeskEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<TicketFormData>({
        requester_id: String(ticket.requester_id),
        category: ticket.category,
        subject: ticket.subject,
        description: ticket.description,
        priority: ticket.priority,
        assignee_id: ticket.assignee_id ? String(ticket.assignee_id) : '',
    });

    const replyForm = useForm<{ message: string }>({ message: '' });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(HelpdeskController.update(ticket.id));
    };

    const submitReply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        replyForm.submit(HelpdeskController.reply(ticket.id), {
            preserveScroll: true,
            onSuccess: () => replyForm.reset(),
        });
    };

    return (
        <>
            <Head title={`Tiket ${ticket.ticket_no}`} />
            <div style={{ padding: '28px 32px' }}>
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
                        href={HelpdeskController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Helpdesk
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{ticket.ticket_no}</span>
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        margin: '0 0 24px',
                        flexWrap: 'wrap',
                    }}
                >
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Ubah Tiket
                    </h1>
                    <StatusBadge status={ticket.status} />
                </div>

                <HelpdeskForm
                    form={form}
                    employees={employees}
                    users={users}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={HelpdeskController.index().url}
                    onSubmit={handleSubmit}
                />

                {/* Reply thread */}
                <div style={{ ...card, maxWidth: 640, marginTop: 22 }}>
                    <div
                        style={{
                            padding: '18px 24px',
                            borderBottom: `1px solid ${C.line}`,
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Diskusi Tiket
                    </div>

                    <div
                        style={{
                            padding: '18px 24px',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        {ticket.replies.length === 0 && (
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.faint,
                                    textAlign: 'center',
                                    padding: '14px 0',
                                }}
                            >
                                Belum ada balasan.
                            </div>
                        )}

                        {ticket.replies.map((reply) => (
                            <div
                                key={reply.id}
                                style={{
                                    background: C.surface,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 10,
                                    padding: '12px 14px',
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        marginBottom: 6,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 12.5,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {reply.user ?? '—'}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                        }}
                                    >
                                        {reply.created_at ?? ''}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: C.text,
                                        lineHeight: 1.55,
                                        whiteSpace: 'pre-wrap',
                                    }}
                                >
                                    {reply.message}
                                </div>
                            </div>
                        ))}
                    </div>

                    <form
                        onSubmit={submitReply}
                        style={{
                            padding: '16px 24px',
                            borderTop: `1px solid ${C.line}`,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        <textarea
                            value={replyForm.data.message}
                            onChange={(event) =>
                                replyForm.setData('message', event.target.value)
                            }
                            placeholder="Tulis balasan..."
                            style={textareaStyle}
                        />
                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <button
                                type="submit"
                                disabled={
                                    replyForm.processing ||
                                    replyForm.data.message.trim() === ''
                                }
                                style={{
                                    ...btnP,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity:
                                        replyForm.processing ||
                                        replyForm.data.message.trim() === ''
                                            ? 0.6
                                            : 1,
                                    cursor:
                                        replyForm.processing ||
                                        replyForm.data.message.trim() === ''
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                <AIcon name="send" size={16} color="#fff" />
                                Kirim Balasan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
