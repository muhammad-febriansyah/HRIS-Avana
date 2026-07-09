import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import type { FlashProps } from './types';

interface PeriodOption {
    id: number;
    name: string;
    code: string;
    cycle_label: string;
    range: string;
}

interface AbsensiProps {
    periods: PeriodOption[];
}

const fieldLabel = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
} as const;
const fieldInput = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
} as const;

function FieldErr({ msg }: { msg?: string }) {
    if (!msg) {
        return null;
    }

    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{msg}</div>
    );
}

export default function AttendanceUpload({ periods }: AbsensiProps) {
    const { flash } = usePage<FlashProps>().props;
    const [fileName, setFileName] = useState('');

    const form = useForm<{ period_id: string; file: File | null }>({
        period_id: periods[0] ? String(periods[0].id) : '',
        file: null,
    });
    const { data, setData, errors, processing } = form;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(PayrollController.importAttendance().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('file');
                setFileName('');
            },
        });
    };

    return (
        <>
            <Head title="Upload Absensi" />
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
                        href={PayrollController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Payroll
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Upload Absensi</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 6px',
                        letterSpacing: '-.01em',
                    }}
                >
                    Upload Absensi
                </h1>
                <div
                    style={{
                        fontSize: 14,
                        color: C.muted,
                        margin: '0 0 24px',
                    }}
                >
                    Impor jumlah hari hadir &amp; jam lembur per karyawan untuk
                    satu periode (mingguan / dua-mingguan / bulanan).
                </div>

                <form onSubmit={submit} style={{ ...card }}>
                    <div style={{ padding: '22px 24px' }}>
                        <div style={{ marginBottom: 16 }}>
                            <label style={fieldLabel}>Periode</label>
                            <select
                                value={data.period_id}
                                onChange={(e) =>
                                    setData('period_id', e.target.value)
                                }
                                style={{ ...fieldInput, cursor: 'pointer' }}
                            >
                                {periods.length === 0 && (
                                    <option value="">
                                        Belum ada periode — buat periode dulu
                                    </option>
                                )}
                                {periods.map((p) => (
                                    <option key={p.id} value={String(p.id)}>
                                        {p.name} · {p.cycle_label} · {p.range}
                                    </option>
                                ))}
                            </select>
                            <FieldErr msg={errors.period_id} />
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 12,
                                padding: '12px 14px',
                                border: `1px dashed ${C.border}`,
                                borderRadius: 8,
                                background: '#F8FAFC',
                                marginBottom: 16,
                            }}
                        >
                            <div style={{ fontSize: 13, color: C.muted }}>
                                Unduh template CSV, isi kolom{' '}
                                <b>hari_hadir</b> &amp; <b>jam_lembur</b>, lalu
                                unggah kembali.
                            </div>
                            <a
                                href={PayrollController.attendanceTemplate().url}
                                style={{
                                    ...btnOut,
                                    flex: 'none',
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon name="download" size={16} />
                                Template
                            </a>
                        </div>

                        <div>
                            <label style={fieldLabel}>Berkas CSV</label>
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;
                                    setData('file', file);
                                    setFileName(file?.name ?? '');
                                }}
                                style={{
                                    ...fieldInput,
                                    height: 'auto',
                                    padding: '10px 13px',
                                }}
                            />
                            {fileName && (
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: C.muted,
                                        marginTop: 6,
                                    }}
                                >
                                    {fileName}
                                </div>
                            )}
                            <FieldErr msg={errors.file} />
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
                            href={PayrollController.index().url}
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
                            disabled={processing || !data.file || !data.period_id}
                            style={{
                                ...btnP,
                                height: 44,
                                justifyContent: 'center',
                                opacity:
                                    processing || !data.file || !data.period_id
                                        ? 0.6
                                        : 1,
                                cursor:
                                    processing || !data.file || !data.period_id
                                        ? 'not-allowed'
                                        : 'pointer',
                            }}
                        >
                            <AIcon name="upload-cloud" size={16} color="#fff" />
                            Impor Absensi
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
