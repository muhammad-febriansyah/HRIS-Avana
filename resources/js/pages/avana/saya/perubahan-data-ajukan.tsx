import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnProcess, C } from '@/lib/avana';
import {
    Field,
    inputStyle,
    PageShell,
    Panel,
    textareaStyle,
    withError,
} from './components';

interface ChangeableField {
    key: string;
    label: string;
    group: string;
    current: string | null;
}

interface Props {
    fields: ChangeableField[];
}

/** The value the form starts each field at: whatever is stored today. */
const startingValues = (fields: ChangeableField[]): Record<string, string> =>
    Object.fromEntries(fields.map((field) => [field.key, field.current ?? '']));

export default function SayaPerubahanDataAjukan({ fields }: Props) {
    const form = useForm<{
        values: Record<string, string>;
        selected: string[];
        reason: string;
    }>({
        values: startingValues(fields),
        selected: [],
        reason: '',
    });

    const groups = fields.reduce<Record<string, ChangeableField[]>>(
        (carry, field) => {
            carry[field.group] = [...(carry[field.group] ?? []), field];

            return carry;
        },
        {},
    );

    const toggle = (key: string) =>
        form.setData(
            'selected',
            form.data.selected.includes(key)
                ? form.data.selected.filter((item) => item !== key)
                : [...form.data.selected, key],
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            reason: data.reason,
            changes: data.selected.map((key) => ({
                field: key,
                value: data.values[key] ?? '',
            })),
        }));

        form.post('/avana/saya/perubahan-data');
    };

    return (
        <>
            <Head title="Ajukan Perubahan Data" />
            <PageShell>
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
                    <span>Layanan Saya</span>
                    <AIcon name="chevron-right" size={13} />
                    <Link
                        href="/avana/saya/perubahan-data"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Perubahan Data Saya
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Ajukan</span>
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
                    Ajukan Perubahan Data
                </h1>
                <div
                    style={{
                        fontSize: 14,
                        color: C.muted,
                        marginTop: 4,
                        marginBottom: 22,
                    }}
                >
                    Centang data yang ingin diubah, lalu isi nilai barunya. Data
                    lama tetap berlaku sampai pengajuan disetujui.
                </div>

                <form onSubmit={submit}>
                    {Object.entries(groups).map(([group, groupFields]) => (
                        <div key={group} style={{ marginBottom: 16 }}>
                            <Panel title={group}>
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns:
                                            'repeat(auto-fit, minmax(280px, 1fr))',
                                        gap: 16,
                                    }}
                                >
                                    {groupFields.map((field) => {
                                        const checked =
                                            form.data.selected.includes(
                                                field.key,
                                            );

                                        return (
                                            <div key={field.key}>
                                                <label
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 8,
                                                        fontSize: 13,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                        cursor: 'pointer',
                                                        marginBottom: 6,
                                                    }}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={() =>
                                                            toggle(field.key)
                                                        }
                                                    />
                                                    {field.label}
                                                </label>

                                                <Field
                                                    label=""
                                                    hint={`Sekarang: ${field.current ?? '(kosong)'}`}
                                                >
                                                    <input
                                                        value={
                                                            form.data.values[
                                                                field.key
                                                            ] ?? ''
                                                        }
                                                        disabled={!checked}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'values',
                                                                {
                                                                    ...form.data
                                                                        .values,
                                                                    [field.key]:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                        placeholder="Nilai baru"
                                                        style={{
                                                            ...withError(
                                                                inputStyle,
                                                                false,
                                                            ),
                                                            background: checked
                                                                ? '#fff'
                                                                : C.surface,
                                                        }}
                                                    />
                                                </Field>
                                            </div>
                                        );
                                    })}
                                </div>
                            </Panel>
                        </div>
                    ))}

                    <Panel title="Alasan Perubahan">
                        <Field
                            label="Alasan"
                            // `changes` is only a validation key, not a form
                            // field, so its error surfaces here.
                            error={
                                form.errors.reason ??
                                (form.errors as Record<string, string>).changes
                            }
                            hint="mis. pindah kos, ganti nomor, rekening lama ditutup."
                        >
                            <textarea
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                placeholder="Jelaskan singkat kenapa data ini berubah"
                                style={withError(
                                    textareaStyle,
                                    !!form.errors.reason,
                                )}
                            />
                        </Field>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                                marginTop: 20,
                            }}
                        >
                            <Link
                                href="/avana/saya/perubahan-data"
                                style={{
                                    ...btnOut,
                                    height: 44,
                                    justifyContent: 'center',
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon name="x" size={16} color={C.text} />
                                Batal
                            </Link>
                            <button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.selected.length === 0
                                }
                                style={{
                                    ...btnProcess,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity:
                                        form.processing ||
                                        form.data.selected.length === 0
                                            ? 0.6
                                            : 1,
                                }}
                            >
                                <AIcon name="send" size={16} color="#fff" />
                                Kirim Pengajuan
                            </button>
                        </div>
                    </Panel>
                </form>
            </PageShell>
        </>
    );
}
