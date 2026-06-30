import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import HelpdeskController from '@/actions/App/Http/Controllers/Avana/HelpdeskController';
import { AIcon, btnP, C, card, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    fieldLabelStyle,
    iconBtn,
    PriorityBadge,
    selectStyle,
    StatusBadge,
} from './components';
import { categoryLabel } from './types';
import type { HelpdeskIndexProps, TicketRow } from './types';
import type { FlashProps } from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

const compactSelectStyle: CSSProperties = {
    ...selectStyle,
    height: 34,
    fontSize: 12.5,
    padding: '0 10px',
    color: C.text,
};

export default function HelpdeskIndex({
    tickets,
    users,
    statuses,
    kpis,
}: HelpdeskIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<TicketRow | null>(null);
    const [assignTicket, setAssignTicket] = useState<TicketRow | null>(null);
    const [assigneeId, setAssigneeId] = useState('');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const deleteTicket = () => {
        if (!confirm) {
            return;
        }

        router.delete(HelpdeskController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    /** Move a ticket to a new workflow status. */
    const changeStatus = (ticket: TicketRow, status: string) => {
        if (status === ticket.status) {
            return;
        }

        router.post(
            HelpdeskController.changeStatus(ticket.id).url,
            { status },
            { preserveScroll: true },
        );
    };

    const openAssign = (ticket: TicketRow) => {
        setAssigneeId(ticket.assignee_id ? String(ticket.assignee_id) : '');
        setAssignTicket(ticket);
    };

    const submitAssign = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!assignTicket) {
            return;
        }

        router.post(
            HelpdeskController.assign(assignTicket.id).url,
            { assignee_id: assigneeId === '' ? null : Number(assigneeId) },
            {
                preserveScroll: true,
                onSuccess: () => setAssignTicket(null),
            },
        );
    };

    const kpiItems = [
        {
            label: 'Terbuka',
            value: kpis.open,
            icon: 'inbox',
            color: C.primary,
        },
        {
            label: 'Diproses',
            value: kpis.in_progress,
            icon: 'loader',
            color: C.amber,
        },
        {
            label: 'Selesai',
            value: kpis.resolved,
            icon: 'circle-check',
            color: C.green,
        },
        {
            label: 'Ditutup',
            value: kpis.closed,
            icon: 'archive',
            color: C.muted,
        },
    ];

    return (
        <>
            <Head title="Helpdesk" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Helpdesk</span>
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
                            HR Helpdesk
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola tiket dukungan karyawan.
                        </div>
                    </div>
                    <Link
                        href={HelpdeskController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Buat Tiket
                    </Link>
                </div>

                {/* KPI cards */}
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 14,
                        marginBottom: 22,
                    }}
                >
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    marginBottom: 10,
                                }}
                            >
                                <div
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 9,
                                        background: `${item.color}1a`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name={item.icon}
                                        size={17}
                                        color={item.color}
                                    />
                                </div>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        fontWeight: 500,
                                    }}
                                >
                                    {item.label}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 26,
                                    fontWeight: 700,
                                    color: C.navy,
                                    letterSpacing: '-.02em',
                                }}
                            >
                                {item.value}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Tickets table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Daftar Tiket
                </div>
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
                                    <th style={thCell}>No. Tiket</th>
                                    <th style={thCell}>Pelapor</th>
                                    <th style={thCell}>Kategori</th>
                                    <th style={thCell}>Subjek</th>
                                    <th style={thCell}>Prioritas</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>Penanggung Jawab</th>
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
                                {tickets.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={8}
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
                                                <AIcon
                                                    name="ticket"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada tiket.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {tickets.map((ticket) => (
                                    <tr
                                        key={ticket.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.navy,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {ticket.ticket_no}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            <div style={{ fontWeight: 500 }}>
                                                {ticket.requester ?? '—'}
                                            </div>
                                            {ticket.requester_number && (
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    {ticket.requester_number}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {categoryLabel(ticket.category)}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                                maxWidth: 260,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    fontWeight: 500,
                                                    color: C.navy,
                                                }}
                                            >
                                                {ticket.subject}
                                            </div>
                                            {ticket.replies_count > 0 && (
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                        marginTop: 2,
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 4,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="message-circle"
                                                        size={12}
                                                        color={C.faint}
                                                    />
                                                    {ticket.replies_count}{' '}
                                                    balasan
                                                </div>
                                            )}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <PriorityBadge
                                                priority={ticket.priority}
                                            />
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusBadge
                                                status={ticket.status}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: ticket.assignee
                                                    ? C.text
                                                    : C.faint,
                                            }}
                                        >
                                            {ticket.assignee ??
                                                'Belum ditugaskan'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                }}
                                            >
                                                <select
                                                    title="Ubah status"
                                                    value={ticket.status}
                                                    onChange={(event) =>
                                                        changeStatus(
                                                            ticket,
                                                            event.target.value,
                                                        )
                                                    }
                                                    style={{
                                                        ...compactSelectStyle,
                                                        width: 120,
                                                    }}
                                                >
                                                    {statuses.map((option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <button
                                                    title="Tugaskan"
                                                    onClick={() =>
                                                        openAssign(ticket)
                                                    }
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="user-plus"
                                                        size={15}
                                                        color={C.muted}
                                                    />
                                                </button>
                                                <Link
                                                    title="Ubah"
                                                    href={HelpdeskController.edit(
                                                        ticket.id,
                                                    )}
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="pencil"
                                                        size={15}
                                                        color={C.muted}
                                                    />
                                                </Link>
                                                <button
                                                    title="Hapus"
                                                    onClick={() =>
                                                        setConfirm(ticket)
                                                    }
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="trash-2"
                                                        size={15}
                                                        color={C.red}
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Assign ticket modal */}
            {assignTicket && (
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
                        onClick={() => setAssignTicket(null)}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitAssign}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 420,
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Tugaskan Tiket
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Pilih penanggung jawab untuk tiket{' '}
                            <strong style={{ color: C.text }}>
                                {assignTicket.ticket_no}
                            </strong>
                            .
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Penanggung Jawab
                            </label>
                            <select
                                value={assigneeId}
                                onChange={(event) =>
                                    setAssigneeId(event.target.value)
                                }
                                style={selectStyle}
                            >
                                <option value="">Belum ditugaskan</option>
                                {users.map((user) => (
                                    <option
                                        key={user.id}
                                        value={String(user.id)}
                                    >
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={() => setAssignTicket(null)}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete ticket modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus tiket?"
                    body={
                        <>
                            Tiket{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.ticket_no}
                            </strong>{' '}
                            beserta seluruh balasannya akan dihapus. Tindakan ini
                            tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteTicket}
                />
            )}
        </>
    );
}
