import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import OkrController from '@/actions/App/Http/Controllers/Avana/OkrController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    LevelBadge,
    ProgressBar,
    StatusBadge,
    withError,
} from './components';
import { emptyKeyResultForm } from './types';
import type {
    FlashProps,
    KeyResultFormData,
    KeyResultRow,
    ObjectiveRow,
    OkrIndexProps,
} from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

const miniInputStyle: CSSProperties = {
    ...inputStyle,
    height: 34,
    width: 90,
    fontSize: 12.5,
    padding: '0 9px',
};

/** A single key result row with an inline progress-update control. */
function KeyResultItem({ keyResult }: { keyResult: KeyResultRow }) {
    const [value, setValue] = useState<string>(String(keyResult.current_value));
    const [saving, setSaving] = useState(false);

    const saveProgress = () => {
        setSaving(true);
        router.put(
            OkrController.updateKeyResult(keyResult.id).url,
            {
                title: keyResult.title,
                target_value: keyResult.target_value,
                current_value: value,
                unit: keyResult.unit ?? '',
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const deleteKr = () => {
        router.delete(OkrController.destroyKeyResult(keyResult.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <div
            style={{
                padding: '12px 0',
                borderTop: `1px solid ${C.line}`,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    marginBottom: 8,
                }}
            >
                <div style={{ fontSize: 13, color: C.text, fontWeight: 500 }}>
                    {keyResult.title}
                </div>
                <div
                    style={{
                        fontSize: 12,
                        color: C.faint,
                        whiteSpace: 'nowrap',
                    }}
                >
                    {keyResult.current_value} / {keyResult.target_value}
                    {keyResult.unit ? ` ${keyResult.unit}` : ''}
                </div>
            </div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                }}
            >
                <div style={{ flex: 1 }}>
                    <ProgressBar value={keyResult.progress} height={6} />
                </div>
                <span
                    style={{
                        fontSize: 12,
                        fontWeight: 600,
                        color: C.primary,
                        width: 36,
                        textAlign: 'right',
                    }}
                >
                    {keyResult.progress}%
                </span>
                <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    title="Perbarui nilai saat ini"
                    style={miniInputStyle}
                />
                <button
                    type="button"
                    title="Simpan progres"
                    onClick={saveProgress}
                    disabled={saving}
                    style={{
                        ...iconBtn,
                        opacity: saving ? 0.6 : 1,
                        cursor: saving ? 'not-allowed' : 'pointer',
                    }}
                >
                    <AIcon name="check" size={15} color={C.green} />
                </button>
                <button
                    type="button"
                    title="Hapus key result"
                    onClick={deleteKr}
                    style={iconBtn}
                >
                    <AIcon name="trash-2" size={15} color={C.red} />
                </button>
            </div>
        </div>
    );
}

/** A single objective card with its key results and an add-KR inline form. */
function ObjectiveCard({
    objective,
    onDelete,
}: {
    objective: ObjectiveRow;
    onDelete: (objective: ObjectiveRow) => void;
}) {
    const [adding, setAdding] = useState(false);
    const krForm = useForm<KeyResultFormData>({ ...emptyKeyResultForm });

    const submitKr = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        krForm.submit(OkrController.storeKeyResult(objective.id), {
            preserveScroll: true,
            onSuccess: () => {
                krForm.reset();
                setAdding(false);
            },
        });
    };

    return (
        <div style={{ ...card, padding: '20px 22px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-start',
                    justifyContent: 'space-between',
                    gap: 14,
                }}
            >
                <div style={{ flex: 1 }}>
                    <div
                        style={{
                            fontSize: 16,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 8,
                        }}
                    >
                        {objective.title}
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            flexWrap: 'wrap',
                        }}
                    >
                        <LevelBadge level={objective.level} />
                        <StatusBadge status={objective.status} />
                        {objective.employee && (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                    fontSize: 12,
                                    color: C.muted,
                                }}
                            >
                                <AIcon
                                    name="user"
                                    size={13}
                                    color={C.faint}
                                />
                                {objective.employee}
                            </span>
                        )}
                        {objective.cycle && (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                    fontSize: 12,
                                    color: C.muted,
                                }}
                            >
                                <AIcon
                                    name="calendar-clock"
                                    size={13}
                                    color={C.faint}
                                />
                                {objective.cycle}
                            </span>
                        )}
                    </div>
                </div>
                <div style={{ display: 'inline-flex', gap: 6 }}>
                    <Link
                        title="Ubah"
                        href={OkrController.edit(objective.id)}
                        style={iconBtn}
                    >
                        <AIcon name="pencil" size={15} color={C.muted} />
                    </Link>
                    <button
                        title="Hapus"
                        onClick={() => onDelete(objective)}
                        style={iconBtn}
                    >
                        <AIcon name="trash-2" size={15} color={C.red} />
                    </button>
                </div>
            </div>

            {objective.description && (
                <div
                    style={{
                        fontSize: 13,
                        color: C.muted,
                        marginTop: 12,
                        lineHeight: 1.55,
                    }}
                >
                    {objective.description}
                </div>
            )}

            {/* Objective progress rollup */}
            <div style={{ marginTop: 16 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 7,
                    }}
                >
                    <span
                        style={{
                            fontSize: 12,
                            fontWeight: 600,
                            color: C.faint,
                            letterSpacing: '.04em',
                            textTransform: 'uppercase',
                        }}
                    >
                        Progres
                    </span>
                    <span
                        style={{
                            fontSize: 13,
                            fontWeight: 700,
                            color: C.navy,
                        }}
                    >
                        {objective.progress}%
                    </span>
                </div>
                <ProgressBar value={objective.progress} />
            </div>

            {/* Key results */}
            <div style={{ marginTop: 16 }}>
                <div
                    style={{
                        fontSize: 12,
                        fontWeight: 600,
                        color: C.faint,
                        letterSpacing: '.04em',
                        textTransform: 'uppercase',
                    }}
                >
                    Key Results
                </div>
                {objective.key_results.length === 0 && (
                    <div
                        style={{
                            fontSize: 13,
                            color: C.muted,
                            padding: '12px 0 0',
                        }}
                    >
                        Belum ada key result.
                    </div>
                )}
                {objective.key_results.map((keyResult) => (
                    <KeyResultItem key={keyResult.id} keyResult={keyResult} />
                ))}
            </div>

            {/* Add key result */}
            {adding ? (
                <form
                    onSubmit={submitKr}
                    style={{
                        marginTop: 14,
                        padding: 14,
                        borderRadius: 10,
                        background: C.surface,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 12,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Judul Key Result{' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={krForm.data.title}
                            onChange={(event) =>
                                krForm.setData('title', event.target.value)
                            }
                            placeholder="Capai NPS 60"
                            style={withError(inputStyle, !!krForm.errors.title)}
                        />
                        <FieldError message={krForm.errors.title} />
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr 1fr',
                            gap: 12,
                        }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>
                                Target <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="number"
                                min={0}
                                step="0.01"
                                value={krForm.data.target_value}
                                onChange={(event) =>
                                    krForm.setData(
                                        'target_value',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    inputStyle,
                                    !!krForm.errors.target_value,
                                )}
                            />
                            <FieldError message={krForm.errors.target_value} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>
                                Saat Ini{' '}
                                <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="number"
                                min={0}
                                step="0.01"
                                value={krForm.data.current_value}
                                onChange={(event) =>
                                    krForm.setData(
                                        'current_value',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    inputStyle,
                                    !!krForm.errors.current_value,
                                )}
                            />
                            <FieldError message={krForm.errors.current_value} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Satuan</label>
                            <input
                                type="text"
                                value={krForm.data.unit}
                                onChange={(event) =>
                                    krForm.setData('unit', event.target.value)
                                }
                                placeholder="%, Rp, unit"
                                style={withError(
                                    inputStyle,
                                    !!krForm.errors.unit,
                                )}
                            />
                            <FieldError message={krForm.errors.unit} />
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button
                            type="button"
                            onClick={() => {
                                krForm.reset();
                                krForm.clearErrors();
                                setAdding(false);
                            }}
                            style={{
                                ...btnOut,
                                height: 38,
                                justifyContent: 'center',
                            }}
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={krForm.processing}
                            style={{
                                ...btnP,
                                height: 38,
                                justifyContent: 'center',
                                opacity: krForm.processing ? 0.7 : 1,
                                cursor: krForm.processing
                                    ? 'not-allowed'
                                    : 'pointer',
                            }}
                        >
                            <AIcon name="check" size={15} color="#fff" />
                            Simpan
                        </button>
                    </div>
                </form>
            ) : (
                <button
                    type="button"
                    onClick={() => setAdding(true)}
                    style={{
                        ...btnOut,
                        height: 38,
                        marginTop: 14,
                    }}
                >
                    <AIcon name="plus" size={15} color={C.text} />
                    Tambah Key Result
                </button>
            )}
        </div>
    );
}

export default function OkrIndex({ objectives, kpis }: OkrIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<ObjectiveRow | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const deleteObjective = () => {
        if (!confirm) {
            return;
        }

        router.delete(OkrController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const kpiItems = [
        {
            label: 'Total Objective',
            value: kpis.total_objectives,
            icon: 'target',
            color: C.primary,
        },
        {
            label: 'Rata-rata Progres',
            value: `${kpis.avg_progress}%`,
            icon: 'trending-up',
            color: C.green,
        },
        {
            label: 'On-Track',
            value: kpis.on_track,
            icon: 'circle-check',
            color: C.sky,
        },
    ];

    return (
        <>
            <Head title="OKR & Goal" />
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
                            <span style={{ color: C.muted }}>OKR &amp; Goal</span>
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
                            OKR &amp; Goal
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola objective &amp; key result perusahaan, tim,
                            dan individu.
                        </div>
                    </div>
                    <Link
                        href={OkrController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Buat Objective
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

                {/* Objectives */}
                {objectives.length === 0 ? (
                    <div
                        style={{
                            ...card,
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
                            <AIcon name="target" size={28} color={C.faint} />
                            <div>Belum ada objective.</div>
                        </div>
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        {objectives.map((objective) => (
                            <ObjectiveCard
                                key={objective.id}
                                objective={objective}
                                onDelete={setConfirm}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Confirm delete objective modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus objective?"
                    body={
                        <>
                            Objective{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.title}
                            </strong>{' '}
                            beserta seluruh key result-nya akan dihapus.
                            Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteObjective}
                />
            )}
        </>
    );
}
