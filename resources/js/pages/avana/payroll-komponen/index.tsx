import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, ActionBtn, btnOut, btnP, C, card, rp } from '@/lib/avana';
import FormulaTab from './formula-tab';
import type {
    CalcType,
    Category,
    Component,
    Formula,
    Kpis,
    Option,
    TaxBpjsRow,
} from './types';

interface Props {
    components: Component[];
    formulas: Formula[];
    taxBpjs: TaxBpjsRow[];
    kpis: Kpis;
    componentOptions: Option[];
    formulaOptions: Option[];
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

const CALC_LABEL: Record<CalcType, { label: string; icon: string }> = {
    jumlah_tetap: { label: 'Jumlah Tetap', icon: 'calendar' },
    per_hari: { label: 'Per Hari', icon: 'calendar-days' },
    per_jam: { label: 'Per Jam', icon: 'clock' },
    persentase: { label: 'Persentase', icon: 'percent' },
    rumus: { label: 'Rumus', icon: 'calculator' },
};

const CAT_STYLE: Record<Category, { c: string; bg: string; label: string }> = {
    pendapatan: { c: '#15803D', bg: '#DCFCE7', label: 'Pendapatan' },
    potongan: { c: '#B91C1C', bg: '#FEE2E2', label: 'Potongan' },
    pajak: { c: '#1D4ED8', bg: '#DBEAFE', label: 'Pajak' },
    bpjs: { c: '#7C3AED', bg: '#EDE9FE', label: 'BPJS' },
};

const cell: CSSProperties = {
    fontSize: 13,
    color: C.text,
    padding: '12px 16px',
};
const cellName: CSSProperties = { ...cell };

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

interface CForm {
    id: number | null;
    code: string;
    name: string;
    group: string;
    tipe: CalcType;
    basis_value: string;
    payroll_formula_id: string;
    is_taxable: boolean;
    show_on_slip: boolean;
    [key: string]: string | number | boolean | null;
}

const emptyForm: CForm = {
    id: null,
    code: '',
    name: '',
    group: 'penerimaan',
    tipe: 'jumlah_tetap',
    basis_value: '',
    payroll_formula_id: '',
    is_taxable: true,
    show_on_slip: true,
};

export default function PayrollKomponen({
    components,
    formulas,
    taxBpjs,
    kpis,
    componentOptions,
    formulaOptions,
}: Props) {
    const { flash } = usePage<FlashProps>().props;
    const [tab, setTab] = useState<'komponen' | 'rumus' | 'pajak'>('komponen');
    const [cat, setCat] = useState<'semua' | Category>('semua');
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<
        'all' | 'active' | 'inactive'
    >('all');
    const [selected, setSelected] = useState<number | null>(null);
    const [modal, setModal] = useState(false);

    const form = useForm<CForm>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const counts = useMemo(
        () => ({
            semua: components.length,
            pendapatan: components.filter((c) => c.category === 'pendapatan')
                .length,
            potongan: components.filter((c) => c.category === 'potongan')
                .length,
            pajak: taxBpjs.filter((t) => t.category === 'pajak').length,
            bpjs: taxBpjs.filter((t) => t.category === 'bpjs').length,
        }),
        [components, taxBpjs],
    );

    const showingTax = cat === 'pajak' || cat === 'bpjs';

    const rows = useMemo(() => {
        const q = search.trim().toLowerCase();

        return components
            .filter((c) => cat === 'semua' || c.category === cat)
            .filter(
                (c) =>
                    statusFilter === 'all' ||
                    (statusFilter === 'active'
                        ? c.status === 'active'
                        : c.status !== 'active'),
            )
            .filter(
                (c) =>
                    !q ||
                    c.name.toLowerCase().includes(q) ||
                    (c.code ?? '').toLowerCase().includes(q),
            );
    }, [components, cat, search, statusFilter]);

    const taxRows = useMemo(
        () => taxBpjs.filter((t) => cat === 'semua' || t.category === cat),
        [taxBpjs, cat],
    );

    const detail = components.find((c) => c.id === selected) ?? null;

    const openCreate = () => {
        form.setData({ ...emptyForm });
        form.clearErrors();
        setModal(true);
    };
    const openEdit = (c: Component) => {
        form.setData({
            id: c.id,
            code: c.code ?? '',
            name: c.name,
            group: c.group,
            tipe: c.calc_type,
            basis_value: c.basis_value != null ? String(c.basis_value) : '',
            payroll_formula_id: c.payroll_formula_id
                ? String(c.payroll_formula_id)
                : '',
            is_taxable: c.is_taxable,
            show_on_slip: c.show_on_slip,
        });
        form.clearErrors();
        setModal(true);
    };

    const submit = () => {
        const tipe = form.data.tipe;
        const calc_basis =
            tipe === 'persentase'
                ? 'percentage'
                : tipe === 'per_hari'
                  ? 'per_present_day'
                  : tipe === 'per_jam'
                    ? 'per_overtime_hour'
                    : 'fixed';
        const payload = {
            code: form.data.code,
            name: form.data.name,
            group: form.data.group,
            calc_basis,
            basis_type: tipe === 'rumus' ? 'formula' : 'fixed',
            basis_value: form.data.basis_value,
            payroll_formula_id:
                tipe === 'rumus' ? form.data.payroll_formula_id : '',
            is_taxable: form.data.is_taxable,
            show_on_slip: form.data.show_on_slip,
        };
        const opts = {
            preserveScroll: true,
            onSuccess: () => setModal(false),
            onError: () => toast.error('Periksa kembali isian'),
        };

        if (form.data.id) {
            router.put(
                `/avana/payroll/komponen/component/${form.data.id}`,
                payload,
                opts,
            );
        } else {
            router.post('/avana/payroll/komponen/component', payload, opts);
        }
    };

    const exportCsv = () => {
        const head = ['Kode', 'Nama', 'Kategori', 'Tipe Perhitungan', 'Status'];
        const lines = rows.map((c) =>
            [
                c.code ?? '',
                c.name,
                c.category,
                (CALC_LABEL[c.calc_type] ?? CALC_LABEL.jumlah_tetap).label,
                c.status === 'active' ? 'Aktif' : 'Nonaktif',
            ]
                .map((v) => `"${String(v).replace(/"/g, '""')}"`)
                .join(','),
        );
        const blob = new Blob([[head.join(','), ...lines].join('\n')], {
            type: 'text/csv;charset=utf-8;',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'komponen-payroll.csv';
        a.click();
        URL.revokeObjectURL(url);
    };

    const toggle = (c: Component) =>
        router.post(
            `/avana/payroll/komponen/component/${c.id}/toggle`,
            {},
            { preserveScroll: true },
        );
    const remove = (c: Component) =>
        router.delete(`/avana/payroll/komponen/component/${c.id}`, {
            preserveScroll: true,
        });

    const catItem = (key: 'semua' | Category, name: string, dot?: string) => (
        <button
            key={key}
            onClick={() => setCat(key)}
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                width: '100%',
                padding: '10px 12px',
                borderRadius: 9,
                border: 'none',
                background: cat === key ? 'rgba(47,84,201,.08)' : 'transparent',
                color: cat === key ? C.primary : C.text,
                fontWeight: cat === key ? 600 : 500,
                fontSize: 13.5,
                cursor: 'pointer',
                textAlign: 'left',
            }}
        >
            <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                {dot ? (
                    <span
                        style={{
                            width: 8,
                            height: 8,
                            borderRadius: 100,
                            background: dot,
                        }}
                    />
                ) : (
                    <AIcon
                        name="layers"
                        size={15}
                        color={cat === key ? C.primary : C.faint}
                    />
                )}
                {name}
            </span>
            <span style={{ fontSize: 12, color: C.faint }}>
                {counts[key as keyof typeof counts]}
            </span>
        </button>
    );

    return (
        <>
            <Head title="Pengaturan Komponen Payroll" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 20,
                    }}
                >
                    <div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            Payroll · Komponen
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                            }}
                        >
                            Pengaturan Komponen Payroll
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola komponen pendapatan, potongan, dan pengaturan
                            perhitungan payroll perusahaan Anda.
                        </div>
                    </div>
                    {tab === 'komponen' && !showingTax && (
                        <button style={btnP} onClick={openCreate}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Komponen
                        </button>
                    )}
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(240px,1fr))',
                        gap: 16,
                        marginBottom: 20,
                    }}
                >
                    <Kpi
                        icon="wallet"
                        color={C.green}
                        title="Total Pendapatan"
                        value={kpis.pendapatan}
                        active={kpis.pendapatan_active}
                        hint="Komponen yang menambah penghasilan karyawan"
                    />
                    <Kpi
                        icon="arrow-down"
                        color={C.red}
                        title="Total Potongan"
                        value={kpis.potongan}
                        active={kpis.potongan_active}
                        hint="Komponen yang mengurangi penghasilan karyawan"
                    />
                    <Kpi
                        icon="percent"
                        color={C.primary}
                        title="Pajak & BPJS"
                        value={kpis.pajak_bpjs}
                        hint="Komponen pajak dan iuran wajib"
                    />
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        borderBottom: `1px solid ${C.border}`,
                        marginBottom: 18,
                    }}
                >
                    {(
                        [
                            ['komponen', 'Komponen'],
                            ['rumus', 'Rumus Perhitungan'],
                            ['pajak', 'Kategori Pajak & BPJS'],
                        ] as const
                    ).map(([key, lbl]) => (
                        <button
                            key={key}
                            onClick={() => {
                                setTab(key);

                                if (key === 'pajak') {
                                    setCat('pajak');
                                } else if (key === 'komponen') {
                                    setCat('semua');
                                }
                            }}
                            style={{
                                padding: '11px 14px',
                                background:
                                    tab === key
                                        ? 'rgba(47,84,201,.07)'
                                        : C.surface,
                                border: 'none',
                                borderRadius: '8px 8px 0 0',
                                borderBottom: `2px solid ${tab === key ? C.primary : 'transparent'}`,
                                marginBottom: -1,
                                fontSize: 13.5,
                                fontWeight: tab === key ? 600 : 500,
                                color: tab === key ? C.primary : C.muted,
                                cursor: 'pointer',
                            }}
                        >
                            {lbl}
                        </button>
                    ))}
                </div>

                {tab === 'rumus' && (
                    <FormulaTab
                        formulas={formulas}
                        componentOptions={componentOptions}
                    />
                )}

                {tab === 'pajak' && <TaxBpjsPanel taxBpjs={taxBpjs} />}

                {tab === 'komponen' && (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '210px minmax(0,1fr) 260px',
                            gap: 16,
                            alignItems: 'start',
                        }}
                    >
                        <div style={{ ...card, padding: 12 }}>
                            <div
                                style={{
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.navy,
                                    padding: '4px 8px 10px',
                                }}
                            >
                                Kategori Komponen
                            </div>
                            {catItem('semua', 'Semua Komponen')}
                            {catItem('pendapatan', 'Pendapatan', '#16A34A')}
                            {catItem('potongan', 'Potongan', '#DC2626')}
                            {catItem('pajak', 'Pajak', '#2F54C9')}
                            {catItem('bpjs', 'BPJS', '#7C3AED')}

                            <div
                                style={{
                                    marginTop: 14,
                                    padding: 12,
                                    borderRadius: 10,
                                    background: 'rgba(47,84,201,.05)',
                                    border: `1px solid rgba(47,84,201,.12)`,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 7,
                                        fontSize: 12.5,
                                        fontWeight: 600,
                                        color: C.primary,
                                        marginBottom: 6,
                                    }}
                                >
                                    <AIcon
                                        name="info"
                                        size={14}
                                        color={C.primary}
                                    />
                                    Informasi
                                </div>
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: C.muted,
                                        lineHeight: 1.5,
                                    }}
                                >
                                    Komponen payroll dipakai untuk perhitungan
                                    gaji karyawan. Pastikan pengaturan sesuai
                                    kebijakan perusahaan.
                                </div>
                            </div>
                        </div>

                        <div
                            style={{ ...card, padding: 0, overflow: 'hidden' }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: 12,
                                    flexWrap: 'wrap',
                                    padding: '14px 16px',
                                    borderBottom: `1px solid ${C.line}`,
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 15,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    Daftar Komponen
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            border: `1px solid ${C.border}`,
                                            borderRadius: 8,
                                            padding: '6px 10px',
                                            minWidth: 170,
                                        }}
                                    >
                                        <AIcon
                                            name="search"
                                            size={15}
                                            color={C.faint}
                                        />
                                        <input
                                            value={search}
                                            onChange={(e) =>
                                                setSearch(e.target.value)
                                            }
                                            placeholder="Cari komponen…"
                                            style={{
                                                border: 'none',
                                                outline: 'none',
                                                fontSize: 13,
                                                flex: 1,
                                                background: 'transparent',
                                                color: C.text,
                                            }}
                                        />
                                    </div>
                                    <select
                                        value={statusFilter}
                                        onChange={(e) =>
                                            setStatusFilter(
                                                e.target.value as
                                                    | 'all'
                                                    | 'active'
                                                    | 'inactive',
                                            )
                                        }
                                        style={{
                                            padding: '8px 10px',
                                            borderRadius: 8,
                                            border: `1px solid ${C.border}`,
                                            fontSize: 13,
                                            color: C.text,
                                            background: '#fff',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <option value="all">
                                            Semua Status
                                        </option>
                                        <option value="active">Aktif</option>
                                        <option value="inactive">
                                            Nonaktif
                                        </option>
                                    </select>
                                    <button
                                        onClick={exportCsv}
                                        style={{
                                            ...btnOut,
                                            height: 34,
                                            padding: '0 12px',
                                            fontSize: 12.5,
                                        }}
                                    >
                                        <AIcon name="upload" size={14} />
                                        Export
                                    </button>
                                </div>
                            </div>

                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 640,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            {[
                                                'Nama Komponen',
                                                'Kategori',
                                                'Tipe Perhitungan',
                                                'Status',
                                                'Aksi',
                                            ].map((h) => (
                                                <th
                                                    key={h}
                                                    style={{
                                                        textAlign: 'left',
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                        color: C.faint,
                                                        padding: '11px 16px',
                                                        textTransform:
                                                            'uppercase',
                                                        letterSpacing: '.03em',
                                                    }}
                                                >
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {showingTax
                                            ? taxRows.map((t) => (
                                                  <tr
                                                      key={`tax-${t.id}`}
                                                      style={{
                                                          borderTop: `1px solid ${C.line}`,
                                                      }}
                                                  >
                                                      <td style={cellName}>
                                                          <div
                                                              style={{
                                                                  fontWeight: 600,
                                                                  color: C.navy,
                                                              }}
                                                          >
                                                              {t.name}
                                                          </div>
                                                          <div
                                                              style={{
                                                                  fontSize: 11.5,
                                                                  color: C.faint,
                                                              }}
                                                          >
                                                              {t.code}
                                                          </div>
                                                      </td>
                                                      <td style={cell}>
                                                          <CatBadge
                                                              category={
                                                                  t.category
                                                              }
                                                          />
                                                      </td>
                                                      <td
                                                          style={{
                                                              ...cell,
                                                              color: C.muted,
                                                          }}
                                                      >
                                                          Otomatis (engine)
                                                      </td>
                                                      <td style={cell}>
                                                          <StatusPill
                                                              active={
                                                                  t.is_active
                                                              }
                                                          />
                                                      </td>
                                                      <td style={cell}>
                                                          <a
                                                              href="/avana/payroll/konfigurasi"
                                                              style={{
                                                                  fontSize: 12,
                                                                  color: C.primary,
                                                                  fontWeight: 600,
                                                                  textDecoration:
                                                                      'none',
                                                              }}
                                                          >
                                                              Kelola →
                                                          </a>
                                                      </td>
                                                  </tr>
                                              ))
                                            : rows.map((c) => {
                                                  const ct =
                                                      CALC_LABEL[c.calc_type] ??
                                                      CALC_LABEL.jumlah_tetap;
                                                  const active =
                                                      c.status === 'active';

                                                  return (
                                                      <tr
                                                          key={c.id}
                                                          onClick={() =>
                                                              setSelected(c.id)
                                                          }
                                                          style={{
                                                              borderTop: `1px solid ${C.line}`,
                                                              cursor: 'pointer',
                                                              background:
                                                                  selected ===
                                                                  c.id
                                                                      ? 'rgba(47,84,201,.04)'
                                                                      : 'transparent',
                                                          }}
                                                      >
                                                          <td style={cellName}>
                                                              <div
                                                                  style={{
                                                                      fontWeight: 600,
                                                                      color: C.navy,
                                                                  }}
                                                              >
                                                                  {c.name}
                                                              </div>
                                                              <div
                                                                  style={{
                                                                      fontSize: 11.5,
                                                                      color: C.faint,
                                                                  }}
                                                              >
                                                                  {c.code ??
                                                                      '—'}
                                                              </div>
                                                          </td>
                                                          <td style={cell}>
                                                              <CatBadge
                                                                  category={
                                                                      c.category
                                                                  }
                                                              />
                                                          </td>
                                                          <td style={cell}>
                                                              <span
                                                                  style={{
                                                                      display:
                                                                          'inline-flex',
                                                                      alignItems:
                                                                          'center',
                                                                      gap: 6,
                                                                      fontSize: 13,
                                                                      color: C.text,
                                                                  }}
                                                              >
                                                                  <AIcon
                                                                      name={
                                                                          ct.icon
                                                                      }
                                                                      size={14}
                                                                      color={
                                                                          C.muted
                                                                      }
                                                                  />
                                                                  {ct.label}
                                                              </span>
                                                          </td>
                                                          <td
                                                              style={cell}
                                                              onClick={(e) =>
                                                                  e.stopPropagation()
                                                              }
                                                          >
                                                              <Toggle
                                                                  on={active}
                                                                  onClick={() =>
                                                                      toggle(c)
                                                                  }
                                                              />
                                                          </td>
                                                          <td
                                                              style={cell}
                                                              onClick={(e) =>
                                                                  e.stopPropagation()
                                                              }
                                                          >
                                                              <div
                                                                  style={{
                                                                      display:
                                                                          'flex',
                                                                      gap: 6,
                                                                  }}
                                                              >
                                                                  <ActionBtn
                                                                      icon="pencil"
                                                                      label="Edit"
                                                                      variant="primary"
                                                                      onClick={() =>
                                                                          openEdit(
                                                                              c,
                                                                          )
                                                                      }
                                                                  />
                                                                  <ActionBtn
                                                                      icon="trash-2"
                                                                      label="Hapus"
                                                                      variant="danger"
                                                                      onClick={() =>
                                                                          remove(
                                                                              c,
                                                                          )
                                                                      }
                                                                  />
                                                              </div>
                                                          </td>
                                                      </tr>
                                                  );
                                              })}
                                        {!showingTax && rows.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    style={{
                                                        ...cell,
                                                        textAlign: 'center',
                                                        color: C.faint,
                                                        padding: '40px',
                                                    }}
                                                >
                                                    Tidak ada komponen.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div style={{ ...card, padding: 18 }}>
                            <div
                                style={{
                                    fontSize: 14.5,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginBottom: 4,
                                }}
                            >
                                Detail Komponen
                            </div>
                            {detail ? (
                                <ComponentDetail
                                    component={detail}
                                    calcLabel={
                                        (
                                            CALC_LABEL[detail.calc_type] ??
                                            CALC_LABEL.jumlah_tetap
                                        ).label
                                    }
                                />
                            ) : (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                    }}
                                >
                                    Pilih komponen untuk melihat detail
                                    informasi & aturan perhitungan.
                                </div>
                            )}
                            <Legend />
                        </div>
                    </div>
                )}
            </div>

            {modal && (
                <div
                    onClick={() => setModal(false)}
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
                                marginBottom: 18,
                            }}
                        >
                            {form.data.id ? 'Ubah Komponen' : 'Tambah Komponen'}
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 2fr',
                                gap: 12,
                                marginBottom: 14,
                            }}
                        >
                            <div>
                                <label style={label}>Kode</label>
                                <input
                                    style={input}
                                    placeholder="cth. TJ-MKN"
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label style={label}>Nama Komponen</label>
                                <input
                                    style={input}
                                    placeholder="cth. Tunjangan Makan"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 12,
                                marginBottom: 14,
                            }}
                        >
                            <div>
                                <label style={label}>Kategori</label>
                                <select
                                    style={input}
                                    value={form.data.group}
                                    onChange={(e) =>
                                        form.setData('group', e.target.value)
                                    }
                                >
                                    <option value="penerimaan">
                                        Pendapatan
                                    </option>
                                    <option value="potongan">Potongan</option>
                                </select>
                            </div>
                            <div>
                                <label style={label}>Tipe Perhitungan</label>
                                <select
                                    style={input}
                                    value={form.data.tipe}
                                    onChange={(e) =>
                                        form.setData(
                                            'tipe',
                                            e.target.value as CalcType,
                                        )
                                    }
                                >
                                    <option value="jumlah_tetap">
                                        Jumlah Tetap
                                    </option>
                                    <option value="per_hari">Per Hari</option>
                                    <option value="per_jam">Per Jam</option>
                                    <option value="persentase">
                                        Persentase
                                    </option>
                                    <option value="rumus">Rumus</option>
                                </select>
                            </div>
                        </div>

                        {form.data.tipe === 'rumus' ? (
                            <div style={{ marginBottom: 14 }}>
                                <label style={label}>Formula</label>
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
                                    <option value="">Pilih formula…</option>
                                    {formulaOptions.map((f) => (
                                        <option key={f.id} value={f.id}>
                                            {f.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : (
                            <div style={{ marginBottom: 14 }}>
                                <label style={label}>
                                    {form.data.tipe === 'persentase'
                                        ? 'Persentase (%)'
                                        : 'Nilai / Nominal (Rp)'}
                                </label>
                                <input
                                    type="number"
                                    min={0}
                                    style={input}
                                    value={form.data.basis_value}
                                    onChange={(e) =>
                                        form.setData(
                                            'basis_value',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0"
                                />
                            </div>
                        )}

                        <div
                            style={{
                                display: 'flex',
                                gap: 18,
                                marginBottom: 22,
                                flexWrap: 'wrap',
                            }}
                        >
                            <label
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    fontSize: 13,
                                    cursor: 'pointer',
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.is_taxable}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_taxable',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Masuk perhitungan PPh 21
                            </label>
                            <label
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    fontSize: 13,
                                    cursor: 'pointer',
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.show_on_slip}
                                    onChange={(e) =>
                                        form.setData(
                                            'show_on_slip',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Tampil di slip gaji
                            </label>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                            }}
                        >
                            <button
                                style={btnOut}
                                onClick={() => setModal(false)}
                            >
                                <AIcon name="x" size={15} color={C.text} />
                                Batal
                            </button>
                            <button
                                style={{ ...btnP, background: C.green }}
                                disabled={form.processing}
                                onClick={submit}
                            >
                                <AIcon name="save" size={15} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function Kpi({
    icon,
    color,
    title,
    value,
    active,
    hint,
}: {
    icon: string;
    color: string;
    title: string;
    value: number;
    active?: number;
    hint: string;
}) {
    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    marginBottom: 12,
                }}
            >
                <div
                    style={{
                        width: 38,
                        height: 38,
                        borderRadius: 10,
                        background: `${color}1a`,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <AIcon name={icon} size={18} color={color} />
                </div>
                <span style={{ fontSize: 13, color: C.muted, fontWeight: 500 }}>
                    {title}
                </span>
            </div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                <span style={{ fontSize: 26, fontWeight: 700, color: C.navy }}>
                    {value}
                </span>
                <span style={{ fontSize: 13, color: C.muted }}>Komponen</span>
                {active !== undefined && (
                    <span
                        style={{
                            fontSize: 11.5,
                            fontWeight: 600,
                            color: C.green,
                            background: 'rgba(22,163,74,.1)',
                            padding: '2px 8px',
                            borderRadius: 100,
                        }}
                    >
                        Aktif: {active}
                    </span>
                )}
            </div>
            <div style={{ fontSize: 12, color: C.faint, marginTop: 6 }}>
                {hint}
            </div>
        </div>
    );
}

function CatBadge({ category }: { category: Category }) {
    const s = CAT_STYLE[category];

    return (
        <span
            style={{
                fontSize: 11.5,
                fontWeight: 600,
                padding: '3px 10px',
                borderRadius: 6,
                color: s.c,
                background: s.bg,
            }}
        >
            {s.label}
        </span>
    );
}

function StatusPill({ active }: { active: boolean }) {
    return (
        <span
            style={{
                fontSize: 11.5,
                fontWeight: 600,
                padding: '3px 10px',
                borderRadius: 100,
                color: active ? C.green : C.muted,
                background: active
                    ? 'rgba(22,163,74,.1)'
                    : 'rgba(107,114,128,.12)',
            }}
        >
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

function Toggle({ on, onClick }: { on: boolean; onClick: () => void }) {
    return (
        <button
            onClick={onClick}
            title={on ? 'Nonaktifkan' : 'Aktifkan'}
            style={{
                width: 40,
                height: 22,
                borderRadius: 100,
                border: 'none',
                background: on ? C.primary : C.border,
                position: 'relative',
                cursor: 'pointer',
            }}
        >
            <span
                style={{
                    position: 'absolute',
                    top: 2,
                    left: on ? 20 : 2,
                    width: 18,
                    height: 18,
                    borderRadius: 100,
                    background: '#fff',
                    transition: '.15s',
                    boxShadow: '0 1px 2px rgba(0,0,0,.2)',
                }}
            />
        </button>
    );
}

const CALC_DESC: Record<CalcType, { desc: string; color: string }> = {
    jumlah_tetap: {
        desc: 'Nilai komponen dalam jumlah tetap',
        color: '#2F54C9',
    },
    per_hari: { desc: 'Nilai komponen dihitung per hari', color: '#2F54C9' },
    per_jam: {
        desc: 'Nilai komponen dihitung per jam lembur',
        color: '#0891B2',
    },
    persentase: {
        desc: 'Nilai komponen berdasarkan persentase',
        color: '#D97706',
    },
    rumus: { desc: 'Nilai komponen berdasarkan rumus', color: '#7C3AED' },
};

const sectionHead: CSSProperties = {
    fontSize: 12,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
    letterSpacing: '.04em',
    margin: '18px 0 12px',
};

function Legend() {
    return (
        <>
            <div style={{ ...sectionHead, marginTop: 6 }}>Tipe Perhitungan</div>
            {(
                Object.entries(CALC_LABEL) as [
                    CalcType,
                    { label: string; icon: string },
                ][]
            ).map(([k, v]) => (
                <div
                    key={k}
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 10,
                        marginBottom: 12,
                    }}
                >
                    <div
                        style={{
                            width: 30,
                            height: 30,
                            borderRadius: 8,
                            flex: 'none',
                            background: `${CALC_DESC[k].color}1a`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon
                            name={v.icon}
                            size={15}
                            color={CALC_DESC[k].color}
                        />
                    </div>
                    <div>
                        <div
                            style={{
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            {v.label}
                        </div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            {CALC_DESC[k].desc}
                        </div>
                    </div>
                </div>
            ))}

            <div
                style={{
                    borderTop: `1px solid ${C.line}`,
                    margin: '4px 0',
                }}
            />
            <div style={sectionHead}>Status Komponen</div>
            {[
                {
                    on: true,
                    label: 'Aktif',
                    desc: 'Komponen digunakan dalam perhitungan payroll',
                },
                {
                    on: false,
                    label: 'Nonaktif',
                    desc: 'Komponen tidak digunakan dalam perhitungan',
                },
            ].map((s) => (
                <div
                    key={s.label}
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 10,
                        marginBottom: 12,
                    }}
                >
                    <span
                        style={{
                            width: 34,
                            height: 20,
                            flex: 'none',
                            borderRadius: 100,
                            background: s.on ? C.primary : C.border,
                            position: 'relative',
                            marginTop: 1,
                        }}
                    >
                        <span
                            style={{
                                position: 'absolute',
                                top: 2,
                                left: s.on ? 16 : 2,
                                width: 16,
                                height: 16,
                                borderRadius: 100,
                                background: '#fff',
                            }}
                        />
                    </span>
                    <div>
                        <div
                            style={{
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            {s.label}
                        </div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            {s.desc}
                        </div>
                    </div>
                </div>
            ))}
        </>
    );
}

function ComponentDetail({
    component,
    calcLabel,
}: {
    component: Component;
    calcLabel: string;
}) {
    const rowsInfo: [string, string][] = [
        ['Kode', component.code ?? '—'],
        ['Kategori', CAT_STYLE[component.category].label],
        ['Tipe Perhitungan', calcLabel],
        [
            'Nilai',
            component.basis_value != null ? rp(component.basis_value) : '—',
        ],
        ['Kena PPh 21', component.is_taxable ? 'Ya' : 'Tidak'],
        ['Tampil di slip', component.show_on_slip ? 'Ya' : 'Tidak'],
        ['Status', component.status === 'active' ? 'Aktif' : 'Nonaktif'],
    ];

    return (
        <div style={{ marginTop: 10 }}>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 12,
                }}
            >
                {component.name}
            </div>
            {rowsInfo.map(([k, v]) => (
                <div
                    key={k}
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        fontSize: 12.5,
                        padding: '7px 0',
                        borderBottom: `1px solid ${C.line}`,
                    }}
                >
                    <span style={{ color: C.muted }}>{k}</span>
                    <span style={{ color: C.text, fontWeight: 500 }}>{v}</span>
                </div>
            ))}
            {component.values.length > 0 && (
                <div style={{ marginTop: 14 }}>
                    <div
                        style={{
                            fontSize: 12,
                            fontWeight: 600,
                            color: C.faint,
                            textTransform: 'uppercase',
                            marginBottom: 8,
                        }}
                    >
                        Nilai per Kategori ({component.values.length})
                    </div>
                    {component.values.slice(0, 5).map((v) => (
                        <div
                            key={v.id}
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                fontSize: 12,
                                padding: '4px 0',
                            }}
                        >
                            <span style={{ color: C.muted }}>
                                {v.kategori ??
                                    v.position ??
                                    v.job_level ??
                                    v.employment_status ??
                                    'Umum'}
                            </span>
                            <span style={{ color: C.text }}>{rp(v.value)}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function TaxBpjsPanel({ taxBpjs }: { taxBpjs: TaxBpjsRow[] }) {
    return (
        <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.line}`,
                    flexWrap: 'wrap',
                    gap: 10,
                }}
            >
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    Kategori Pajak & BPJS
                </div>
                <a
                    href="/avana/payroll/konfigurasi"
                    style={{ ...btnOut, textDecoration: 'none' }}
                >
                    <AIcon name="settings" size={15} />
                    Kelola BPJS & Pajak
                </a>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            {['Nama', 'Kode', 'Kategori', 'Status'].map((h) => (
                                <th
                                    key={h}
                                    style={{
                                        textAlign: 'left',
                                        fontSize: 11.5,
                                        fontWeight: 600,
                                        color: C.faint,
                                        padding: '11px 18px',
                                        textTransform: 'uppercase',
                                    }}
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {taxBpjs.map((t) => (
                            <tr
                                key={`${t.category}-${t.id}`}
                                style={{ borderTop: `1px solid ${C.line}` }}
                            >
                                <td
                                    style={{
                                        ...cell,
                                        fontWeight: 600,
                                        color: C.navy,
                                        padding: '12px 18px',
                                    }}
                                >
                                    {t.name}
                                </td>
                                <td
                                    style={{
                                        ...cell,
                                        color: C.muted,
                                        padding: '12px 18px',
                                    }}
                                >
                                    {t.code}
                                </td>
                                <td style={{ ...cell, padding: '12px 18px' }}>
                                    <CatBadge category={t.category} />
                                </td>
                                <td style={{ ...cell, padding: '12px 18px' }}>
                                    <StatusPill active={t.is_active} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
