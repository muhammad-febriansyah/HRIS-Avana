import { Head, router, useForm } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import RecruitmentRequisitionController from '@/actions/App/Http/Controllers/Avana/RecruitmentRequisitionController';
import { DatePicker } from '@/components/avana/date-picker';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, ActionBtn, btnP, C, card } from '@/lib/avana';
import { Field, Modal } from './hiring-request';
import { Empty, Kpi, KpiRow, RecruitmentHeader, td, th } from './shell';

interface Dept {
    id: number;
    name: string;
}

interface HiringOpt {
    id: number;
    request_number: string | null;
    position_title: string;
    department_id: number | null;
    vacancy: number;
    qualification: string | null;
    job_description: string | null;
    employment_type: string;
}

interface Requisition {
    id: number;
    requisition_number: string | null;
    hiring_request_number: string | null;
    position_title: string;
    department_id: number | null;
    department: string | null;
    vacancy: number;
    qualification: string | null;
    job_description: string | null;
    employment_type: string;
    location: string | null;
    status: string;
    publish_date: string | null;
    closing_date: string | null;
    job_posting_id: number | null;
    job_title: string | null;
}

interface Props {
    requisitions: Requisition[];
    hiringRequests: HiringOpt[];
    departments: Dept[];
    employmentTypes: string[];
    kpis: { draft: number; published: number; total: number };
}

const EMP_LABEL: Record<string, string> = {
    tetap: 'Tetap',
    kontrak: 'Kontrak',
    magang: 'Magang',
    harian: 'Harian',
};
const STATUS: Record<string, { c: string; bg: string; label: string }> = {
    draft: { c: '#B45309', bg: '#FEF3C7', label: 'Draft' },
    published: { c: '#15803D', bg: '#DCFCE7', label: 'Published' },
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

const emptyForm = {
    hiring_request_id: '',
    position_title: '',
    department_id: '',
    vacancy: 1,
    qualification: '',
    job_description: '',
    employment_type: 'tetap',
    location: '',
};

export default function RequisitionPage({
    requisitions,
    hiringRequests,
    departments,
    employmentTypes,
    kpis,
}: Props) {
    const { can } = usePermission();
    const [open, setOpen] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);
    const [publishId, setPublishId] = useState<number | null>(null);

    const form = useForm<typeof emptyForm>({ ...emptyForm });
    const pubForm = useForm({ publish_date: '', closing_date: '' });

    const openCreate = () => {
        setEditId(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (r: Requisition) => {
        setEditId(r.id);
        form.setData({
            hiring_request_id: '',
            position_title: r.position_title,
            department_id: r.department_id ? String(r.department_id) : '',
            vacancy: r.vacancy,
            qualification: r.qualification ?? '',
            job_description: r.job_description ?? '',
            employment_type: r.employment_type,
            location: r.location ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const pickHiring = (id: string) => {
        const h = hiringRequests.find((x) => String(x.id) === id);
        form.setData({
            ...form.data,
            hiring_request_id: id,
            ...(h
                ? {
                      position_title: h.position_title,
                      department_id: h.department_id
                          ? String(h.department_id)
                          : '',
                      vacancy: h.vacancy,
                      qualification: h.qualification ?? '',
                      job_description: h.job_description ?? '',
                      employment_type: h.employment_type,
                  }
                : {}),
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
            form.put(RecruitmentRequisitionController.update(editId).url, opts);
        } else {
            form.post(RecruitmentRequisitionController.store().url, opts);
        }
    };

    const publish = () => {
        if (!publishId) {
            return;
        }

        pubForm.post(RecruitmentRequisitionController.publish(publishId).url, {
            preserveScroll: true,
            onSuccess: () => {
                setPublishId(null);
                pubForm.reset();
            },
            onError: () => toast.error('Periksa tanggal publish'),
        });
    };

    const remove = (id: number) =>
        router.delete(RecruitmentRequisitionController.destroy(id).url, {
            preserveScroll: true,
        });

    return (
        <>
            <Head title="Recruitment Requisition" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Recruitment Requisition"
                    subtitle="HR menyusun requisition dari Hiring Request, lalu publikasikan lowongan (stage 2–3)."
                    action={
                        can('recruitment.create') &&
                        hiringRequests.length > 0 ? (
                            <button style={btnP} onClick={openCreate}>
                                <AIcon name="plus" size={16} color="#fff" />
                                Buat Requisition
                            </button>
                        ) : null
                    }
                />

                <KpiRow>
                    <Kpi
                        label="Draft"
                        value={kpis.draft}
                        icon="clipboard-list"
                        color={C.amber}
                    />
                    <Kpi
                        label="Published"
                        value={kpis.published}
                        icon="megaphone"
                        color={C.green}
                    />
                    <Kpi
                        label="Total"
                        value={kpis.total}
                        icon="files"
                        color={C.navy}
                    />
                </KpiRow>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {requisitions.length === 0 ? (
                        <Empty
                            icon="clipboard-list"
                            title="Belum ada requisition"
                            hint={
                                hiringRequests.length === 0
                                    ? 'Buat Hiring Request terlebih dahulu.'
                                    : 'Susun requisition dari hiring request yang masuk.'
                            }
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 1000,
                                }}
                            >
                                <thead>
                                    <tr>
                                        {[
                                            'No. Requisition',
                                            'Hiring Req',
                                            'Posisi',
                                            'Vacancy',
                                            'Tipe',
                                            'Lokasi',
                                            'Status',
                                            'Lowongan',
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
                                    {requisitions.map((r) => {
                                        const st =
                                            STATUS[r.status] ?? STATUS.draft;

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
                                                    {r.requisition_number ??
                                                        '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {r.hiring_request_number ??
                                                        '—'}
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
                                                <td style={td}>{r.vacancy}</td>
                                                <td style={td}>
                                                    {EMP_LABEL[
                                                        r.employment_type
                                                    ] ?? r.employment_type}
                                                </td>
                                                <td style={td}>
                                                    {r.location ?? '—'}
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
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {r.job_title ?? '—'}
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
                                                            r.status ===
                                                                'draft' && (
                                                                <ActionBtn
                                                                    icon="megaphone"
                                                                    label="Publish"
                                                                    variant="success"
                                                                    onClick={() =>
                                                                        setPublishId(
                                                                            r.id,
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                        {can(
                                                            'recruitment.update',
                                                        ) &&
                                                            r.status ===
                                                                'draft' && (
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
                                                            'recruitment.archive',
                                                        ) &&
                                                            r.status !==
                                                                'published' && (
                                                                <ActionBtn
                                                                    icon="trash-2"
                                                                    label="Hapus"
                                                                    variant="danger"
                                                                    onClick={() =>
                                                                        remove(
                                                                            r.id,
                                                                        )
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
                    title={editId ? 'Ubah Requisition' : 'Buat Requisition'}
                    onClose={() => setOpen(false)}
                    onSubmit={submit}
                    processing={form.processing}
                >
                    {!editId && (
                        <Field
                            label="Dari Hiring Request"
                            error={form.errors.hiring_request_id}
                        >
                            <select
                                style={input}
                                value={form.data.hiring_request_id}
                                onChange={(e) => pickHiring(e.target.value)}
                            >
                                <option value="">Pilih hiring request…</option>
                                {hiringRequests.map((h) => (
                                    <option key={h.id} value={h.id}>
                                        {h.request_number} — {h.position_title}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
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
                        <Field label="Lokasi">
                            <input
                                style={input}
                                value={form.data.location}
                                onChange={(e) =>
                                    form.setData('location', e.target.value)
                                }
                            />
                        </Field>
                    </div>
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
                </Modal>
            )}

            {publishId && (
                <Modal
                    title="Publikasikan Lowongan"
                    onClose={() => setPublishId(null)}
                    onSubmit={publish}
                    processing={pubForm.processing}
                    submitLabel="Publish"
                >
                    <div
                        style={{
                            fontSize: 13,
                            color: C.muted,
                            marginBottom: 14,
                        }}
                    >
                        Requisition akan menjadi lowongan aktif yang dapat
                        dilamar kandidat.
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 12,
                        }}
                    >
                        <Field
                            label="Tanggal Publish"
                            error={pubForm.errors.publish_date}
                        >
                            <DatePicker
                                value={pubForm.data.publish_date}
                                onChange={(nextValue) =>
                                    pubForm.setData('publish_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                        </Field>
                        <Field
                            label="Closing Date"
                            error={pubForm.errors.closing_date}
                        >
                            <DatePicker
                                value={pubForm.data.closing_date}
                                onChange={(nextValue) =>
                                    pubForm.setData('closing_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                        </Field>
                    </div>
                </Modal>
            )}
        </>
    );
}
