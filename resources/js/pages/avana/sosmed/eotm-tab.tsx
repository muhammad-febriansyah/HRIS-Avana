import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import SocialController from '@/actions/App/Http/Controllers/Avana/SocialController';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    ColorPicker,
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    IconPicker,
    inputStyle,
    ModalShell,
    textareaStyle,
    withError,
} from './components';
import { emptyCoreValueForm, emptyPeriodForm } from './types';
import type {
    CoreValueFormData,
    EotmCoreValue,
    EotmPayload,
    PeriodFormData,
} from './types';

/**
 * Employee of the Month administration: open and close a voting month, watch
 * the live tally, and curate the core values a voter picks from.
 *
 * Votes themselves are cast in the mobile app — nothing here casts one.
 */
export function EotmTab({ eotm }: { eotm: EotmPayload }) {
    const [periodModal, setPeriodModal] = useState(false);
    const [valueModal, setValueModal] = useState(false);
    const [confirmValue, setConfirmValue] = useState<EotmCoreValue | null>(
        null,
    );
    const [confirmClose, setConfirmClose] = useState(false);
    const [confirmReopen, setConfirmReopen] = useState(false);

    const periodForm = useForm<PeriodFormData>({
        ...emptyPeriodForm,
        // Default to this month, the case that needs no thinking.
        period: new Date().toISOString().slice(0, 7),
    });
    const valueForm = useForm<CoreValueFormData>({ ...emptyCoreValueForm });

    const { period, standings, core_values: coreValues, history } = eotm;

    const submitPeriod = () => {
        periodForm.submit(SocialController.storePeriod(), {
            preserveScroll: true,
            onSuccess: () => {
                setPeriodModal(false);
                periodForm.reset();
            },
        });
    };

    const submitValue = () => {
        valueForm.submit(SocialController.storeCoreValue(), {
            preserveScroll: true,
            onSuccess: () => {
                setValueModal(false);
                valueForm.reset();
            },
        });
    };

    const reopenPeriod = () => {
        if (!period) {
            return;
        }

        router.post(
            SocialController.reopenPeriod(period.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setConfirmReopen(false),
            },
        );
    };

    const closePeriod = () => {
        if (!period) {
            return;
        }

        router.post(
            SocialController.closePeriod(period.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setConfirmClose(false),
            },
        );
    };

    return (
        <>
            {/* Period banner */}
            {period === null ? (
                <div
                    style={{
                        ...card,
                        padding: '36px 20px',
                        textAlign: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name="crown" size={28} color={C.faint} />
                    <div
                        style={{
                            fontSize: 14,
                            color: C.muted,
                            margin: '10px 0 16px',
                        }}
                    >
                        Belum ada periode voting. Buka satu agar karyawan bisa
                        mulai memilih dari aplikasi.
                    </div>
                    <button onClick={() => setPeriodModal(true)} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Buka Periode Voting
                    </button>
                </div>
            ) : (
                <div
                    style={{
                        ...card,
                        padding: 20,
                        marginBottom: 16,
                        // Flat brand fill, no gradient — the colour alone says
                        // open vs closed, like the status pills elsewhere.
                        background: period.is_open ? C.primary : C.navy,
                        border: 'none',
                        color: '#fff',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            flexWrap: 'wrap',
                        }}
                    >
                        <AIcon name="crown" size={20} color="#FBBF24" />
                        <span style={{ fontSize: 17, fontWeight: 700 }}>
                            {period.label}
                        </span>
                        <span
                            style={{
                                padding: '3px 10px',
                                borderRadius: 100,
                                fontSize: 10.5,
                                fontWeight: 700,
                                letterSpacing: '.04em',
                                background: 'rgba(255,255,255,.2)',
                            }}
                        >
                            {period.is_open ? 'BERLANGSUNG' : 'DITUTUP'}
                        </span>
                        <div
                            style={{
                                marginLeft: 'auto',
                                display: 'flex',
                                gap: 8,
                            }}
                        >
                            {period.is_open ? (
                                <button
                                    onClick={() => setConfirmClose(true)}
                                    style={{
                                        ...btnOut,
                                        background: 'rgba(255,255,255,.15)',
                                        border: '1px solid rgba(255,255,255,.35)',
                                        color: '#fff',
                                    }}
                                >
                                    <AIcon name="lock" size={15} color="#fff" />
                                    Tutup Voting
                                </button>
                            ) : (
                                <>
                                    <button
                                        onClick={() => setConfirmReopen(true)}
                                        style={{
                                            ...btnOut,
                                            background: 'rgba(255,255,255,.15)',
                                            border: '1px solid rgba(255,255,255,.35)',
                                            color: '#fff',
                                        }}
                                    >
                                        <AIcon
                                            name="rotate-ccw"
                                            size={15}
                                            color="#fff"
                                        />
                                        Buka Ulang
                                    </button>
                                    <button
                                        onClick={() => setPeriodModal(true)}
                                        style={{
                                            ...btnOut,
                                            background: 'rgba(255,255,255,.15)',
                                            border: '1px solid rgba(255,255,255,.35)',
                                            color: '#fff',
                                        }}
                                    >
                                        <AIcon
                                            name="plus"
                                            size={15}
                                            color="#fff"
                                        />
                                        Periode Baru
                                    </button>
                                </>
                            )}
                        </div>
                    </div>

                    <div
                        style={{
                            fontSize: 13,
                            marginTop: 10,
                            opacity: 0.9,
                        }}
                    >
                        {!period.is_open && period.winner
                            ? `Pemenang: ${period.winner} — ${period.winner_votes} suara`
                            : `${period.total_votes} suara masuk`}
                    </div>

                    {period.description ? (
                        <div
                            style={{
                                fontSize: 12.5,
                                marginTop: 6,
                                opacity: 0.8,
                                lineHeight: 1.5,
                            }}
                        >
                            {period.description}
                        </div>
                    ) : null}
                </div>
            )}

            {/* Live tally */}
            <div style={{ ...card, overflow: 'hidden', marginBottom: 16 }}>
                <div
                    style={{
                        padding: '14px 16px',
                        borderBottom: `1px solid ${C.line}`,
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    Perolehan Suara
                </div>
                {standings.length === 0 ? (
                    <div
                        style={{
                            padding: '36px 18px',
                            textAlign: 'center',
                            fontSize: 13.5,
                            color: C.muted,
                        }}
                    >
                        Belum ada suara masuk.
                    </div>
                ) : (
                    <div style={{ padding: 16 }}>
                        {standings.map((row) => (
                            <div
                                key={row.employee_id}
                                style={{ marginBottom: 14 }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        marginBottom: 6,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 700,
                                            color:
                                                row.rank <= 3
                                                    ? C.amber
                                                    : C.muted,
                                            width: 20,
                                        }}
                                    >
                                        {row.rank}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {row.name}
                                    </span>
                                    {row.core_value ? (
                                        <span
                                            style={{
                                                fontSize: 11,
                                                fontWeight: 600,
                                                color:
                                                    row.core_value_color ??
                                                    C.muted,
                                            }}
                                        >
                                            {row.core_value}
                                        </span>
                                    ) : null}
                                    <span
                                        style={{
                                            marginLeft: 'auto',
                                            fontSize: 12.5,
                                            fontWeight: 700,
                                            color: C.primary,
                                        }}
                                    >
                                        {row.votes} suara
                                    </span>
                                </div>
                                <div
                                    style={{
                                        height: 6,
                                        borderRadius: 99,
                                        background: 'rgba(15,23,42,.07)',
                                        overflow: 'hidden',
                                    }}
                                >
                                    <div
                                        style={{
                                            height: '100%',
                                            width: `${row.percent}%`,
                                            borderRadius: 99,
                                            background:
                                                row.core_value_color ??
                                                C.primary,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Core values */}
            <div style={{ ...card, overflow: 'hidden', marginBottom: 16 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        padding: '14px 16px',
                        borderBottom: `1px solid ${C.line}`,
                    }}
                >
                    <div
                        style={{
                            fontSize: 14,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Core Value
                    </div>
                    <button
                        onClick={() => setValueModal(true)}
                        style={{ ...btnOut, marginLeft: 'auto', height: 34 }}
                    >
                        <AIcon name="plus" size={15} />
                        Tambah
                    </button>
                </div>
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 8,
                        padding: 16,
                    }}
                >
                    {coreValues.length === 0 ? (
                        <div style={{ fontSize: 13, color: C.muted }}>
                            Belum ada core value.
                        </div>
                    ) : (
                        coreValues.map((value) => (
                            <span
                                key={value.id}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    padding: '6px 10px 6px 12px',
                                    borderRadius: 100,
                                    fontSize: 12,
                                    fontWeight: 600,
                                    color: value.color,
                                    background: `${value.color}18`,
                                }}
                            >
                                <AIcon
                                    name={value.icon}
                                    size={13}
                                    color={value.color}
                                />
                                {value.name}
                                <button
                                    onClick={() => setConfirmValue(value)}
                                    aria-label={`Hapus ${value.name}`}
                                    style={{
                                        border: 'none',
                                        background: 'transparent',
                                        cursor: 'pointer',
                                        display: 'inline-flex',
                                        padding: 0,
                                    }}
                                >
                                    <AIcon
                                        name="x"
                                        size={13}
                                        color={value.color}
                                    />
                                </button>
                            </span>
                        ))
                    )}
                </div>
            </div>

            {/* Roll of honour */}
            {history.length > 0 && (
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 420,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Periode</th>
                                    <th style={thCell}>Pemenang</th>
                                    <th style={thCell}>Suara</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {row.label}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {row.winner ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {row.winner_votes}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Open-period modal */}
            {periodModal && (
                <ModalShell
                    title="Buka Periode Voting"
                    subtitle="Satu periode per bulan. Membuka periode baru otomatis menutup yang sedang berjalan."
                    onClose={() => setPeriodModal(false)}
                    onSubmit={submitPeriod}
                    processing={periodForm.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Bulan <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="month"
                            name="period"
                            value={periodForm.data.period}
                            onChange={(event) =>
                                periodForm.setData('period', event.target.value)
                            }
                            style={withError(
                                inputStyle,
                                !!periodForm.errors.period,
                            )}
                        />
                        <FieldError message={periodForm.errors.period} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Judul</label>
                        <input
                            type="text"
                            name="title"
                            value={periodForm.data.title}
                            onChange={(event) =>
                                periodForm.setData('title', event.target.value)
                            }
                            placeholder="contoh: Employee of the Month Juli"
                            style={withError(
                                inputStyle,
                                !!periodForm.errors.title,
                            )}
                        />
                        <FieldError message={periodForm.errors.title} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Keterangan</label>
                        <textarea
                            name="description"
                            value={periodForm.data.description}
                            onChange={(event) =>
                                periodForm.setData(
                                    'description',
                                    event.target.value,
                                )
                            }
                            placeholder="Muncul di aplikasi karyawan sebagai pengantar voting."
                            style={withError(
                                textareaStyle,
                                !!periodForm.errors.description,
                            )}
                        />
                        <FieldError message={periodForm.errors.description} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Batas Waktu (opsional)
                        </label>
                        <input
                            type="date"
                            name="closes_at"
                            value={periodForm.data.closes_at}
                            onChange={(event) =>
                                periodForm.setData(
                                    'closes_at',
                                    event.target.value,
                                )
                            }
                            style={withError(
                                inputStyle,
                                !!periodForm.errors.closes_at,
                            )}
                        />
                        <FieldError message={periodForm.errors.closes_at} />
                    </div>
                </ModalShell>
            )}

            {/* Core-value modal */}
            {valueModal && (
                <ModalShell
                    title="Tambah Core Value"
                    subtitle="Pemilih memilih satu core value untuk rekan yang dia vote."
                    onClose={() => setValueModal(false)}
                    onSubmit={submitValue}
                    processing={valueForm.processing}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Nama <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            value={valueForm.data.name}
                            onChange={(event) =>
                                valueForm.setData('name', event.target.value)
                            }
                            placeholder="contoh: Jujur"
                            style={withError(
                                inputStyle,
                                !!valueForm.errors.name,
                            )}
                        />
                        <FieldError message={valueForm.errors.name} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Ikon</label>
                        <IconPicker
                            value={valueForm.data.icon}
                            onChange={(icon) => valueForm.setData('icon', icon)}
                            accent={valueForm.data.color}
                        />
                        <FieldError message={valueForm.errors.icon} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Warna</label>
                        <ColorPicker
                            value={valueForm.data.color}
                            onChange={(color) =>
                                valueForm.setData('color', color)
                            }
                        />
                        <FieldError message={valueForm.errors.color} />
                    </div>
                </ModalShell>
            )}

            {confirmValue && (
                <ConfirmModal
                    title="Hapus core value?"
                    body={
                        <>
                            <strong style={{ color: C.text }}>
                                {confirmValue.name}
                            </strong>{' '}
                            tidak akan muncul lagi di surat suara. Vote yang
                            sudah menyebutnya tetap tersimpan.
                        </>
                    }
                    onCancel={() => setConfirmValue(null)}
                    onConfirm={() => {
                        router.delete(
                            SocialController.destroyCoreValue(confirmValue.id)
                                .url,
                            {
                                preserveScroll: true,
                                onSuccess: () => setConfirmValue(null),
                            },
                        );
                    }}
                />
            )}

            {confirmReopen && period && (
                <ConfirmModal
                    title="Buka ulang voting?"
                    body={
                        <>
                            {period.label} akan menerima suara lagi.{' '}
                            {period.winner
                                ? `Pemenang tercatat (${period.winner}) dihapus dan dihitung ulang saat voting ditutup kembali.`
                                : 'Hasilnya akan dihitung ulang saat ditutup kembali.'}
                        </>
                    }
                    confirmLabel="Buka Ulang"
                    icon="rotate-ccw"
                    tone={C.primary}
                    onCancel={() => setConfirmReopen(false)}
                    onConfirm={reopenPeriod}
                />
            )}

            {confirmClose && period && (
                <ConfirmModal
                    title="Tutup voting?"
                    body={
                        <>
                            Perolehan {period.label} akan dikunci dan pemenang
                            dicatat permanen. Karyawan tidak bisa memberi suara
                            lagi setelah ini.
                        </>
                    }
                    confirmLabel="Tutup Voting"
                    icon="lock"
                    tone={C.navy}
                    onCancel={() => setConfirmClose(false)}
                    onConfirm={closePeriod}
                />
            )}
        </>
    );
}
