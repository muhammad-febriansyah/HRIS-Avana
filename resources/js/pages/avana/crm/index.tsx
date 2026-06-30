import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CrmController from '@/actions/App/Http/Controllers/Avana/CrmController';
import { AIcon, ActionBtn, btnOut, btnP, C, card, rp, RupiahInput, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    KpiCard,
    ModalShell,
    selectStyle,
    StageBadge,
    textareaStyle,
    withError,
} from './components';
import {
    emptyContactForm,
    emptyDealForm,
    type ContactFormData,
    type CrmIndexProps,
    type DealCard,
    type DealFormData,
    type FlashProps,
} from './types';

export default function CrmIndex({
    pipeline,
    contacts,
    contactOptions,
    owners,
    stages,
    kpis,
}: CrmIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [contactModalOpen, setContactModalOpen] = useState(false);
    const [dealModalOpen, setDealModalOpen] = useState(false);
    const [editingDeal, setEditingDeal] = useState<DealCard | null>(null);
    const [confirm, setConfirm] = useState<DealCard | null>(null);

    const contactForm = useForm<ContactFormData>({ ...emptyContactForm });
    const dealForm = useForm<DealFormData>({ ...emptyDealForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openContact = () => {
        contactForm.clearErrors();
        contactForm.setData({ ...emptyContactForm });
        setContactModalOpen(true);
    };

    const closeContact = () => {
        setContactModalOpen(false);
        contactForm.reset();
        contactForm.clearErrors();
    };

    const submitContact = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        contactForm.submit(CrmController.storeContact(), {
            onSuccess: () => closeContact(),
        });
    };

    const openDeal = (deal: DealCard | null = null) => {
        dealForm.clearErrors();

        if (deal) {
            dealForm.setData({
                contact_id: deal.contact_id ? String(deal.contact_id) : '',
                title: deal.title,
                value: String(Math.round(deal.value)),
                stage: deal.stage,
                owner_id: deal.owner_id ? String(deal.owner_id) : '',
                expected_close: deal.expected_close ?? '',
                notes: deal.notes ?? '',
            });
        } else {
            dealForm.setData({ ...emptyDealForm });
        }

        setEditingDeal(deal);
        setDealModalOpen(true);
    };

    const closeDeal = () => {
        setDealModalOpen(false);
        setEditingDeal(null);
        dealForm.reset();
        dealForm.clearErrors();
    };

    const submitDeal = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const action = editingDeal
            ? CrmController.updateDeal(editingDeal.id)
            : CrmController.storeDeal();

        dealForm.submit(action, {
            onSuccess: () => closeDeal(),
        });
    };

    const deleteDeal = () => {
        if (!confirm) {
            return;
        }

        router.delete(CrmController.destroyDeal(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    /** Move a deal to an adjacent pipeline stage. */
    const moveStage = (deal: DealCard, direction: -1 | 1) => {
        const order = stages.map((option) => option.value);
        const nextIndex = order.indexOf(deal.stage) + direction;

        if (nextIndex < 0 || nextIndex >= order.length) {
            return;
        }

        router.post(
            CrmController.moveStage(deal.id).url,
            { stage: order[nextIndex] },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="CRM" />
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
                            <span style={{ color: C.muted }}>CRM</span>
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
                            CRM &amp; Sales Pipeline
                        </h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                            Kelola kontak &amp; pipeline penjualan.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <button onClick={openContact} style={btnOut}>
                            <AIcon name="user-plus" size={16} color={C.text} />
                            Tambah Kontak
                        </button>
                        <button onClick={() => openDeal()} style={btnP}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Deal
                        </button>
                    </div>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    <KpiCard
                        label="Total Deal"
                        value={kpis.total_deals}
                        icon="handshake"
                        color={C.primary}
                    />
                    <KpiCard
                        label="Nilai Pipeline"
                        value={rp(kpis.pipeline_value)}
                        icon="trending-up"
                        color={C.amber}
                    />
                    <KpiCard
                        label="Nilai Won"
                        value={rp(kpis.won_value)}
                        icon="circle-check"
                        color={C.green}
                    />
                </div>

                {/* Pipeline board */}
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 12 }}>
                    Pipeline Deal
                </div>
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        overflowX: 'auto',
                        paddingBottom: 8,
                        marginBottom: 28,
                    }}
                >
                    {stages.map((stage) => {
                        const cards = pipeline[stage.value] ?? [];

                        return (
                            <div
                                key={stage.value}
                                style={{
                                    ...card,
                                    flex: '0 0 270px',
                                    background: C.surface,
                                    padding: 12,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                    }}
                                >
                                    <StageBadge stage={stage.value} label={stage.label} />
                                    <span style={{ fontSize: 12, fontWeight: 600, color: C.muted }}>
                                        {cards.length}
                                    </span>
                                </div>

                                {cards.length === 0 && (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.faint,
                                            textAlign: 'center',
                                            padding: '18px 0',
                                        }}
                                    >
                                        Kosong
                                    </div>
                                )}

                                {cards.map((deal) => (
                                    <div
                                        key={deal.id}
                                        style={{
                                            background: '#fff',
                                            border: `1px solid ${C.border}`,
                                            borderRadius: 10,
                                            padding: 12,
                                        }}
                                    >
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {deal.title}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 14,
                                                fontWeight: 700,
                                                color: C.primary,
                                                marginTop: 4,
                                            }}
                                        >
                                            {rp(deal.value)}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.muted,
                                                marginTop: 6,
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 5,
                                            }}
                                        >
                                            <AIcon name="user" size={12} color={C.faint} />
                                            {deal.contact ?? 'Tanpa kontak'}
                                            {deal.company ? ` · ${deal.company}` : ''}
                                        </div>
                                        {deal.owner && (
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 4,
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 5,
                                                }}
                                            >
                                                <AIcon
                                                    name="briefcase"
                                                    size={12}
                                                    color={C.faint}
                                                />
                                                {deal.owner}
                                            </div>
                                        )}
                                        <div
                                            style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                                marginTop: 10,
                                                gap: 6,
                                            }}
                                        >
                                            <button
                                                title="Tahap sebelumnya"
                                                onClick={() => moveStage(deal, -1)}
                                                style={iconBtn}
                                            >
                                                <AIcon
                                                    name="chevron-left"
                                                    size={15}
                                                    color={C.muted}
                                                />
                                            </button>
                                            <div style={{ display: 'flex', gap: 6 }}>
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Ubah"
                                                    variant="neutral"
                                                    onClick={() => openDeal(deal)}
                                                />
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() => setConfirm(deal)}
                                                />
                                            </div>
                                            <button
                                                title="Tahap berikutnya"
                                                onClick={() => moveStage(deal, 1)}
                                                style={iconBtn}
                                            >
                                                <AIcon
                                                    name="chevron-right"
                                                    size={15}
                                                    color={C.muted}
                                                />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        );
                    })}
                </div>

                {/* Contacts table */}
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 12 }}>
                    Daftar Kontak
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{ width: '100%', borderCollapse: 'collapse', minWidth: 700 }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Nama</th>
                                    <th style={thCell}>Perusahaan</th>
                                    <th style={thCell}>Email</th>
                                    <th style={thCell}>Telepon</th>
                                    <th style={thCell}>Deal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {contacts.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            colSpan={5}
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
                                                    name="contact"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada kontak.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {contacts.map((contact) => (
                                    <tr
                                        key={contact.id}
                                        style={{ borderTop: `1px solid ${C.line}` }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {contact.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {contact.company ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {contact.email ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {contact.phone ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {contact.deals_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Add contact modal */}
            {contactModalOpen && (
                <ModalShell
                    title="Tambah Kontak"
                    subtitle="Catat kontak atau prospek baru."
                    onClose={closeContact}
                    onSubmit={submitContact}
                    processing={contactForm.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Nama <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={contactForm.data.name}
                            onChange={(event) => contactForm.setData('name', event.target.value)}
                            placeholder="Nama kontak"
                            style={withError(inputStyle, !!contactForm.errors.name)}
                        />
                        <FieldError message={contactForm.errors.name} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Perusahaan</label>
                        <input
                            type="text"
                            value={contactForm.data.company}
                            onChange={(event) =>
                                contactForm.setData('company', event.target.value)
                            }
                            placeholder="Nama perusahaan"
                            style={withError(inputStyle, !!contactForm.errors.company)}
                        />
                        <FieldError message={contactForm.errors.company} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        <div>
                            <label style={fieldLabelStyle}>Email</label>
                            <input
                                type="email"
                                value={contactForm.data.email}
                                onChange={(event) =>
                                    contactForm.setData('email', event.target.value)
                                }
                                placeholder="email@contoh.com"
                                style={withError(inputStyle, !!contactForm.errors.email)}
                            />
                            <FieldError message={contactForm.errors.email} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Telepon</label>
                            <input
                                type="text"
                                value={contactForm.data.phone}
                                onChange={(event) =>
                                    contactForm.setData('phone', event.target.value)
                                }
                                placeholder="08xxxxxxxxxx"
                                style={withError(inputStyle, !!contactForm.errors.phone)}
                            />
                            <FieldError message={contactForm.errors.phone} />
                        </div>
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Catatan</label>
                        <textarea
                            value={contactForm.data.notes}
                            onChange={(event) => contactForm.setData('notes', event.target.value)}
                            placeholder="Catatan (opsional)"
                            style={withError(textareaStyle, !!contactForm.errors.notes)}
                        />
                        <FieldError message={contactForm.errors.notes} />
                    </div>
                </ModalShell>
            )}

            {/* Add / edit deal modal */}
            {dealModalOpen && (
                <ModalShell
                    title={editingDeal ? 'Ubah Deal' : 'Tambah Deal'}
                    subtitle="Catat peluang penjualan beserta nilainya."
                    onClose={closeDeal}
                    onSubmit={submitDeal}
                    processing={dealForm.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Judul <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={dealForm.data.title}
                            onChange={(event) => dealForm.setData('title', event.target.value)}
                            placeholder="Nama deal"
                            style={withError(inputStyle, !!dealForm.errors.title)}
                        />
                        <FieldError message={dealForm.errors.title} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        <div>
                            <label style={fieldLabelStyle}>
                                Nilai <span style={{ color: C.red }}>*</span>
                            </label>
                            <RupiahInput
                                value={dealForm.data.value}
                                onChange={(raw) => dealForm.setData('value', raw)}
                                invalid={!!dealForm.errors.value}
                            />
                            <FieldError message={dealForm.errors.value} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>
                                Tahap <span style={{ color: C.red }}>*</span>
                            </label>
                            <select
                                value={dealForm.data.stage}
                                onChange={(event) => dealForm.setData('stage', event.target.value)}
                                style={withError(selectStyle, !!dealForm.errors.stage)}
                            >
                                {stages.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={dealForm.errors.stage} />
                        </div>
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Kontak</label>
                        <select
                            value={dealForm.data.contact_id}
                            onChange={(event) => dealForm.setData('contact_id', event.target.value)}
                            style={withError(selectStyle, !!dealForm.errors.contact_id)}
                        >
                            <option value="">Tanpa kontak</option>
                            {contactOptions.map((option) => (
                                <option key={option.id} value={String(option.id)}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={dealForm.errors.contact_id} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Pemilik (PIC)</label>
                        <select
                            value={dealForm.data.owner_id}
                            onChange={(event) => dealForm.setData('owner_id', event.target.value)}
                            style={withError(selectStyle, !!dealForm.errors.owner_id)}
                        >
                            <option value="">Tanpa pemilik</option>
                            {owners.map((option) => (
                                <option key={option.id} value={String(option.id)}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={dealForm.errors.owner_id} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Perkiraan Closing</label>
                        <input
                            type="date"
                            value={dealForm.data.expected_close}
                            onChange={(event) =>
                                dealForm.setData('expected_close', event.target.value)
                            }
                            style={withError(inputStyle, !!dealForm.errors.expected_close)}
                        />
                        <FieldError message={dealForm.errors.expected_close} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Catatan</label>
                        <textarea
                            value={dealForm.data.notes}
                            onChange={(event) => dealForm.setData('notes', event.target.value)}
                            placeholder="Catatan (opsional)"
                            style={withError(textareaStyle, !!dealForm.errors.notes)}
                        />
                        <FieldError message={dealForm.errors.notes} />
                    </div>
                </ModalShell>
            )}

            {/* Confirm delete deal modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus deal?"
                    body={
                        <>
                            Deal{' '}
                            <strong style={{ color: C.text }}>{confirm.title}</strong> akan
                            dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteDeal}
                />
            )}
        </>
    );
}
