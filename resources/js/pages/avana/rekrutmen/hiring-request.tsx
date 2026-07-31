import { Head, router, useForm } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import HiringRequestController from '@/actions/App/Http/Controllers/Avana/HiringRequestController';
import { DatePicker } from '@/components/avana/date-picker';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, ActionBtn, btnOut, btnP, C, card } from '@/lib/avana';
import { Empty, Kpi, KpiRow, RecruitmentHeader, td, th } from './shell';

interface Dept {
    id: number;
    name: string;
}

interface PositionOption {
    id: number;
    name: string;
    department_id: number | null;
}

/** One manpower need on a request. A request may carry several. */
interface Need {
    id: number;
    position_id: number | null;
    position_title: string;
    department_id: number | null;
    department: string | null;
    vacancy: number;
    job_description: string | null;
    qualification: string | null;
    employment_type: string;
    target_join_date: string | null;
}

interface Req {
    id: number;
    request_number: string | null;
    requester: string | null;
    status: string;
    vacancy: number;
    requisitions_count: number;
    created_at: string | null;
    items: Need[];
}

interface Props {
    requests: Req[];
    departments: Dept[];
    positions: PositionOption[];
    employmentTypes: string[];
    canManage: boolean;
    kpis: { open: number; in_process: number; total: number };
}

const EMP_LABEL: Record<string, string> = {
    tetap: 'Tetap',
    kontrak: 'Kontrak',
    magang: 'Magang',
    harian: 'Harian',
};

const STATUS: Record<string, { c: string; bg: string; label: string }> = {
    open: { c: '#1D4ED8', bg: '#DBEAFE', label: 'Open' },
    in_process: { c: '#B45309', bg: '#FEF3C7', label: 'Diproses' },
    closed: { c: '#6B7280', bg: '#F1F5F9', label: 'Ditutup' },
};

const input: CSSProperties = {
    width: '100%',
    padding: '10px 12px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 14,
    outline: 'none',
    color: C.text,
    background: '#fff',
};
const label: CSSProperties = {
    fontSize: 13,
    fontWeight: 600,
    color: C.text,
    display: 'block',
    marginBottom: 6,
};

/** A need as the form holds it: ids arrive as strings from `<select>`. */
interface NeedDraft {
    id: number | null;
    position_id: string;
    position_title: string;
    department_id: string;
    vacancy: number;
    employment_type: string;
    target_join_date: string;
    job_description: string;
    qualification: string;
}

const emptyNeed = (): NeedDraft => ({
    id: null,
    position_id: '',
    position_title: '',
    department_id: '',
    vacancy: 1,
    employment_type: 'tetap',
    target_join_date: '',
    job_description: '',
    qualification: '',
});

export default function HiringRequestPage({
    requests,
    departments,
    positions,
    employmentTypes,
    kpis,
}: Props) {
    const { can } = usePermission();
    const [open, setOpen] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);

    const form = useForm<{ items: NeedDraft[] }>({ items: [emptyNeed()] });

    const openCreate = () => {
        setEditId(null);
        form.setData({ items: [emptyNeed()] });
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (r: Req) => {
        setEditId(r.id);
        form.setData({
            items: r.items.map((n) => ({
                id: n.id,
                position_id: n.position_id ? String(n.position_id) : '',
                position_title: n.position_title,
                department_id: n.department_id ? String(n.department_id) : '',
                vacancy: n.vacancy,
                employment_type: n.employment_type,
                target_join_date: n.target_join_date ?? '',
                job_description: n.job_description ?? '',
                qualification: n.qualification ?? '',
            })),
        });
        form.clearErrors();
        setOpen(true);
    };

    const patchNeed = (index: number, patch: Partial<NeedDraft>) =>
        form.setData(
            'items',
            form.data.items.map((n, i) =>
                i === index ? { ...n, ...patch } : n,
            ),
        );

    const addNeed = () =>
        form.setData('items', [...form.data.items, emptyNeed()]);

    const removeNeed = (index: number) =>
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );

    // Picking a master position fills the title, and the department it belongs
    // to — retyping what the master already knows is only a chance to disagree
    // with it.
    const pickPosition = (index: number, value: string) => {
        const match = positions.find((p) => String(p.id) === value);

        patchNeed(index, {
            position_id: value,
            position_title:
                match?.name ?? form.data.items[index].position_title,
            department_id: match?.department_id
                ? String(match.department_id)
                : form.data.items[index].department_id,
        });
    };

    const submit = () => {
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
            onError: () => toast.error('Periksa kembali isian'),
        };

        if (editId) {
            form.put(HiringRequestController.update(editId).url, opts);
        } else {
            form.post(HiringRequestController.store().url, opts);
        }
    };

    const close = (id: number) =>
        router.post(
            HiringRequestController.close(id).url,
            {},
            { preserveScroll: true },
        );

    const remove = (id: number) =>
        router.delete(HiringRequestController.destroy(id).url, {
            preserveScroll: true,
        });

    return (
        <>
            <Head title="Hiring Request" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Hiring Request"
                    subtitle="Pengajuan kebutuhan tenaga kerja oleh Hiring Manager (stage 1). Satu request boleh memuat beberapa kebutuhan."
                    action={
                        can('recruitment.create') ? (
                            <button style={btnP} onClick={openCreate}>
                                <AIcon name="plus" size={16} color="#fff" />
                                Buat Hiring Request
                            </button>
                        ) : null
                    }
                />

                <KpiRow>
                    <Kpi
                        label="Open"
                        value={kpis.open}
                        icon="file-plus"
                        color={C.primary}
                    />
                    <Kpi
                        label="Diproses"
                        value={kpis.in_process}
                        icon="loader"
                        color={C.amber}
                    />
                    <Kpi
                        label="Total"
                        value={kpis.total}
                        icon="files"
                        color={C.navy}
                    />
                </KpiRow>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {requests.length === 0 ? (
                        <Empty
                            icon="file-plus"
                            title="Belum ada hiring request"
                            hint="Ajukan kebutuhan tenaga kerja untuk memulai proses rekrutmen."
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 940,
                                }}
                            >
                                <thead>
                                    <tr>
                                        {[
                                            'No. Request',
                                            'Kebutuhan',
                                            'Total Vacancy',
                                            'Status',
                                            'Req.',
                                            'Aksi',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                style={{
                                                    ...th,
                                                    paddingTop: 14,
                                                }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.map((r) => {
                                        const st =
                                            STATUS[r.status] ?? STATUS.open;

                                        return (
                                            <tr key={r.id}>
                                                <td
                                                    style={{
                                                        ...td,
                                                        fontWeight: 600,
                                                        color: C.primary,
                                                        whiteSpace: 'nowrap',
                                                        verticalAlign: 'top',
                                                    }}
                                                >
                                                    {r.request_number ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        verticalAlign: 'top',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'grid',
                                                            gap: 6,
                                                        }}
                                                    >
                                                        {r.items.map((n) => (
                                                            <div key={n.id}>
                                                                <div
                                                                    style={{
                                                                        fontWeight: 600,
                                                                        color: C.navy,
                                                                    }}
                                                                >
                                                                    {
                                                                        n.position_title
                                                                    }{' '}
                                                                    <span
                                                                        style={{
                                                                            color: C.faint,
                                                                            fontWeight: 500,
                                                                        }}
                                                                    >
                                                                        ×
                                                                        {
                                                                            n.vacancy
                                                                        }
                                                                    </span>
                                                                </div>
                                                                <div
                                                                    style={{
                                                                        fontSize: 12,
                                                                        color: C.faint,
                                                                    }}
                                                                >
                                                                    {[
                                                                        n.department,
                                                                        EMP_LABEL[
                                                                            n
                                                                                .employment_type
                                                                        ] ??
                                                                            n.employment_type,
                                                                        n.target_join_date,
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            ' · ',
                                                                        )}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        verticalAlign: 'top',
                                                    }}
                                                >
                                                    {r.vacancy}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        verticalAlign: 'top',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            fontWeight: 700,
                                                            padding: '4px 10px',
                                                            borderRadius: 6,
                                                            color: st.c,
                                                            background: st.bg,
                                                        }}
                                                    >
                                                        {st.label}
                                                    </span>
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        verticalAlign: 'top',
                                                    }}
                                                >
                                                    {r.requisitions_count}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        verticalAlign: 'top',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            gap: 6,
                                                            flexWrap: 'wrap',
                                                        }}
                                                    >
                                                        {can(
                                                            'recruitment.update',
                                                        ) &&
                                                            r.status !==
                                                                'closed' && (
                                                                <ActionBtn
                                                                    icon="pencil"
                                                                    label="Edit"
                                                                    variant="primary"
                                                                    onClick={() =>
                                                                        openEdit(
                                                                            r,
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                        {can(
                                                            'recruitment.update',
                                                        ) &&
                                                            r.status !==
                                                                'closed' && (
                                                                <ActionBtn
                                                                    icon="lock"
                                                                    label="Tutup"
                                                                    variant="warning"
                                                                    onClick={() =>
                                                                        close(
                                                                            r.id,
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                        {can(
                                                            'recruitment.archive',
                                                        ) && (
                                                            <ActionBtn
                                                                icon="trash-2"
                                                                label="Hapus"
                                                                variant="danger"
                                                                onClick={() =>
                                                                    remove(r.id)
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {open && (
                <Modal
                    title={
                        editId ? 'Ubah Hiring Request' : 'Buat Hiring Request'
                    }
                    onClose={() => setOpen(false)}
                    onSubmit={submit}
                    processing={form.processing}
                    width={640}
                >
                    {form.errors.items && (
                        <div
                            style={{
                                fontSize: 13,
                                color: C.red,
                                marginBottom: 12,
                            }}
                        >
                            {form.errors.items}
                        </div>
                    )}

                    {form.data.items.map((need, index) => (
                        <div
                            key={index}
                            style={{
                                border: `1px solid ${C.line}`,
                                borderRadius: 12,
                                padding: 16,
                                marginBottom: 12,
                                background: '#FBFDFF',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: 12,
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 700,
                                        color: C.navy,
                                    }}
                                >
                                    Kebutuhan {index + 1}
                                </div>
                                {form.data.items.length > 1 && (
                                    <ActionBtn
                                        icon="trash-2"
                                        label="Hapus"
                                        variant="danger"
                                        onClick={() => removeNeed(index)}
                                    />
                                )}
                            </div>

                            <Field
                                label="Posisi (master)"
                                error={
                                    form.errors[
                                        `items.${index}.position_title` as keyof typeof form.errors
                                    ] as string | undefined
                                }
                            >
                                <select
                                    style={input}
                                    value={need.position_id}
                                    onChange={(e) =>
                                        pickPosition(index, e.target.value)
                                    }
                                >
                                    <option value="">
                                        — pilih dari master, atau ketik manual —
                                    </option>
                                    {positions.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Nama Posisi">
                                <input
                                    style={input}
                                    placeholder="cth. Software Engineer"
                                    value={need.position_title}
                                    onChange={(e) =>
                                        patchNeed(index, {
                                            position_title: e.target.value,
                                        })
                                    }
                                />
                            </Field>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '2fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <Field label="Departemen">
                                    <select
                                        style={input}
                                        value={need.department_id}
                                        onChange={(e) =>
                                            patchNeed(index, {
                                                department_id: e.target.value,
                                            })
                                        }
                                    >
                                        <option value="">—</option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Vacancy"
                                    error={
                                        form.errors[
                                            `items.${index}.vacancy` as keyof typeof form.errors
                                        ] as string | undefined
                                    }
                                >
                                    <input
                                        type="number"
                                        min={1}
                                        style={input}
                                        placeholder="cth. 2"
                                        value={need.vacancy}
                                        onChange={(e) =>
                                            patchNeed(index, {
                                                vacancy: Number(e.target.value),
                                            })
                                        }
                                    />
                                </Field>
                            </div>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 12,
                                }}
                            >
                                <Field label="Tipe Kerja">
                                    <select
                                        style={input}
                                        value={need.employment_type}
                                        onChange={(e) =>
                                            patchNeed(index, {
                                                employment_type: e.target.value,
                                            })
                                        }
                                    >
                                        {employmentTypes.map((t) => (
                                            <option key={t} value={t}>
                                                {EMP_LABEL[t] ?? t}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Target Join">
                                    <DatePicker
                                        value={need.target_join_date}
                                        onChange={(nextValue) =>
                                            patchNeed(index, {
                                                target_join_date: nextValue,
                                            })
                                        }
                                        placeholder="Pilih tanggal"
                                        width="100%"
                                    />
                                </Field>
                            </div>

                            <Field label="Deskripsi Pekerjaan">
                                <textarea
                                    style={{
                                        ...input,
                                        minHeight: 64,
                                        resize: 'vertical',
                                    }}
                                    placeholder="cth. Membangun dan memelihara aplikasi internal perusahaan"
                                    value={need.job_description}
                                    onChange={(e) =>
                                        patchNeed(index, {
                                            job_description: e.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Kualifikasi">
                                <textarea
                                    style={{
                                        ...input,
                                        minHeight: 64,
                                        resize: 'vertical',
                                    }}
                                    placeholder="cth. S1 Teknik Informatika, pengalaman 2 tahun"
                                    value={need.qualification}
                                    onChange={(e) =>
                                        patchNeed(index, {
                                            qualification: e.target.value,
                                        })
                                    }
                                />
                            </Field>
                        </div>
                    ))}

                    <button
                        style={{ ...btnOut, width: '100%', marginBottom: 8 }}
                        onClick={addNeed}
                    >
                        <AIcon name="plus" size={15} color={C.text} />
                        Tambah Kebutuhan
                    </button>
                </Modal>
            )}
        </>
    );
}

export function Field({
    label: lbl,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div style={{ marginBottom: 14 }}>
            <label style={label}>{lbl}</label>
            {children}
            {error && (
                <div style={{ fontSize: 12, color: C.red, marginTop: 4 }}>
                    {error}
                </div>
            )}
        </div>
    );
}

export function Modal({
    title,
    onClose,
    onSubmit,
    processing,
    submitLabel = 'Simpan',
    submitIcon = 'save',
    width = 540,
    children,
}: {
    title: string;
    onClose: () => void;
    onSubmit: () => void;
    processing?: boolean;
    submitLabel?: string;
    submitIcon?: string;
    width?: number;
    children: React.ReactNode;
}) {
    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(15,23,42,.45)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 50,
                padding: 20,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    ...card,
                    width,
                    maxWidth: '100%',
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    padding: 26,
                }}
            >
                <div
                    style={{
                        fontSize: 18,
                        fontWeight: 700,
                        color: C.navy,
                        marginBottom: 18,
                    }}
                >
                    {title}
                </div>
                {children}
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                        marginTop: 8,
                    }}
                >
                    <button style={btnOut} onClick={onClose}>
                        <AIcon name="x" size={15} color={C.text} />
                        Batal
                    </button>
                    <button
                        style={btnP}
                        disabled={processing}
                        onClick={onSubmit}
                    >
                        <AIcon name={submitIcon} size={15} color="#fff" />
                        {submitLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
