import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnSave, C, card } from '@/lib/avana';
import type { FlashProps } from '../employees/types';

interface ShiftOption {
    id: number;
    code: string | null;
    name: string;
    start_time: string | null;
    end_time: string | null;
}

interface PatternStep {
    shift_id: number | null;
    shift_code?: string | null;
    shift_name?: string | null;
    days: number;
}

interface Pattern {
    id: number;
    code: string;
    name: string;
    industry: string | null;
    description: string | null;
    status: string;
    cycle_days: number;
    summary: string;
    steps: PatternStep[];
}

interface PageProps {
    patterns: Pattern[];
    shifts: ShiftOption[];
}

const control: CSSProperties = {
    width: '100%',
    padding: '9px 12px',
    borderRadius: 9,
    border: `1px solid ${C.border}`,
    background: '#fff',
    fontSize: 13,
    color: C.navy,
};

const label: CSSProperties = {
    display: 'block',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.muted,
    marginBottom: 5,
};

const EMPTY = {
    code: '',
    name: '',
    industry: '',
    description: '',
    status: 'active',
    steps: [{ shift_id: null as number | null, days: 1 }],
};

export default function RosterPatternIndex({ patterns, shifts }: PageProps) {
    const { flash } = usePage<FlashProps>().props;
    const [editing, setEditing] = useState<Pattern | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm<typeof EMPTY>({ ...EMPTY });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const startCreate = () => {
        setEditing(null);
        form.setDefaults({ ...EMPTY });
        form.reset();
        form.clearErrors();
        setOpen(true);
    };

    const startEdit = (pattern: Pattern) => {
        setEditing(pattern);
        form.setData({
            code: pattern.code,
            name: pattern.name,
            industry: pattern.industry ?? '',
            description: pattern.description ?? '',
            status: pattern.status,
            steps: pattern.steps.map((step) => ({
                shift_id: step.shift_id,
                days: step.days,
            })),
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit = () => {
        const done = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(`/avana/roster-pola/${editing.id}`, done);
        } else {
            form.post('/avana/roster-pola', done);
        }
    };

    const remove = (pattern: Pattern) => {
        router.delete(`/avana/roster-pola/${pattern.id}`, {
            preserveScroll: true,
        });
    };

    const setStep = (index: number, next: Partial<PatternStep>) => {
        form.setData(
            'steps',
            form.data.steps.map((step, i) =>
                i === index ? { ...step, ...next } : step,
            ),
        );
    };

    const addStep = () => {
        form.setData('steps', [
            ...form.data.steps,
            { shift_id: null, days: 1 },
        ]);
    };

    const removeStep = (index: number) => {
        if (form.data.steps.length === 1) {
            return;
        }

        form.setData(
            'steps',
            form.data.steps.filter((_, i) => i !== index),
        );
    };

    return (
        <>
            <Head title="Pola Roster" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-end',
                        flexWrap: 'wrap',
                        gap: 12,
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
                            <span style={{ color: C.muted }}>Pola Roster</span>
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
                            Pola Roster
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Siklus rotasi yang dipakai mengisi roster — misal 3
                            pagi, 3 siang, 3 malam, 2 libur.
                        </div>
                    </div>
                    <button onClick={startCreate} style={btnSave}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Pola
                    </button>
                </div>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {patterns.length === 0 ? (
                        <div
                            style={{
                                padding: 40,
                                textAlign: 'center',
                                color: C.faint,
                                fontSize: 13,
                            }}
                        >
                            Belum ada pola roster.
                        </div>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                }}
                            >
                                <thead>
                                    <tr style={{ background: C.surface }}>
                                        {[
                                            'Pola',
                                            'Industri',
                                            'Siklus',
                                            'Panjang',
                                            'Status',
                                            'Aksi',
                                        ].map((head) => (
                                            <th
                                                key={head}
                                                style={{
                                                    textAlign: 'left',
                                                    padding: '12px 16px',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.muted,
                                                    textTransform: 'uppercase',
                                                    letterSpacing: '.03em',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {head}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {patterns.map((pattern) => (
                                        <tr
                                            key={pattern.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{ padding: '12px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {pattern.name}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {pattern.code}
                                                </div>
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                }}
                                            >
                                                {pattern.industry ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 13,
                                                    color: C.navy,
                                                    fontWeight: 600,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {pattern.summary}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {pattern.cycle_days} hari
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    fontSize: 12.5,
                                                    color:
                                                        pattern.status ===
                                                        'active'
                                                            ? C.green
                                                            : C.faint,
                                                }}
                                            >
                                                {pattern.status === 'active'
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '12px 16px',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                <button
                                                    onClick={() =>
                                                        startEdit(pattern)
                                                    }
                                                    style={{
                                                        ...btnOut,
                                                        padding: '6px 12px',
                                                        marginRight: 8,
                                                    }}
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        remove(pattern)
                                                    }
                                                    style={{
                                                        ...btnOut,
                                                        padding: '6px 12px',
                                                        color: C.red,
                                                    }}
                                                >
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {open && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(15,23,42,.45)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                        zIndex: 60,
                    }}
                    onClick={() => setOpen(false)}
                >
                    <div
                        onClick={(event) => event.stopPropagation()}
                        style={{
                            ...card,
                            width: 'min(620px, 100%)',
                            maxHeight: '85vh',
                            overflowY: 'auto',
                            padding: 22,
                        }}
                    >
                        <h2
                            style={{
                                fontSize: 17,
                                fontWeight: 600,
                                color: C.navy,
                                margin: '0 0 16px',
                            }}
                        >
                            {editing ? 'Ubah Pola' : 'Tambah Pola'}
                        </h2>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 12,
                            }}
                        >
                            <div>
                                <label style={label}>Nama Pola *</label>
                                <input
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    style={control}
                                />
                                {form.errors.name && (
                                    <div
                                        style={{
                                            color: C.red,
                                            fontSize: 11.5,
                                            marginTop: 4,
                                        }}
                                    >
                                        {form.errors.name}
                                    </div>
                                )}
                            </div>
                            <div>
                                <label style={label}>Kode *</label>
                                <input
                                    value={form.data.code}
                                    onChange={(event) =>
                                        form.setData('code', event.target.value)
                                    }
                                    style={control}
                                />
                                {form.errors.code && (
                                    <div
                                        style={{
                                            color: C.red,
                                            fontSize: 11.5,
                                            marginTop: 4,
                                        }}
                                    >
                                        {form.errors.code}
                                    </div>
                                )}
                            </div>
                            <div>
                                <label style={label}>Industri</label>
                                <input
                                    value={form.data.industry}
                                    onChange={(event) =>
                                        form.setData(
                                            'industry',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Pabrik, Rumah Sakit, …"
                                    style={control}
                                />
                            </div>
                            <div>
                                <label style={label}>Status</label>
                                <select
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    style={{ ...control, cursor: 'pointer' }}
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                            </div>
                        </div>

                        <div style={{ marginTop: 18 }}>
                            <label style={label}>Siklus</label>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.faint,
                                    marginBottom: 9,
                                }}
                            >
                                Dibaca berurutan lalu diulang. Tahap tanpa shift
                                berarti libur.
                            </div>

                            {form.data.steps.map((step, index) => (
                                <div
                                    key={index}
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        marginBottom: 8,
                                        alignItems: 'center',
                                    }}
                                >
                                    <select
                                        value={step.shift_id ?? ''}
                                        onChange={(event) =>
                                            setStep(index, {
                                                shift_id:
                                                    event.target.value === ''
                                                        ? null
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                            })
                                        }
                                        style={{
                                            ...control,
                                            flex: 1,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <option value="">Libur</option>
                                        {shifts.map((shift) => (
                                            <option
                                                key={shift.id}
                                                value={shift.id}
                                            >
                                                {shift.name} (
                                                {shift.start_time}–
                                                {shift.end_time})
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        type="number"
                                        min={1}
                                        max={60}
                                        value={step.days}
                                        onChange={(event) =>
                                            setStep(index, {
                                                days: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                        style={{ ...control, width: 92 }}
                                    />
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: C.muted,
                                            width: 30,
                                        }}
                                    >
                                        hari
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => removeStep(index)}
                                        style={{
                                            ...btnOut,
                                            padding: '7px 10px',
                                            color: C.red,
                                        }}
                                    >
                                        <AIcon name="trash-2" size={14} />
                                    </button>
                                </div>
                            ))}

                            {form.errors.steps && (
                                <div style={{ color: C.red, fontSize: 11.5 }}>
                                    {form.errors.steps}
                                </div>
                            )}

                            <button
                                type="button"
                                onClick={addStep}
                                style={{ ...btnOut, marginTop: 4 }}
                            >
                                <AIcon name="plus" size={14} />
                                Tambah tahap
                            </button>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                style={{ ...btnOut, flex: 1 }}
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={submit}
                                disabled={form.processing}
                                style={{ ...btnSave, flex: 1 }}
                            >
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
