import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import SettlementController from '@/actions/App/Http/Controllers/Avana/SettlementController';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, C, card, rp } from '@/lib/avana';
import {
    dateInputStyle,
    Field,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import { emptySettlementForm } from './types';
import type {
    FlashProps,
    SettlementCreateProps,
    SettlementFormData,
} from './types';

export default function SettlementCreate({ advances }: SettlementCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<SettlementFormData>({ ...emptySettlementForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(SettlementController.store());
    };

    const selected = advances.find(
        (advance) => String(advance.id) === form.data.cash_advance_id,
    );

    return (
        <>
            <Head title="Buka Settlement" />
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
                        href={SettlementController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Settlement
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buka Settlement</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 24px',
                        letterSpacing: '-.01em',
                    }}
                >
                    Pertanggungjawaban Uang Muka
                </h1>

                {advances.length === 0 ? (
                    <div
                        style={{
                            ...card,
                            padding: '48px 24px',
                            textAlign: 'center',
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
                                name="file-check"
                                size={28}
                                color={C.faint}
                            />
                            <div style={{ fontSize: 14, color: C.muted }}>
                                Tidak ada uang muka yang menunggu
                                dipertanggungjawabkan.
                            </div>
                            <div style={{ fontSize: 12.5, color: C.faint }}>
                                Settlement hanya bisa dibuka untuk uang muka
                                yang sudah dicairkan.
                            </div>
                        </div>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} style={{ ...card }}>
                        <div
                            style={{
                                padding: '22px 24px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <Field
                                label="Uang Muka"
                                required
                                error={form.errors.cash_advance_id}
                            >
                                <SearchableSelect
                                    value={form.data.cash_advance_id}
                                    onChange={(value) =>
                                        form.setData('cash_advance_id', value)
                                    }
                                    options={advances.map((advance) => ({
                                        value: String(advance.id),
                                        label: `${advance.employee_name ?? '—'} — ${rp(advance.amount)} (${advance.purpose ?? 'Tanpa keperluan'})`,
                                    }))}
                                    placeholder="Pilih uang muka yang sudah dicairkan"
                                    searchPlaceholder="Cari karyawan / keperluan…"
                                    allowClear
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.cash_advance_id,
                                    )}
                                />
                            </Field>

                            {selected && (
                                <div
                                    style={{
                                        background: C.surface,
                                        borderRadius: 8,
                                        padding: '13px 15px',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 6,
                                        fontSize: 12.5,
                                        color: C.muted,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                        }}
                                    >
                                        <span>Nilai uang muka</span>
                                        <strong style={{ color: C.navy }}>
                                            {rp(selected.amount)}
                                        </strong>
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                        }}
                                    >
                                        <span>Dicairkan</span>
                                        <span style={{ color: C.text }}>
                                            {selected.disbursed_at ?? '—'}
                                        </span>
                                    </div>
                                </div>
                            )}

                            <Field
                                label="Tanggal Settlement"
                                required
                                error={form.errors.settlement_date}
                            >
                                <input
                                    type="date"
                                    value={form.data.settlement_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'settlement_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        dateInputStyle,
                                        !!form.errors.settlement_date,
                                    )}
                                />
                            </Field>

                            <Field label="Catatan" error={form.errors.notes}>
                                <textarea
                                    rows={3}
                                    placeholder="Catatan pertanggungjawaban (opsional)"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        textareaStyle,
                                        !!form.errors.notes,
                                    )}
                                />
                            </Field>

                            <div
                                style={{
                                    background: C.surface,
                                    borderRadius: 8,
                                    padding: '11px 13px',
                                    display: 'flex',
                                    alignItems: 'flex-start',
                                    gap: 9,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    lineHeight: 1.5,
                                }}
                            >
                                <AIcon
                                    name="info"
                                    size={15}
                                    color={C.primary}
                                />
                                <span>
                                    Setelah dibuka, unggah bukti pengeluaran
                                    satu per satu. Selisihnya dihitung otomatis:
                                    sisa dana dikembalikan karyawan, kekurangan
                                    dibayarkan perusahaan.
                                </span>
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                justifyContent: 'flex-end',
                                padding: '16px 24px',
                                borderTop: `1px solid ${C.line}`,
                            }}
                        >
                            <Link
                                href={SettlementController.index()}
                                style={{
                                    ...btnOut,
                                    height: 44,
                                    justifyContent: 'center',
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon name="x" size={16} />
                                Batal
                            </Link>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: form.processing ? 0.7 : 1,
                                    cursor: form.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="plus" size={16} color="#fff" />
                                Buka Settlement
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}
