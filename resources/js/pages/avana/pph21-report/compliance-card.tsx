import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, C, btnCreate, btnSave } from '@/lib/avana';
import { Panel, btnCancel, stateColor } from './components';
import type { Compliance } from './types';

/**
 * The five-step compliance checklist for the selected tax period, plus the
 * panel where the two steps payroll cannot know — deposited, reported — are
 * typed in with their DJP receipt numbers.
 */
export function ComplianceCard({
    compliance,
    canUpdate,
}: {
    compliance: Compliance;
    canUpdate: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const record = compliance.record;

    const form = useForm({
        payroll_period_id: compliance.period_id ?? 0,
        deposit_status: record.deposit_status,
        deposit_date: record.deposit_date ?? '',
        deposit_ntpn: record.deposit_ntpn ?? '',
        report_status: record.report_status,
        report_date: record.report_date ?? '',
        report_ntte: record.report_ntte ?? '',
        note: record.note ?? '',
    });

    const submit = () => {
        form.post('/avana/payroll/pph21-report/kepatuhan', {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const overallColor = stateColor(compliance.overall);

    return (
        <Panel
            title="Status Kepatuhan Masa Pajak"
            right={
                <span
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                        padding: '4px 11px',
                        borderRadius: 100,
                        fontSize: 11.5,
                        fontWeight: 600,
                        color: overallColor,
                        background: `${overallColor}18`,
                    }}
                >
                    {compliance.done} / {compliance.total} langkah
                </span>
            }
        >
            <div style={{ padding: '16px 20px' }}>
                {compliance.steps.map((step) => (
                    <div
                        key={step.key}
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            justifyContent: 'space-between',
                            gap: 12,
                            padding: '9px 0',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-start',
                                gap: 10,
                            }}
                        >
                            <span
                                style={{
                                    width: 9,
                                    height: 9,
                                    borderRadius: '50%',
                                    background: stateColor(step.state),
                                    marginTop: 5,
                                    flexShrink: 0,
                                }}
                            />
                            <div>
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: C.text,
                                        fontWeight: 500,
                                    }}
                                >
                                    {step.label}
                                </div>
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: C.faint,
                                        marginTop: 2,
                                    }}
                                >
                                    {step.detail}
                                </div>
                            </div>
                        </div>
                        <span
                            style={{
                                fontSize: 11.5,
                                fontWeight: 600,
                                color: stateColor(step.state),
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {step.state === 'done'
                                ? 'Selesai'
                                : step.state === 'warn'
                                  ? 'Perlu tindakan'
                                  : 'Belum'}
                        </span>
                    </div>
                ))}

                {canUpdate && compliance.period_id !== null && !editing && (
                    <button
                        type="button"
                        data-testid="edit-kepatuhan"
                        style={{ ...btnCreate, marginTop: 14 }}
                        onClick={() => setEditing(true)}
                    >
                        <AIcon name="pencil" size={14} color="#fff" />
                        Catat Setoran / Pelaporan
                    </button>
                )}

                {editing && (
                    <div
                        style={{
                            marginTop: 16,
                            paddingTop: 16,
                            borderTop: `1px solid ${C.line}`,
                        }}
                    >
                        <Field label="Status Penyetoran">
                            <select
                                data-testid="deposit-status"
                                value={form.data.deposit_status}
                                onChange={(e) =>
                                    form.setData(
                                        'deposit_status',
                                        e.target.value as 'pending' | 'done',
                                    )
                                }
                                style={input}
                            >
                                <option value="pending">Belum disetor</option>
                                <option value="done">Sudah disetor</option>
                            </select>
                        </Field>
                        <div style={row}>
                            <Field label="Tanggal Setor">
                                <DatePicker
                                    value={form.data.deposit_date}
                                    onChange={(v) =>
                                        form.setData('deposit_date', v)
                                    }
                                    width="100%"
                                    placeholder="Pilih tanggal"
                                />
                            </Field>
                            <Field label="NTPN">
                                <input
                                    data-testid="deposit-ntpn"
                                    value={form.data.deposit_ntpn}
                                    onChange={(e) =>
                                        form.setData(
                                            'deposit_ntpn',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Nomor Transaksi Penerimaan Negara"
                                    style={input}
                                />
                            </Field>
                        </div>

                        <Field label="Status Pelaporan">
                            <select
                                data-testid="report-status"
                                value={form.data.report_status}
                                onChange={(e) =>
                                    form.setData(
                                        'report_status',
                                        e.target.value as 'pending' | 'done',
                                    )
                                }
                                style={input}
                            >
                                <option value="pending">
                                    Belum dilaporkan
                                </option>
                                <option value="done">Sudah dilaporkan</option>
                            </select>
                        </Field>
                        {form.errors.report_status && (
                            <div style={errorText}>
                                {form.errors.report_status}
                            </div>
                        )}
                        <div style={row}>
                            <Field label="Tanggal Lapor">
                                <DatePicker
                                    value={form.data.report_date}
                                    onChange={(v) =>
                                        form.setData('report_date', v)
                                    }
                                    width="100%"
                                    placeholder="Pilih tanggal"
                                />
                            </Field>
                            <Field label="NTTE">
                                <input
                                    data-testid="report-ntte"
                                    value={form.data.report_ntte}
                                    onChange={(e) =>
                                        form.setData(
                                            'report_ntte',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Nomor Tanda Terima Elektronik"
                                    style={input}
                                />
                            </Field>
                        </div>

                        <Field label="Catatan">
                            <textarea
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                                rows={2}
                                style={{ ...input, resize: 'vertical' }}
                            />
                        </Field>

                        <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                            <button
                                type="button"
                                data-testid="simpan-kepatuhan"
                                style={btnSave}
                                disabled={form.processing}
                                onClick={submit}
                            >
                                <AIcon name="save" size={14} color="#fff" />
                                Simpan
                            </button>
                            <button
                                type="button"
                                style={btnCancel}
                                onClick={() => {
                                    form.reset();
                                    form.clearErrors();
                                    setEditing(false);
                                }}
                            >
                                Batal
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </Panel>
    );
}

const input: React.CSSProperties = {
    width: '100%',
    padding: '9px 11px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

const row: React.CSSProperties = {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: 12,
};

const errorText: React.CSSProperties = {
    fontSize: 11.5,
    color: C.red,
    marginTop: -6,
    marginBottom: 10,
};

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div style={{ marginBottom: 12 }}>
            <div
                style={{
                    fontSize: 11.5,
                    fontWeight: 600,
                    color: C.muted,
                    marginBottom: 5,
                }}
            >
                {label}
            </div>
            {children}
        </div>
    );
}
