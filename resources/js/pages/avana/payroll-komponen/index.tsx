import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import PayrollKomponenController from '@/actions/App/Http/Controllers/Avana/PayrollKomponenController';
import { AIcon, ActionBtn, C, card } from '@/lib/avana';

interface ComponentValue {
    id: number;
    kategori: string | null;
    employment_status: string | null;
    position: string | null;
    job_level: string | null;
    branch: string | null;
    value: number;
    note: string | null;
}

interface Component {
    id: number;
    code: string | null;
    name: string;
    group: string;
    is_taxable: boolean;
    show_on_slip: boolean;
    calc_basis: string;
    period_basis: string | null;
    formula: string | null;
    basis_type: string | null;
    basis_value: number | null;
    basis_min: number | null;
    basis_max: number | null;
    basis_cut_off_day: number | null;
    payroll_formula_id: number | null;
    values: ComponentValue[];
    status: string;
}

interface Option {
    id: number;
    name: string;
}

interface MappingOptions {
    positions: Option[];
    jobLevels: Option[];
    branches: Option[];
    kategori: string[];
    statuses: string[];
}

interface FormulaItem {
    id: number;
    tipe: string;
    component: string | null;
    component_id: number | null;
    operator: string;
    nilai: number;
    prorate: boolean;
}

interface Formula {
    id: number;
    name: string;
    note: string | null;
    is_active: boolean;
    items: FormulaItem[];
}

interface Props {
    components: Component[];
    formulas: Formula[];
    componentOptions: { id: number; name: string }[];
    formulaOptions: Option[];
    mappingOptions: MappingOptions;
}

const BASIS_LABEL: Record<string, string> = {
    fixed: 'Tetap',
    percentage: 'Persentase',
    per_present_day: 'Per Hari Hadir',
    per_overtime_hour: 'Per Jam Lembur',
};

const DASAR_LABEL: Record<string, string> = {
    '': 'Bawaan (posisi/kehadiran)',
    fixed: 'Fixed (nilai tetap)',
    tabel: 'Tabel (nilai komponen)',
    formula: 'Formula (kombinasi)',
};

const rupiah = (n: number) => 'Rp ' + n.toLocaleString('id-ID');

const input: React.CSSProperties = {
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    width: '100%',
};
const th: React.CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const primaryBtn: React.CSSProperties = {
    padding: '9px 16px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};

function Pill({ on, label }: { on: boolean; label: string }) {
    return (
        <span
            style={{
                fontSize: 11.5,
                fontWeight: 700,
                padding: '3px 9px',
                borderRadius: 6,
                color: on ? '#15803D' : C.muted,
                background: on ? '#DCFCE7' : C.line,
            }}
        >
            {label}
        </span>
    );
}

export default function PayrollKomponen({
    components,
    formulas,
    componentOptions,
    formulaOptions,
    mappingOptions,
}: Props) {
    const [tab, setTab] = useState<'komponen' | 'nilai' | 'formula'>(
        'komponen',
    );

    return (
        <>
            <Head title="Master Komponen" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ marginBottom: 20 }}>
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
                        <span>Payroll</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Master Komponen</span>
                    </div>
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                        }}
                    >
                        Master Komponen &amp; Formula
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Kelola komponen penerimaan/potongan &amp; kombinasi
                        formula perhitungan gaji.
                    </div>
                </div>

                <div style={{ display: 'flex', gap: 8, marginBottom: 18 }}>
                    {(
                        [
                            ['komponen', 'Master Komponen'],
                            ['nilai', 'Nilai Komponen'],
                            ['formula', 'Master Formula'],
                        ] as const
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => setTab(key)}
                            style={{
                                padding: '9px 15px',
                                borderRadius: 8,
                                border: `1px solid ${tab === key ? C.primary : C.line}`,
                                background: tab === key ? C.primary + '10' : '#fff',
                                color: tab === key ? C.primary : C.muted,
                                fontSize: 13.5,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'komponen' && (
                    <KomponenTab
                        components={components}
                        formulaOptions={formulaOptions}
                    />
                )}
                {tab === 'nilai' && (
                    <NilaiKomponenTab
                        components={components}
                        mappingOptions={mappingOptions}
                    />
                )}
                {tab === 'formula' && (
                    <FormulaTab
                        formulas={formulas}
                        componentOptions={componentOptions}
                    />
                )}
            </div>
        </>
    );
}

function KomponenTab({
    components,
    formulaOptions,
}: {
    components: Component[];
    formulaOptions: Option[];
}) {
    const form = useForm({
        code: '',
        name: '',
        group: 'penerimaan',
        calc_basis: 'fixed',
        period_basis: '',
        is_taxable: true,
        show_on_slip: true,
        basis_type: '',
        basis_value: '',
        basis_min: '',
        basis_max: '',
        basis_cut_off_day: '',
        payroll_formula_id: '',
    });

    const submit = () => {
        form.post(PayrollKomponenController.storeComponent().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Komponen disimpan');
                form.reset(
                    'code',
                    'name',
                    'basis_value',
                    'basis_min',
                    'basis_max',
                    'basis_cut_off_day',
                );
            },
            onError: () => toast.error('Periksa isian komponen'),
        });
    };

    const del = (id: number) =>
        router.delete(PayrollKomponenController.destroyComponent(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Komponen dihapus'),
        });

    const groups = [
        ['penerimaan', 'Komponen Penerimaan'],
        ['potongan', 'Komponen Potongan'],
    ] as const;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            {/* Add form */}
            <div style={{ ...card, padding: 18 }}>
                <div
                    style={{
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 14,
                    }}
                >
                    Tambah Komponen
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '110px 1.4fr 1fr 1fr 1fr',
                        gap: 12,
                        marginBottom: 14,
                    }}
                >
                    <input
                        style={input}
                        placeholder="Kode"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                    />
                    <input
                        style={input}
                        placeholder="Nama komponen"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <select
                        style={input}
                        value={form.data.group}
                        onChange={(e) => form.setData('group', e.target.value)}
                    >
                        <option value="penerimaan">Penerimaan</option>
                        <option value="potongan">Potongan</option>
                    </select>
                    <select
                        style={input}
                        value={form.data.calc_basis}
                        onChange={(e) =>
                            form.setData('calc_basis', e.target.value)
                        }
                    >
                        {Object.entries(BASIS_LABEL).map(([v, l]) => (
                            <option key={v} value={v}>
                                {l}
                            </option>
                        ))}
                    </select>
                    <select
                        style={input}
                        value={form.data.period_basis}
                        onChange={(e) =>
                            form.setData('period_basis', e.target.value)
                        }
                    >
                        <option value="">Periode —</option>
                        <option value="berjalan">Berjalan</option>
                        <option value="bulan_lalu">Bulan Lalu</option>
                    </select>
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 20,
                    }}
                >
                    <label
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 13,
                            color: C.text,
                            cursor: 'pointer',
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={form.data.is_taxable}
                            onChange={(e) =>
                                form.setData('is_taxable', e.target.checked)
                            }
                        />
                        Perhitungan Pajak (masuk PPh 21)
                    </label>
                    <label
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 13,
                            color: C.text,
                            cursor: 'pointer',
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={form.data.show_on_slip}
                            onChange={(e) =>
                                form.setData('show_on_slip', e.target.checked)
                            }
                        />
                        Tampil di Slip Gaji
                    </label>
                </div>

                {/* Dasar Perhitungan (BPR manual 1.2.2) */}
                <div
                    style={{
                        marginTop: 16,
                        paddingTop: 14,
                        borderTop: `1px solid ${C.line}`,
                    }}
                >
                    <div
                        style={{
                            fontSize: 13,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 10,
                        }}
                    >
                        Dasar Perhitungan
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.4fr 1fr 1fr 1fr',
                            gap: 12,
                            alignItems: 'end',
                        }}
                    >
                        <select
                            style={input}
                            value={form.data.basis_type}
                            onChange={(e) =>
                                form.setData('basis_type', e.target.value)
                            }
                        >
                            {Object.entries(DASAR_LABEL).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>

                        {form.data.basis_type === 'fixed' && (
                            <input
                                style={input}
                                type="number"
                                placeholder="Nilai tetap"
                                value={form.data.basis_value}
                                onChange={(e) =>
                                    form.setData('basis_value', e.target.value)
                                }
                            />
                        )}

                        {form.data.basis_type === 'formula' && (
                            <>
                                <select
                                    style={input}
                                    value={form.data.payroll_formula_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'payroll_formula_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih formula —</option>
                                    {formulaOptions.map((f) => (
                                        <option key={f.id} value={f.id}>
                                            {f.name}
                                        </option>
                                    ))}
                                </select>
                                <input
                                    style={input}
                                    type="number"
                                    placeholder="Min (opsional)"
                                    value={form.data.basis_min}
                                    onChange={(e) =>
                                        form.setData('basis_min', e.target.value)
                                    }
                                />
                                <input
                                    style={input}
                                    type="number"
                                    placeholder="Max (opsional)"
                                    value={form.data.basis_max}
                                    onChange={(e) =>
                                        form.setData('basis_max', e.target.value)
                                    }
                                />
                            </>
                        )}

                        {form.data.basis_type === 'tabel' && (
                            <div
                                style={{
                                    gridColumn: 'span 2',
                                    fontSize: 12.5,
                                    color: C.faint,
                                }}
                            >
                                Atur nilainya di tab{' '}
                                <strong>Nilai Komponen</strong> setelah komponen
                                dibuat.
                            </div>
                        )}
                    </div>
                </div>

                <div style={{ marginTop: 14, textAlign: 'right' }}>
                    <button
                        style={primaryBtn}
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Tambah Komponen
                    </button>
                </div>
            </div>

            {groups.map(([key, label]) => {
                const rows = components.filter((c) => c.group === key);

                return (
                    <div
                        key={key}
                        style={{ ...card, padding: 0, overflow: 'hidden' }}
                    >
                        <div
                            style={{
                                padding: '16px 18px 6px',
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            {label} ({rows.length})
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                }}
                            >
                                <thead>
                                    <tr>
                                        {[
                                            'Kode',
                                            'Nama',
                                            'Basis',
                                            'Pajak',
                                            'Slip',
                                            'Periode',
                                            '',
                                        ].map((h, i) => (
                                            <th key={i} style={th}>
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                style={{
                                                    ...td,
                                                    textAlign: 'center',
                                                    color: C.faint,
                                                }}
                                            >
                                                Belum ada komponen.
                                            </td>
                                        </tr>
                                    ) : (
                                        rows.map((c) => (
                                            <tr key={c.id}>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {c.code ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {c.name}
                                                </td>
                                                <td style={td}>
                                                    {c.basis_type ? (
                                                        <span
                                                            style={{
                                                                fontSize: 11.5,
                                                                fontWeight: 700,
                                                                padding: '3px 8px',
                                                                borderRadius: 6,
                                                                background:
                                                                    C.primary +
                                                                    '14',
                                                                color: C.primary,
                                                            }}
                                                        >
                                                            {DASAR_LABEL[
                                                                c.basis_type
                                                            ] ?? c.basis_type}
                                                        </span>
                                                    ) : (
                                                        (BASIS_LABEL[
                                                            c.calc_basis
                                                        ] ?? c.calc_basis)
                                                    )}
                                                </td>
                                                <td style={td}>
                                                    <Pill
                                                        on={c.is_taxable}
                                                        label={
                                                            c.is_taxable
                                                                ? 'Ya'
                                                                : 'Tidak'
                                                        }
                                                    />
                                                </td>
                                                <td style={td}>
                                                    <Pill
                                                        on={c.show_on_slip}
                                                        label={
                                                            c.show_on_slip
                                                                ? 'Ya'
                                                                : 'Tidak'
                                                        }
                                                    />
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {c.period_basis ?? '—'}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() => del(c.id)}
                                                    />
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function NilaiKomponenTab({
    components,
    mappingOptions,
}: {
    components: Component[];
    mappingOptions: MappingOptions;
}) {
    const [selectedId, setSelectedId] = useState<number | null>(
        components[0]?.id ?? null,
    );
    const selected = components.find((c) => c.id === selectedId) ?? null;

    const form = useForm({
        kategori: '',
        employment_status: '',
        position_id: '',
        job_level_id: '',
        branch_id: '',
        value: '',
        note: '',
    });

    const add = () => {
        if (selected === null) {
            return;
        }

        form.post(
            PayrollKomponenController.storeComponentValue(selected.id).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Nilai komponen disimpan');
                    form.reset('value', 'note');
                },
                onError: () => toast.error('Periksa isian nilai'),
            },
        );
    };

    const del = (id: number) =>
        router.delete(PayrollKomponenController.destroyComponentValue(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Nilai dihapus'),
        });

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            <div style={{ ...card, padding: 18 }}>
                <div
                    style={{
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 6,
                    }}
                >
                    Nilai Komponen (mapping)
                </div>
                <div
                    style={{ fontSize: 12.5, color: C.faint, marginBottom: 14 }}
                >
                    Setel nilai komponen per jenis pegawai, status, posisi,
                    pangkat, atau cabang. Baris paling spesifik yang cocok akan
                    dipakai saat proses gaji (komponen dengan dasar perhitungan{' '}
                    <strong>Tabel</strong>).
                </div>
                <select
                    style={{ ...input, maxWidth: 360 }}
                    value={selectedId ?? ''}
                    onChange={(e) => setSelectedId(Number(e.target.value))}
                >
                    {components.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                            {c.basis_type === 'tabel' ? ' • Tabel' : ''}
                        </option>
                    ))}
                </select>
            </div>

            {selected && (
                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '14px 18px',
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                            borderBottom: `1px solid ${C.line}`,
                        }}
                    >
                        {selected.name}
                        {selected.basis_type !== 'tabel' && (
                            <span
                                style={{
                                    marginLeft: 10,
                                    fontSize: 11.5,
                                    color: '#B45309',
                                    fontWeight: 500,
                                }}
                            >
                                Dasar perhitungan bukan "Tabel" — nilai ini belum
                                dipakai engine.
                            </span>
                        )}
                    </div>

                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{ width: '100%', borderCollapse: 'collapse' }}
                        >
                            <thead>
                                <tr>
                                    {[
                                        'Kategori',
                                        'Status',
                                        'Posisi',
                                        'Pangkat',
                                        'Cabang',
                                        'Nilai',
                                        '',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {selected.values.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada nilai. Baris tanpa filter =
                                            berlaku untuk semua pegawai.
                                        </td>
                                    </tr>
                                ) : (
                                    selected.values.map((v) => (
                                        <tr key={v.id}>
                                            <td style={td}>
                                                {v.kategori ?? '—'}
                                            </td>
                                            <td style={td}>
                                                {v.employment_status ?? '—'}
                                            </td>
                                            <td style={td}>
                                                {v.position ?? '—'}
                                            </td>
                                            <td style={td}>
                                                {v.job_level ?? '—'}
                                            </td>
                                            <td style={td}>{v.branch ?? '—'}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {rupiah(v.value)}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() => del(v.id)}
                                                />
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Add mapping row */}
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                '1fr 1fr 1fr 1fr 1fr 1fr auto',
                            gap: 10,
                            padding: 14,
                            alignItems: 'center',
                            background: C.surface,
                        }}
                    >
                        <select
                            style={input}
                            value={form.data.kategori}
                            onChange={(e) =>
                                form.setData('kategori', e.target.value)
                            }
                        >
                            <option value="">Kategori —</option>
                            {mappingOptions.kategori.map((k) => (
                                <option key={k} value={k}>
                                    {k}
                                </option>
                            ))}
                        </select>
                        <select
                            style={input}
                            value={form.data.employment_status}
                            onChange={(e) =>
                                form.setData(
                                    'employment_status',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Status —</option>
                            {mappingOptions.statuses.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                        <select
                            style={input}
                            value={form.data.position_id}
                            onChange={(e) =>
                                form.setData('position_id', e.target.value)
                            }
                        >
                            <option value="">Posisi —</option>
                            {mappingOptions.positions.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <select
                            style={input}
                            value={form.data.job_level_id}
                            onChange={(e) =>
                                form.setData('job_level_id', e.target.value)
                            }
                        >
                            <option value="">Pangkat —</option>
                            {mappingOptions.jobLevels.map((j) => (
                                <option key={j.id} value={j.id}>
                                    {j.name}
                                </option>
                            ))}
                        </select>
                        <select
                            style={input}
                            value={form.data.branch_id}
                            onChange={(e) =>
                                form.setData('branch_id', e.target.value)
                            }
                        >
                            <option value="">Cabang —</option>
                            {mappingOptions.branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                        <input
                            style={input}
                            type="number"
                            placeholder="Nilai"
                            value={form.data.value}
                            onChange={(e) =>
                                form.setData('value', e.target.value)
                            }
                        />
                        <button
                            style={primaryBtn}
                            disabled={form.processing}
                            onClick={add}
                        >
                            + Nilai
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function FormulaTab({
    formulas,
    componentOptions,
}: {
    formulas: Formula[];
    componentOptions: { id: number; name: string }[];
}) {
    const newForm = useForm({ name: '', note: '' });

    const createFormula = () => {
        newForm.post(PayrollKomponenController.storeFormula().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Master Formula dibuat');
                newForm.reset();
            },
            onError: () => toast.error('Periksa isian formula'),
        });
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            <div style={{ ...card, padding: 18 }}>
                <div
                    style={{
                        fontSize: 14,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 14,
                    }}
                >
                    Buat Master Formula
                </div>
                <div style={{ display: 'flex', gap: 12 }}>
                    <input
                        style={{ ...input, flex: 1 }}
                        placeholder="Nama formula (mis. Gaji Kotor)"
                        value={newForm.data.name}
                        onChange={(e) => newForm.setData('name', e.target.value)}
                    />
                    <input
                        style={{ ...input, flex: 1 }}
                        placeholder="Keterangan (opsional)"
                        value={newForm.data.note}
                        onChange={(e) => newForm.setData('note', e.target.value)}
                    />
                    <button
                        style={primaryBtn}
                        disabled={newForm.processing}
                        onClick={createFormula}
                    >
                        Buat
                    </button>
                </div>
            </div>

            {formulas.length === 0 ? (
                <div
                    style={{
                        ...card,
                        padding: 40,
                        textAlign: 'center',
                        color: C.faint,
                        fontSize: 14,
                    }}
                >
                    Belum ada Master Formula.
                </div>
            ) : (
                formulas.map((f) => (
                    <FormulaCard
                        key={f.id}
                        formula={f}
                        componentOptions={componentOptions}
                    />
                ))
            )}
        </div>
    );
}

function FormulaCard({
    formula,
    componentOptions,
}: {
    formula: Formula;
    componentOptions: { id: number; name: string }[];
}) {
    const itemForm = useForm({
        tipe: 'penerimaan',
        payroll_component_id: '',
        operator: '*',
        nilai: '1',
        prorate: false as boolean,
    });

    const addItem = () => {
        itemForm.post(
            PayrollKomponenController.storeFormulaItem(formula.id).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Item ditambahkan');
                    itemForm.reset('payroll_component_id');
                },
                onError: () => toast.error('Periksa isian item'),
            },
        );
    };

    const delItem = (id: number) =>
        router.delete(PayrollKomponenController.destroyFormulaItem(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Item dihapus'),
        });

    const delFormula = () =>
        router.delete(PayrollKomponenController.destroyFormula(formula.id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Formula dihapus'),
        });

    return (
        <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '14px 18px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div>
                    <div
                        style={{
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        {formula.name}
                    </div>
                    {formula.note && (
                        <div style={{ fontSize: 12.5, color: C.faint }}>
                            {formula.note}
                        </div>
                    )}
                </div>
                <button
                    onClick={delFormula}
                    style={{
                        border: 'none',
                        background: 'transparent',
                        cursor: 'pointer',
                    }}
                >
                    <AIcon name="trash-2" size={16} color={C.red} />
                </button>
            </div>

            {/* Items */}
            <div style={{ padding: '4px 18px' }}>
                {formula.items.length === 0 ? (
                    <div
                        style={{
                            padding: '14px 0',
                            fontSize: 13,
                            color: C.faint,
                        }}
                    >
                        Belum ada kombinasi komponen.
                    </div>
                ) : (
                    formula.items.map((it) => (
                        <div
                            key={it.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                padding: '10px 0',
                                borderBottom: `1px solid ${C.line}`,
                                fontSize: 13.5,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 11.5,
                                    fontWeight: 700,
                                    padding: '3px 8px',
                                    borderRadius: 6,
                                    background: C.surface,
                                    color: C.muted,
                                    textTransform: 'capitalize',
                                }}
                            >
                                {it.tipe}
                            </span>
                            <span style={{ color: C.navy, fontWeight: 600 }}>
                                {it.component ?? 'UMR'}
                            </span>
                            <span style={{ color: C.muted }}>
                                {it.operator} {it.nilai}
                            </span>
                            {it.prorate && (
                                <span
                                    style={{
                                        fontSize: 11,
                                        color: C.primary,
                                    }}
                                >
                                    prorate
                                </span>
                            )}
                            <button
                                onClick={() => delItem(it.id)}
                                style={{
                                    marginLeft: 'auto',
                                    border: 'none',
                                    background: 'transparent',
                                    cursor: 'pointer',
                                }}
                            >
                                <AIcon name="x" size={14} color={C.faint} />
                            </button>
                        </div>
                    ))
                )}
            </div>

            {/* Add item row */}
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1.4fr 70px 90px auto auto',
                    gap: 10,
                    padding: 14,
                    alignItems: 'center',
                    background: C.surface,
                }}
            >
                <select
                    style={input}
                    value={itemForm.data.tipe}
                    onChange={(e) => itemForm.setData('tipe', e.target.value)}
                >
                    <option value="penerimaan">Penerimaan</option>
                    <option value="potongan">Potongan</option>
                    <option value="umr">UMR</option>
                </select>
                <select
                    style={input}
                    value={itemForm.data.payroll_component_id}
                    onChange={(e) =>
                        itemForm.setData('payroll_component_id', e.target.value)
                    }
                >
                    <option value="">Komponen —</option>
                    {componentOptions.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </select>
                <select
                    style={input}
                    value={itemForm.data.operator}
                    onChange={(e) =>
                        itemForm.setData('operator', e.target.value)
                    }
                >
                    <option value="*">×</option>
                    <option value="+">+</option>
                </select>
                <input
                    style={input}
                    type="number"
                    step="0.01"
                    value={itemForm.data.nilai}
                    onChange={(e) => itemForm.setData('nilai', e.target.value)}
                />
                <label
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 6,
                        fontSize: 12.5,
                        color: C.text,
                        cursor: 'pointer',
                    }}
                >
                    <input
                        type="checkbox"
                        checked={itemForm.data.prorate}
                        onChange={(e) =>
                            itemForm.setData('prorate', e.target.checked)
                        }
                    />
                    prorate
                </label>
                <button style={primaryBtn} onClick={addItem}>
                    + Item
                </button>
            </div>
        </div>
    );
}
