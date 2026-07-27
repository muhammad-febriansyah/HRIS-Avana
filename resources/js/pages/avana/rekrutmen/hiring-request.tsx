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

interface Req {
    id: number;
    request_number: string | null;
    requester: string | null;
    position_title: string;
    department_id: number | null;
    department: string | null;
    vacancy: number;
    job_description: string | null;
    qualification: string | null;
    employment_type: string;
    target_join_date: string | null;
    status: string;
    requisitions_count: number;
    created_at: string | null;
}

interface Props {
    requests: Req[];
    departments: Dept[];
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

const emptyForm = {
    position_title: '',
    department_id: '',
    vacancy: 1,
    employment_type: 'tetap',
    target_join_date: '',
    job_description: '',
    qualification: '',
};

export default function HiringRequestPage({
    requests,
    departments,
    employmentTypes,
    kpis,
}: Props) {
    const { can } = usePermission();
    const [open, setOpen] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);

    const form = useForm<typeof emptyForm>({ ...emptyForm });

    const openCreate = () => {
        setEditId(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (r: Req) => {
        setEditId(r.id);
        form.setData({
            position_title: r.position_title,
            department_id: r.department_id ? String(r.department_id) : '',
            vacancy: r.vacancy,
            employment_type: r.employment_type,
            target_join_date: r.target_join_date ?? '',
            job_description: r.job_description ?? '',
            qualification: r.qualification ?? '',
        });
        form.clearErrors();
        setOpen(true);
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
                    subtitle="Pengajuan kebutuhan tenaga kerja oleh Hiring Manager (stage 1)."
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
                                            'Posisi',
                                            'Departemen',
                                            'Vacancy',
                                            'Tipe',
                                            'Target Join',
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
                                                    }}
                                                >
                                                    {r.request_number ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {r.position_title}
                                                </td>
                                                <td style={td}>
                                                    {r.department ?? '—'}
                                                </td>
                                                <td style={td}>{r.vacancy}</td>
                                                <td style={td}>
                                                    {EMP_LABEL[
                                                        r.employment_type
                                                    ] ?? r.employment_type}
                                                </td>
                                                <td style={td}>
                                                    {r.target_join_date ?? '—'}
                                                </td>
                                                <td style={td}>
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
                                                <td style={td}>
                                                    {r.requisitions_count}
                                                </td>
                                                <td style={td}>
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
                >
                    <Field label="Posisi" error={form.errors.position_title}>
                        <input
                            style={input}
                            value={form.data.position_title}
                            onChange={(e) =>
                                form.setData('position_title', e.target.value)
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
                                value={form.data.department_id}
                                onChange={(e) =>
                                    form.setData(
                                        'department_id',
                                        e.target.value,
                                    )
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
                        <Field label="Vacancy" error={form.errors.vacancy}>
                            <input
                                type="number"
                                min={1}
                                style={input}
                                value={form.data.vacancy}
                                onChange={(e) =>
                                    form.setData(
                                        'vacancy',
                                        Number(e.target.value),
                                    )
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
                                value={form.data.employment_type}
                                onChange={(e) =>
                                    form.setData(
                                        'employment_type',
                                        e.target.value,
                                    )
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
                                value={form.data.target_join_date}
                                onChange={(nextValue) =>
                                    form.setData('target_join_date', nextValue)
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
                            value={form.data.job_description}
                            onChange={(e) =>
                                form.setData('job_description', e.target.value)
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
                            value={form.data.qualification}
                            onChange={(e) =>
                                form.setData('qualification', e.target.value)
                            }
                        />
                    </Field>
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
    children,
}: {
    title: string;
    onClose: () => void;
    onSubmit: () => void;
    processing?: boolean;
    submitLabel?: string;
    submitIcon?: string;
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
                    width: 540,
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
