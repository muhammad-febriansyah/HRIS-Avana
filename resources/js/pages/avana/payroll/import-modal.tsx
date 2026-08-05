import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import PayrollImportController from '@/actions/App/Http/Controllers/Avana/PayrollImportController';
import { AIcon, btnOut, btnProcess, C, card } from '@/lib/avana';
import type { Period } from './types';

/**
 * Upload a payroll computed outside the system. The tenant picks the period,
 * downloads a template already listing that period's employees, fills in the
 * amounts and uploads it — replacing whatever the period held.
 */
export function ImportModal({
    periods,
    onClose,
}: {
    periods: Period[];
    onClose: () => void;
}) {
    // A locked period cannot be rewritten, so it is not offered.
    const options = periods.filter((period) => period.status !== 'locked');

    const [periodId, setPeriodId] = useState<string>(
        options[0] ? String(options[0].id) : '',
    );
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);

    const label: React.CSSProperties = {
        display: 'block',
        fontSize: 12.5,
        fontWeight: 600,
        color: C.muted,
        marginBottom: 6,
    };

    const input: React.CSSProperties = {
        width: '100%',
        padding: '10px 12px',
        borderRadius: 8,
        border: `1px solid ${C.line}`,
        fontSize: 14,
        outline: 'none',
        color: C.text,
        background: '#fff',
    };

    const submit = () => {
        if (!periodId || !file) {
            return;
        }

        setProcessing(true);

        router.post(
            PayrollImportController.store().url,
            { payroll_period_id: periodId, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: onClose,
                onError: (errors) =>
                    toast.error(errors.file ?? 'Berkas tidak bisa diproses'),
                onFinish: () => setProcessing(false),
            },
        );
    };

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
                zIndex: 60,
                padding: 20,
            }}
        >
            <div
                onClick={(event) => event.stopPropagation()}
                style={{
                    ...card,
                    width: 520,
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
                        marginBottom: 2,
                    }}
                >
                    Impor Payroll
                </div>
                <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>
                    Untuk perusahaan yang menghitung gaji di luar sistem. Angka
                    dari berkas dipakai apa adanya — mesin perhitungan tidak
                    dijalankan.
                </div>

                <div style={{ marginBottom: 14 }}>
                    <label style={label}>Periode</label>
                    <select
                        style={input}
                        value={periodId}
                        onChange={(event) => setPeriodId(event.target.value)}
                    >
                        {options.length === 0 && (
                            <option value="">
                                Tidak ada periode yang bisa diisi
                            </option>
                        )}
                        {options.map((period) => (
                            <option key={period.id} value={period.id}>
                                {period.periode} — {period.status_label}
                            </option>
                        ))}
                    </select>
                </div>

                <div style={{ marginBottom: 14 }}>
                    <label style={label}>Berkas (.xlsx / .csv)</label>
                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        onChange={(event) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
                        style={{ ...input, padding: '8px 10px' }}
                    />
                    <a
                        href={
                            periodId
                                ? `${PayrollImportController.template().url}?payroll_period_id=${periodId}`
                                : PayrollImportController.template().url
                        }
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            fontSize: 12.5,
                            color: C.primary,
                            textDecoration: 'none',
                            marginTop: 8,
                        }}
                    >
                        <AIcon name="download" size={14} color={C.primary} />
                        Unduh template (sudah berisi daftar karyawan)
                    </a>
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 10,
                        alignItems: 'flex-start',
                        background: '#FFF8E6',
                        border: '1px solid #F5E2B3',
                        borderRadius: 10,
                        padding: '12px 14px',
                        marginBottom: 20,
                    }}
                >
                    <AIcon name="octagon-alert" size={17} color="#B45309" />
                    <div style={{ fontSize: 12.5, color: '#92400E' }}>
                        Data payroll periode ini akan diganti seluruhnya dengan
                        isi berkas, dan persetujuan sebelumnya direset. Satu
                        baris bermasalah membatalkan seluruh impor.
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                    }}
                >
                    <button style={btnOut} onClick={onClose}>
                        <AIcon name="x" size={15} color={C.text} />
                        Batal
                    </button>
                    <button
                        style={{
                            ...btnProcess,
                            opacity: processing || !file || !periodId ? 0.6 : 1,
                        }}
                        disabled={processing || !file || !periodId}
                        onClick={submit}
                    >
                        <AIcon name="upload" size={15} color="#fff" />
                        {processing ? 'Mengunggah…' : 'Unggah'}
                    </button>
                </div>
            </div>
        </div>
    );
}
