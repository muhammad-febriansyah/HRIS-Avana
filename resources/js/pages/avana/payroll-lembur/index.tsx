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

interface Basis {
    id: number;
    code: string;
    category: string | null;
    components: string[];
    total: number;
}

interface Props {
    policy: {
        max_hours_per_day: number;
        max_hours_per_week: number;
        hours_divisor: number;
        fixed_basis_min_ratio: number;
        enforce_hour_limits: boolean;
    };
    rates: Rate[];
    dayTypes: { value: string; label: string }[];
    basis: Basis[];
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
    fontSize: 14,
    fontWeight: 600,
    color: C.navy,
    marginBottom: 4,
};
const hint: React.CSSProperties = { fontSize: 12.5, color: C.faint, marginBottom: 14 };

const rupiah = (n: number) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
const bandLabel = (r: Rate) =>
    r.hour_to === null
        ? `Jam ke-${r.hour_from} dst.`
        : r.hour_from === r.hour_to
          ? `Jam ke-${r.hour_from}`
          : `Jam ke-${r.hour_from} s.d. ke-${r.hour_to}`;

export default function PayrollLembur({ policy, rates, dayTypes, basis }: Props) {
    const policyForm = useForm({
        max_hours_per_day: String(policy.max_hours_per_day),
        max_hours_per_week: String(policy.max_hours_per_week),
        hours_divisor: String(policy.hours_divisor),
        fixed_basis_min_ratio: String(Math.round(policy.fixed_basis_min_ratio * 100)),
        enforce_hour_limits: policy.enforce_hour_limits,
    });

    const rateForm = useForm({
        day_type: dayTypes[0]?.value ?? 'workday',
        hour_from: '1',
        hour_to: '',
        multiplier: '1.5',
    });

    const savePolicy = () =>
        router.put(
            OvertimeRuleController.updatePolicy().url,
            {
                max_hours_per_day: Number(policyForm.data.max_hours_per_day),
                max_hours_per_week: Number(policyForm.data.max_hours_per_week),
                hours_divisor: Number(policyForm.data.hours_divisor),
                // Stored as a ratio; typed as a percentage because that is how
                // the regulation is written ("75% dari upah sebulan").
                fixed_basis_min_ratio: Number(policyForm.data.fixed_basis_min_ratio) / 100,
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
                onSuccess: () => toast.success('Tabel pengali dikembalikan ke PP 35/2021'),
            },
        );

    const grouped = dayTypes.map((dt) => ({
        ...dt,
        rows: rates.filter((r) => r.day_type === dt.value),
    }));

    return (
        <>
            <Head title="Setup Lembur" />
            <div style={{ padding: '28px 32px' }}>
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
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 4px' }}>
                    Setup Lembur
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Basis perhitungan, tabel pengali per jenis hari, dan batas jam — mengacu PP No. 35
                    Tahun 2021. Dipakai langsung oleh Payroll Run.
                </div>

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div style={sectionTitle}>Aturan Dasar</div>
                    <div style={hint}>
                        Upah Lembur = (Basis Bulanan ÷ {policyForm.data.hours_divisor}) × Pengali × Jam
                        Lembur.
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(4, 1fr) auto',
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
                                onChange={(e) => policyForm.setData('max_hours_per_day', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Maks. jam / minggu</span>
                            <input
                                style={input}
                                type="number"
                                step="0.5"
                                value={policyForm.data.max_hours_per_week}
                                onChange={(e) => policyForm.setData('max_hours_per_week', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Pembagi jam (1/x)</span>
                            <input
                                style={input}
                                type="number"
                                value={policyForm.data.hours_divisor}
                                onChange={(e) => policyForm.setData('hours_divisor', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Batas minimum basis (%)</span>
                            <input
                                style={input}
                                type="number"
                                value={policyForm.data.fixed_basis_min_ratio}
                                onChange={(e) =>
                                    policyForm.setData('fixed_basis_min_ratio', e.target.value)
                                }
                            />
                        </div>
                        <button style={{ ...primaryBtn, background: C.green }} onClick={savePolicy}>
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
                            onChange={(e) => policyForm.setData('enforce_hour_limits', e.target.checked)}
                        />
                        Tolak pengajuan lembur yang melewati batas jam
                    </label>
                    <div style={{ ...hint, marginTop: 8, marginBottom: 0 }}>
                        Batas minimum basis: jika komponen tetap kurang dari{' '}
                        {policyForm.data.fixed_basis_min_ratio}% total penghasilan bulanan, basis lembur
                        otomatis memakai {policyForm.data.fixed_basis_min_ratio}% dari total penghasilan.
                    </div>
                </div>

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            marginBottom: 4,
                        }}
                    >
                        <div style={sectionTitle}>Tabel Pengali</div>
                        <ActionBtn icon="rotate-ccw" label="Reset ke PP 35/2021" onClick={reset} />
                    </div>
                    <div style={hint}>
                        Pengali per jenis hari dan rentang jam. Baris dengan jam awal yang sama akan
                        ditimpa.
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.6fr 1fr 1fr 1fr auto',
                            gap: 12,
                            alignItems: 'end',
                            marginBottom: 16,
                        }}
                    >
                        <div>
                            <span style={label}>Jenis hari</span>
                            <select
                                style={input}
                                value={rateForm.data.day_type}
                                onChange={(e) => rateForm.setData('day_type', e.target.value)}
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
                                onChange={(e) => rateForm.setData('hour_from', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>s.d. jam ke- (kosong = dst.)</span>
                            <input
                                style={input}
                                type="number"
                                min={1}
                                value={rateForm.data.hour_to}
                                onChange={(e) => rateForm.setData('hour_to', e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Pengali</span>
                            <input
                                style={input}
                                type="number"
                                step="0.25"
                                value={rateForm.data.multiplier}
                                onChange={(e) => rateForm.setData('multiplier', e.target.value)}
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

                    {grouped.map((group) => (
                        <div key={group.value} style={{ marginBottom: 14 }}>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginBottom: 6,
                                }}
                            >
                                {group.label}
                            </div>
                            <div style={{ overflowX: 'auto' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                    <thead>
                                        <tr>
                                            {['Jam Lembur', 'Pengali', ''].map((h, i) => (
                                                <th key={i} style={th}>
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.rows.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={3}
                                                    style={{ ...td, textAlign: 'center', color: C.faint }}
                                                >
                                                    Belum ada pengali untuk jenis hari ini.
                                                </td>
                                            </tr>
                                        ) : (
                                            group.rows.map((r) => (
                                                <tr key={r.id}>
                                                    <td style={td}>{bandLabel(r)}</td>
                                                    <td style={{ ...td, fontWeight: 600, color: C.navy }}>
                                                        {r.multiplier.toLocaleString('id-ID')}×
                                                    </td>
                                                    <td style={{ ...td, textAlign: 'right' }}>
                                                        <ActionBtn
                                                            icon="trash-2"
                                                            label="Hapus"
                                                            variant="danger"
                                                            onClick={() => delRate(r.id)}
                                                        />
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}
                </div>

                <div style={{ ...card, padding: 18 }}>
                    <div style={sectionTitle}>Basis Perhitungan per Master Gaji</div>
                    <div style={hint}>
                        Komponen yang dicentang di "Komponen Overtime" pada tiap Master Gaji. Gaji Pokok
                        wajib ikut; tambahkan tunjangan tetap sesuai kebijakan.
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr>
                                    {['Master Gaji', 'Komponen Basis', 'Total Basis'].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {basis.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} style={{ ...td, textAlign: 'center', color: C.faint }}>
                                            Belum ada Master Gaji.
                                        </td>
                                    </tr>
                                ) : (
                                    basis.map((b) => (
                                        <tr key={b.id}>
                                            <td style={{ ...td, fontWeight: 600, color: C.navy }}>
                                                {b.code}
                                                <div style={{ fontSize: 12, fontWeight: 400, color: C.muted }}>
                                                    {b.category ?? '—'}
                                                </div>
                                            </td>
                                            <td style={{ ...td, color: b.components.length ? C.text : C.red }}>
                                                {b.components.length
                                                    ? b.components.join(' + ')
                                                    : 'Belum diatur — basis jatuh ke Gaji Pokok saja'}
                                            </td>
                                            <td style={td}>{rupiah(b.total)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
