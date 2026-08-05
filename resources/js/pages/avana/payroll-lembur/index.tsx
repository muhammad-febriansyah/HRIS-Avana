import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import OvertimeRuleController from '@/actions/App/Http/Controllers/Avana/OvertimeRuleController';
import { ActionBtn, AIcon, C, card } from '@/lib/avana';

interface Rate {
    id: number;
    day_type: string;
    day_type_label: string;
    hour_from: number;
    hour_to: number | null;
    multiplier: number;
}

interface BasisComponent {
    id: number;
    code: string | null;
    name: string;
    is_fixed: boolean;
    /** Gaji Pokok — always part of the basis, cannot be unticked. */
    locked: boolean;
    /** Paid per present day or per overtime hour, so never a fixed allowance. */
    variable: boolean;
}

interface WorkedExample {
    employee: string;
    basis: number;
    basis_floored: boolean;
    divisor: number;
    hourly: number;
    first_hour: number;
    later_hours: number;
    total: number;
}

interface Props {
    policy: {
        max_hours_per_day: number;
        max_hours_per_week: number;
        hours_divisor: number;
        rounding_minutes: number;
        fixed_basis_min_ratio: number;
        enforce_hour_limits: boolean;
    };
    rates: Rate[];
    dayTypes: { value: string; label: string }[];
    basis: BasisComponent[];
    example: WorkedExample | null;
}

const input: React.CSSProperties = {
    padding: '9px 11px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13.5,
    outline: 'none',
    color: C.text,
    width: '100%',
    background: '#fff',
};
const th: React.CSSProperties = {
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    letterSpacing: 0.4,
    textTransform: 'uppercase',
    color: C.muted,
    padding: '11px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const primaryBtn: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    padding: '9px 16px',
    borderRadius: 8,
    border: 'none',
    background: C.primary,
    color: '#fff',
    fontSize: 13,
    fontWeight: 600,
    cursor: 'pointer',
};
const label: React.CSSProperties = {
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    marginBottom: 6,
    display: 'block',
};
const sectionTitle: React.CSSProperties = {
    fontSize: 12.5,
    fontWeight: 700,
    letterSpacing: 0.5,
    textTransform: 'uppercase',
    color: C.navy,
    marginBottom: 6,
};
const hint: React.CSSProperties = {
    fontSize: 12.5,
    color: C.muted,
    marginBottom: 16,
    lineHeight: 1.55,
};

const rupiah = (n: number) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
const times = (n: number) => n.toLocaleString('id-ID') + '×';
const bandLabel = (r: Rate) =>
    r.hour_to === null
        ? `Jam ke-${r.hour_from} dst.`
        : r.hour_from === r.hour_to
          ? `Jam ke-${r.hour_from}`
          : `Jam ke-${r.hour_from} s.d. ke-${r.hour_to}`;

export default function PayrollLembur({
    policy,
    rates,
    dayTypes,
    basis,
    example,
}: Props) {
    const policyForm = useForm({
        max_hours_per_day: String(policy.max_hours_per_day),
        max_hours_per_week: String(policy.max_hours_per_week),
        hours_divisor: String(policy.hours_divisor),
        rounding_minutes: String(policy.rounding_minutes),
        fixed_basis_min_ratio: String(
            Math.round(policy.fixed_basis_min_ratio * 100),
        ),
        enforce_hour_limits: policy.enforce_hour_limits,
    });

    const rateForm = useForm({
        day_type: dayTypes[0]?.value ?? 'workday',
        hour_from: '1',
        hour_to: '',
        multiplier: '1.5',
    });

    const ratio = Math.round(policy.fixed_basis_min_ratio * 100);
    const ticked = basis.filter((c) => c.is_fixed);
    // Gaji Pokok, then the rest of the basis, then what is left out — the
    // order the setup design shows.
    const orderedBasis = [...basis].sort((a, b) => {
        if (a.locked !== b.locked) {
            return a.locked ? -1 : 1;
        }

        if (a.is_fixed !== b.is_fixed) {
            return a.is_fixed ? -1 : 1;
        }

        return a.name.localeCompare(b.name, 'id');
    });
    const firstHourMultiplier =
        rates.find((r) => r.day_type === 'workday' && r.hour_from === 1)
            ?.multiplier ?? 1.5;

    const toggleBasis = (component: BasisComponent, next: boolean) =>
        router.post(
            OvertimeRuleController.setBasisComponent(component.id).url,
            { is_fixed: next },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(errors.is_fixed ?? 'Komponen gagal disimpan'),
            },
        );

    const savePolicy = () =>
        router.put(
            OvertimeRuleController.updatePolicy().url,
            {
                max_hours_per_day: Number(policyForm.data.max_hours_per_day),
                max_hours_per_week: Number(policyForm.data.max_hours_per_week),
                hours_divisor: Number(policyForm.data.hours_divisor),
                rounding_minutes: Number(policyForm.data.rounding_minutes),
                // Stored as a ratio; typed as a percentage because that is how
                // the regulation is written ("75% dari upah sebulan").
                fixed_basis_min_ratio:
                    Number(policyForm.data.fixed_basis_min_ratio) / 100,
                enforce_hour_limits: policyForm.data.enforce_hour_limits,
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Aturan lembur disimpan'),
                onError: () => toast.error('Periksa isian aturan lembur'),
            },
        );

    const saveRate = () =>
        rateForm.post(OvertimeRuleController.storeRate().url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Baris pengali disimpan'),
            onError: () => toast.error('Periksa isian baris pengali'),
        });

    const delRate = (id: number) =>
        router.delete(OvertimeRuleController.destroyRate(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Baris pengali dihapus'),
        });

    const reset = () =>
        router.post(
            OvertimeRuleController.resetRates().url,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success('Tabel pengali dikembalikan ke PP 35/2021'),
            },
        );

    return (
        <>
            <Head title="Setup Lembur" />
            <div style={{ padding: '28px 32px', maxWidth: 1080 }}>
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
                    <span style={{ color: C.muted }}>Setup Lembur</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 18px',
                    }}
                >
                    Setup Lembur
                </h1>

                <div style={{ ...card, padding: 22, marginBottom: 18 }}>
                    <div style={sectionTitle}>Basis Perhitungan Lembur</div>
                    <div style={hint}>
                        Sesuai PP No. 35/2021 — basis lembur bukan Gaji Pokok
                        saja, tapi Gaji Pokok + komponen tunjangan yang ditandai
                        &ldquo;Tetap&rdquo;.
                    </div>

                    <div
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '8px 14px',
                            borderRadius: 999,
                            background: 'rgba(47,84,201,.07)',
                            color: C.primary,
                            fontSize: 13,
                            fontWeight: 600,
                            marginBottom: 16,
                        }}
                    >
                        <AIcon name="zap" size={14} color={C.primary} />
                        Basis aktif: 100% (Gaji Pokok + Tunjangan Tetap)
                    </div>

                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        {orderedBasis.map((component) => (
                            <label
                                key={component.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 11,
                                    padding: '11px 2px',
                                    borderBottom: `1px solid ${C.line}`,
                                    cursor: component.locked
                                        ? 'default'
                                        : 'pointer',
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={component.is_fixed}
                                    disabled={component.locked}
                                    onChange={(e) =>
                                        toggleBasis(component, e.target.checked)
                                    }
                                    style={{ width: 17, height: 17 }}
                                />
                                <span
                                    style={{
                                        flex: 1,
                                        fontSize: 14,
                                        color: component.is_fixed
                                            ? C.text
                                            : C.faint,
                                    }}
                                >
                                    {component.name}
                                </span>
                                {component.locked && (
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: C.muted,
                                            background: C.surface,
                                            padding: '4px 11px',
                                            borderRadius: 999,
                                        }}
                                    >
                                        selalu ikut
                                    </span>
                                )}
                                {!component.is_fixed && !component.locked && (
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                        }}
                                    >
                                        {component.variable
                                            ? 'tidak tetap'
                                            : 'tidak ikut'}
                                    </span>
                                )}
                            </label>
                        ))}
                    </div>

                    <div style={{ ...hint, marginTop: 14, marginBottom: 0 }}>
                        Jika total komponen tetap di atas &lt; {ratio}% dari
                        total penghasilan karyawan, sistem otomatis beralih ke
                        basis {ratio}% dari total penghasilan bulanan.
                    </div>
                </div>

                <div style={{ ...card, padding: 22, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            justifyContent: 'space-between',
                            gap: 12,
                        }}
                    >
                        <div>
                            <div style={sectionTitle}>
                                Tabel Pengali (Multiplier)
                            </div>
                            <div style={hint}>
                                Upah sejam = basis bulanan ÷{' '}
                                {policy.hours_divisor}, dikalikan pengali
                                berikut sesuai jenis hari &amp; jam ke-berapa.
                            </div>
                        </div>
                        <ActionBtn
                            icon="rotate-ccw"
                            label="Reset ke PP 35/2021"
                            onClick={reset}
                        />
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
                                    <th style={th}>Jenis Hari</th>
                                    <th style={th}>Jam Lembur</th>
                                    <th style={{ ...th, textAlign: 'right' }}>
                                        Pengali
                                    </th>
                                    <th style={th} />
                                </tr>
                            </thead>
                            <tbody>
                                {rates.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada pengali.
                                        </td>
                                    </tr>
                                ) : (
                                    rates.map((r) => (
                                        <tr key={r.id}>
                                            <td style={td}>
                                                {r.day_type_label}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {bandLabel(r)}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                    fontWeight: 600,
                                                    color: C.primary,
                                                }}
                                            >
                                                {times(r.multiplier)}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                    width: 90,
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() =>
                                                        delRate(r.id)
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            gap: 9,
                            marginTop: 16,
                            padding: '11px 14px',
                            borderRadius: 8,
                            background: '#FFFBEB',
                            border: `1px solid ${C.amber}33`,
                        }}
                    >
                        <AIcon
                            name="triangle-alert"
                            size={15}
                            color={C.amber}
                        />
                        <span
                            style={{
                                fontSize: 12.5,
                                color: C.amber,
                                fontWeight: 500,
                            }}
                        >
                            Validasi batas: maks. {policy.max_hours_per_day}{' '}
                            jam/hari · {policy.max_hours_per_week} jam/minggu
                            (dari data Cuti &amp; Lembur yang disetujui)
                            {!policy.enforce_hour_limits &&
                                ' — penegakan sedang dimatikan'}
                        </span>
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.6fr 1fr 1fr 1fr auto',
                            gap: 12,
                            alignItems: 'end',
                            marginTop: 18,
                            paddingTop: 18,
                            borderTop: `1px solid ${C.line}`,
                        }}
                    >
                        <div>
                            <span style={label}>Jenis hari</span>
                            <select
                                style={input}
                                value={rateForm.data.day_type}
                                onChange={(e) =>
                                    rateForm.setData('day_type', e.target.value)
                                }
                            >
                                {dayTypes.map((d) => (
                                    <option key={d.value} value={d.value}>
                                        {d.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <span style={label}>Jam ke-</span>
                            <input
                                style={input}
                                type="number"
                                min={1}
                                value={rateForm.data.hour_from}
                                onChange={(e) =>
                                    rateForm.setData(
                                        'hour_from',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <span style={label}>s.d. (kosong = dst.)</span>
                            <input
                                style={input}
                                type="number"
                                min={1}
                                value={rateForm.data.hour_to}
                                onChange={(e) =>
                                    rateForm.setData('hour_to', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <span style={label}>Pengali</span>
                            <input
                                style={input}
                                type="number"
                                step="0.25"
                                value={rateForm.data.multiplier}
                                onChange={(e) =>
                                    rateForm.setData(
                                        'multiplier',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <button
                            style={{ ...primaryBtn, background: C.green }}
                            disabled={rateForm.processing}
                            onClick={saveRate}
                        >
                            <AIcon name="plus" size={15} color="#fff" />
                            Tambah
                        </button>
                    </div>
                </div>

                {example !== null && (
                    <div style={{ ...card, padding: 22, marginBottom: 18 }}>
                        <div style={sectionTitle}>
                            Contoh Perhitungan — {example.employee}
                        </div>
                        <div style={hint}>
                            Simulasi 3 jam lembur pada hari kerja, memakai basis
                            di atas ({rupiah(example.basis)}
                            {example.basis_floored &&
                                `, hasil aturan ${ratio}%`}
                            ).
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
                                        <th style={th}>Komponen</th>
                                        <th style={th}>Perhitungan</th>
                                        <th
                                            style={{
                                                ...th,
                                                textAlign: 'right',
                                            }}
                                        >
                                            Nilai
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style={td}>Upah sejam</td>
                                        <td style={{ ...td, color: C.muted }}>
                                            {rupiah(example.basis)} ÷{' '}
                                            {example.divisor}
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                textAlign: 'right',
                                                color: C.primary,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {rupiah(example.hourly)}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={td}>Jam ke-1</td>
                                        <td style={{ ...td, color: C.muted }}>
                                            {times(firstHourMultiplier)}{' '}
                                            {rupiah(example.hourly)}
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                textAlign: 'right',
                                                color: C.primary,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {rupiah(example.first_hour)}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={td}>Jam ke-2 &amp; ke-3</td>
                                        <td style={{ ...td, color: C.muted }}>
                                            2 × {rupiah(example.hourly)} × 2 jam
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                textAlign: 'right',
                                                color: C.primary,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {rupiah(example.later_hours)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                                marginTop: 16,
                                padding: '10px 16px',
                                borderRadius: 8,
                                background: '#F0FDF4',
                                color: C.green,
                                fontSize: 13.5,
                                fontWeight: 600,
                            }}
                        >
                            <AIcon name="check" size={15} color={C.green} />
                            Total Upah Lembur (3 jam) · {rupiah(example.total)}
                        </div>

                        <div
                            style={{
                                marginTop: 18,
                                padding: '14px 16px',
                                borderRadius: 8,
                                background: C.navy,
                                color: '#E6EAF5',
                                fontSize: 12.5,
                                fontFamily:
                                    'ui-monospace, SFMono-Regular, Menlo, monospace',
                                lineHeight: 1.6,
                            }}
                        >
                            Upah Lembur = (Basis Bulanan ÷{' '}
                            {policy.hours_divisor}) × Pengali × Jam Lembur
                            Disetujui
                        </div>
                    </div>
                )}

                <div style={{ ...card, padding: 22 }}>
                    <div style={sectionTitle}>Aturan Dasar</div>
                    <div style={hint}>
                        Batas jam dan pembagi yang dipakai seluruh perhitungan
                        di atas.
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(5, 1fr) auto',
                            gap: 12,
                            alignItems: 'end',
                        }}
                    >
                        <div>
                            <span style={label}>Maks. jam / hari</span>
                            <input
                                style={input}
                                type="number"
                                step="0.5"
                                value={policyForm.data.max_hours_per_day}
                                onChange={(e) =>
                                    policyForm.setData(
                                        'max_hours_per_day',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <span style={label}>Maks. jam / minggu</span>
                            <input
                                style={input}
                                type="number"
                                step="0.5"
                                value={policyForm.data.max_hours_per_week}
                                onChange={(e) =>
                                    policyForm.setData(
                                        'max_hours_per_week',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <span style={label}>Pembagi jam (1/x)</span>
                            <input
                                style={input}
                                type="number"
                                value={policyForm.data.hours_divisor}
                                onChange={(e) =>
                                    policyForm.setData(
                                        'hours_divisor',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <span style={label}>Pembulatan durasi (menit)</span>
                            <input
                                style={input}
                                type="number"
                                min="0"
                                step="5"
                                value={policyForm.data.rounding_minutes}
                                onChange={(e) =>
                                    policyForm.setData(
                                        'rounding_minutes',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <span style={label}>Batas minimum basis (%)</span>
                            <input
                                style={input}
                                type="number"
                                value={policyForm.data.fixed_basis_min_ratio}
                                onChange={(e) =>
                                    policyForm.setData(
                                        'fixed_basis_min_ratio',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <button
                            style={{ ...primaryBtn, background: C.green }}
                            onClick={savePolicy}
                        >
                            <AIcon name="save" size={15} color="#fff" />
                            Simpan
                        </button>
                    </div>
                    <label
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 8,
                            marginTop: 14,
                            fontSize: 13,
                            color: C.text,
                            cursor: 'pointer',
                        }}
                    >
                        <input
                            type="checkbox"
                            checked={policyForm.data.enforce_hour_limits}
                            onChange={(e) =>
                                policyForm.setData(
                                    'enforce_hour_limits',
                                    e.target.checked,
                                )
                            }
                        />
                        Tolak pengajuan lembur yang melewati batas jam
                    </label>
                    <div style={{ ...hint, marginTop: 10, marginBottom: 0 }}>
                        Komponen basis ditandai lewat daftar centang di atas (
                        {ticked.length} komponen aktif).
                    </div>
                    <div style={{ ...hint, marginTop: 6, marginBottom: 0 }}>
                        {Number(policyForm.data.rounding_minutes) > 0
                            ? `Durasi lembur dibulatkan ke bawah kelipatan ${policyForm.data.rounding_minutes} menit — kurang dari itu tidak dihitung (mis. 45 menit = ${(Math.floor(45 / Number(policyForm.data.rounding_minutes)) * Number(policyForm.data.rounding_minutes)) / 60} jam).`
                            : 'Pembulatan 0 = durasi dihitung apa adanya (45 menit = 0,75 jam).'}
                    </div>
                </div>
            </div>
        </>
    );
}
