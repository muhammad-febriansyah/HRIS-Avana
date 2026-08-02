import { useRef, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import Pph21TerController from '@/actions/App/Http/Controllers/Avana/Pph21TerController';
import { ActionBtn, AIcon, C, card } from '@/lib/avana';

interface Bracket {
    id: number;
    income_min: number;
    income_max: number | null;
    rate: number;
}

interface CategoryTable {
    code: string;
    label: string;
    effective_from: string | null;
    source: string | null;
    brackets: Bracket[];
    issues: string[];
}

interface MapRow {
    ptkp_status: string;
    category: string;
    effective_from: string | null;
}

interface Props {
    asOf: string;
    canManage: boolean;
    categories: CategoryTable[];
    categoryMap: MapRow[];
    versions: {
        effective_start_date: string;
        categories: string;
        brackets: number;
    }[];
    ptkpStatuses: string[];
    categoryOptions: { value: string; label: string }[];
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
    padding: '10px 12px',
    borderBottom: `1px solid ${C.line}`,
};
const td: React.CSSProperties = {
    fontSize: 13,
    color: C.text,
    padding: '9px 12px',
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

const rupiah = (n: number) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
const percent = (n: number) =>
    (n * 100).toLocaleString('id-ID', { maximumFractionDigits: 4 }) + '%';

export default function PayrollTer({
    asOf,
    canManage,
    categories,
    categoryMap,
    versions,
    ptkpStatuses,
    categoryOptions,
}: Props) {
    const [active, setActive] = useState(categories[0]?.code ?? 'A');
    const fileRef = useRef<HTMLInputElement>(null);

    const importForm = useForm<{
        file: File | null;
        effective_start_date: string;
        source: string;
    }>({
        file: null,
        effective_start_date: '',
        source: '',
    });

    const mapForm = useForm({
        ptkp_status: ptkpStatuses[0] ?? 'TK/0',
        category: 'A',
        effective_start_date: '',
    });

    const table = categories.find((c) => c.code === active);

    const viewAt = (date: string) =>
        router.get(
            Pph21TerController.index().url,
            { as_of: date },
            { preserveScroll: true, preserveState: true },
        );

    const submitImport = () => {
        if (!importForm.data.file) {
            toast.error('Pilih berkas .xlsx dulu');

            return;
        }

        importForm.post(Pph21TerController.import().url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Tarif TER diperbarui dari berkas');
                importForm.reset('file');

                if (fileRef.current) {
                    fileRef.current.value = '';
                }
            },
            onError: (errors) =>
                toast.error(errors.file ?? 'Impor gagal — periksa berkas dan tanggal berlaku'),
        });
    };

    const resetToStatutory = () => {
        const date = importForm.data.effective_start_date;

        if (!date) {
            toast.error('Isi tanggal berlaku dulu');

            return;
        }

        router.post(
            Pph21TerController.reset().url,
            { effective_start_date: date },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Tarif dikembalikan ke PP 58/2023'),
            },
        );
    };

    const saveBracket = (bracket: Bracket, patch: Partial<Bracket>) =>
        router.put(
            Pph21TerController.updateBracket(bracket.id).url,
            {
                income_min: patch.income_min ?? bracket.income_min,
                income_max:
                    patch.income_max === undefined ? bracket.income_max : patch.income_max,
                rate: patch.rate ?? bracket.rate,
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Bracket diperbarui'),
                onError: (errors) =>
                    toast.error(
                        errors.rate ?? errors.income_max ?? 'Bracket gagal disimpan',
                    ),
            },
        );

    const deleteBracket = (id: number) =>
        router.delete(Pph21TerController.destroyBracket(id).url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Bracket dihapus'),
        });

    const saveMap = () =>
        mapForm.post(Pph21TerController.updateCategoryMap().url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Kategori PTKP diperbarui'),
            onError: () => toast.error('Periksa isian kategori PTKP'),
        });

    return (
        <>
            <Head title="Tarif TER PPh 21" />
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
                    <span style={{ color: C.muted }}>Tarif TER PPh 21</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 4px' }}>
                    Tarif TER PPh 21
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Tabel Tarif Efektif Rata-rata (PP 58/2023 &amp; PMK 168/2023) sebagai master
                    data. Setiap versi punya tanggal berlaku, jadi payroll bulan lama tetap
                    memakai tarif yang berlaku saat itu.
                </div>

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '200px 1fr',
                            gap: 18,
                            alignItems: 'start',
                        }}
                    >
                        <div>
                            <span style={label}>Lihat tarif per tanggal</span>
                            <input
                                style={input}
                                type="date"
                                value={asOf}
                                onChange={(e) => viewAt(e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Versi terbit</span>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                {versions.length === 0 ? (
                                    <span style={{ fontSize: 13, color: C.faint }}>
                                        Belum ada versi tersimpan.
                                    </span>
                                ) : (
                                    versions.map((v) => (
                                        <button
                                            key={v.effective_start_date}
                                            onClick={() => viewAt(v.effective_start_date)}
                                            style={{
                                                padding: '7px 12px',
                                                borderRadius: 999,
                                                border: `1px solid ${
                                                    v.effective_start_date === asOf
                                                        ? C.primary
                                                        : C.line
                                                }`,
                                                background: '#fff',
                                                fontSize: 12.5,
                                                color: C.text,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            {v.effective_start_date} · {v.categories} ·{' '}
                                            {v.brackets} bracket
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {canManage && (
                    <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, color: C.navy, marginBottom: 4 }}>
                            Terbitkan Tarif Baru
                        </div>
                        <div style={{ fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                            Unggah workbook resmi (sheet TER_A / TER_B / TER_C / Kategori_PTKP).
                            Tarif yang berlaku sebelumnya otomatis ditutup sehari sebelum tanggal
                            berlaku yang baru.
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1.6fr 1fr 1.2fr auto auto',
                                gap: 12,
                                alignItems: 'end',
                            }}
                        >
                            <div>
                                <span style={label}>Berkas .xlsx</span>
                                <input
                                    ref={fileRef}
                                    style={input}
                                    type="file"
                                    accept=".xlsx"
                                    onChange={(e) =>
                                        importForm.setData('file', e.target.files?.[0] ?? null)
                                    }
                                />
                            </div>
                            <div>
                                <span style={label}>Berlaku mulai</span>
                                <input
                                    style={input}
                                    type="date"
                                    value={importForm.data.effective_start_date}
                                    onChange={(e) =>
                                        importForm.setData('effective_start_date', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <span style={label}>Dasar hukum (opsional)</span>
                                <input
                                    style={input}
                                    placeholder="mis. PMK 168/2023"
                                    value={importForm.data.source}
                                    onChange={(e) => importForm.setData('source', e.target.value)}
                                />
                            </div>
                            <button
                                style={{ ...primaryBtn, background: C.green }}
                                disabled={importForm.processing}
                                onClick={submitImport}
                            >
                                <AIcon name="upload" size={15} color="#fff" />
                                Impor
                            </button>
                            <ActionBtn
                                icon="rotate-ccw"
                                label="Reset ke PP 58/2023"
                                onClick={resetToStatutory}
                            />
                        </div>
                        {importForm.errors.file && (
                            <div style={{ fontSize: 12.5, color: C.red, marginTop: 10 }}>
                                {importForm.errors.file}
                            </div>
                        )}
                    </div>
                )}

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div style={{ display: 'flex', gap: 8, marginBottom: 14, flexWrap: 'wrap' }}>
                        {categories.map((c) => (
                            <button
                                key={c.code}
                                onClick={() => setActive(c.code)}
                                style={{
                                    padding: '8px 14px',
                                    borderRadius: 8,
                                    border: `1px solid ${active === c.code ? C.primary : C.line}`,
                                    background: active === c.code ? C.primary : '#fff',
                                    color: active === c.code ? '#fff' : C.text,
                                    fontSize: 13,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                }}
                            >
                                {c.code}
                                <span
                                    style={{
                                        marginLeft: 7,
                                        fontSize: 11.5,
                                        fontWeight: 400,
                                        opacity: 0.8,
                                    }}
                                >
                                    {c.brackets.length}
                                </span>
                            </button>
                        ))}
                    </div>

                    {table && (
                        <>
                            <div style={{ fontSize: 13, color: C.muted, marginBottom: 4 }}>
                                {table.label}
                            </div>
                            <div style={{ fontSize: 12.5, color: C.faint, marginBottom: 12 }}>
                                {table.effective_from
                                    ? `Berlaku sejak ${table.effective_from}`
                                    : 'Memakai tabel bawaan PP 58/2023'}
                                {table.source ? ` · ${table.source}` : ''}
                            </div>

                            {table.issues.length > 0 && (
                                <div
                                    style={{
                                        border: `1px solid ${C.amber}`,
                                        background: '#FFFBEB',
                                        borderRadius: 8,
                                        padding: '10px 12px',
                                        marginBottom: 12,
                                    }}
                                >
                                    {table.issues.map((issue, i) => (
                                        <div
                                            key={i}
                                            style={{ fontSize: 12.5, color: C.amber, fontWeight: 500 }}
                                        >
                                            {issue}
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div style={{ overflowX: 'auto', maxHeight: 460, overflowY: 'auto' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                    <thead>
                                        <tr>
                                            {['#', 'Batas Bawah (>)', 'Batas Atas (s.d.)', 'Tarif', ''].map(
                                                (h, i) => (
                                                    <th key={i} style={th}>
                                                        {h}
                                                    </th>
                                                ),
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {table.brackets.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    style={{ ...td, textAlign: 'center', color: C.faint }}
                                                >
                                                    Belum ada bracket untuk kategori ini.
                                                </td>
                                            </tr>
                                        ) : (
                                            table.brackets.map((b, i) => (
                                                <tr key={b.id}>
                                                    <td style={{ ...td, color: C.faint, width: 44 }}>
                                                        {i + 1}
                                                    </td>
                                                    <td style={td}>{rupiah(b.income_min)}</td>
                                                    <td style={td}>
                                                        {b.income_max === null ? (
                                                            <span style={{ color: C.muted }}>
                                                                ke atas
                                                            </span>
                                                        ) : (
                                                            rupiah(b.income_max)
                                                        )}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                            width: 150,
                                                        }}
                                                    >
                                                        {canManage ? (
                                                            <>
                                                                <input
                                                                    style={{ ...input, width: 110 }}
                                                                    type="number"
                                                                    step="0.000001"
                                                                    defaultValue={b.rate}
                                                                    onBlur={(e) => {
                                                                        const next = Number(e.target.value);

                                                                        if (next !== b.rate) {
                                                                            saveBracket(b, { rate: next });
                                                                        }
                                                                    }}
                                                                />
                                                                <div
                                                                    style={{
                                                                        fontSize: 11.5,
                                                                        fontWeight: 400,
                                                                        color: C.muted,
                                                                        marginTop: 3,
                                                                    }}
                                                                >
                                                                    {percent(b.rate)}
                                                                </div>
                                                            </>
                                                        ) : (
                                                            percent(b.rate)
                                                        )}
                                                    </td>
                                                    <td style={{ ...td, textAlign: 'right' }}>
                                                        {canManage && (
                                                            <ActionBtn
                                                                icon="trash-2"
                                                                label="Hapus"
                                                                variant="danger"
                                                                onClick={() => deleteBracket(b.id)}
                                                            />
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </div>

                <div style={{ ...card, padding: 18 }}>
                    <div style={{ fontSize: 14, fontWeight: 600, color: C.navy, marginBottom: 4 }}>
                        Kategori PTKP
                    </div>
                    <div style={{ fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                        Status PTKP mana membaca tabel yang mana. Diatur PMK yang sama, jadi ikut
                        bisa diubah tanpa rilis.
                    </div>

                    {canManage && (
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr 1fr auto',
                                gap: 12,
                                alignItems: 'end',
                                marginBottom: 16,
                            }}
                        >
                            <div>
                                <span style={label}>Status PTKP</span>
                                <select
                                    style={input}
                                    value={mapForm.data.ptkp_status}
                                    onChange={(e) => mapForm.setData('ptkp_status', e.target.value)}
                                >
                                    {ptkpStatuses.map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <span style={label}>Kategori</span>
                                <select
                                    style={input}
                                    value={mapForm.data.category}
                                    onChange={(e) => mapForm.setData('category', e.target.value)}
                                >
                                    {categoryOptions
                                        .filter((o) => o.value !== 'HARIAN')
                                        .map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                </select>
                            </div>
                            <div>
                                <span style={label}>Berlaku mulai</span>
                                <input
                                    style={input}
                                    type="date"
                                    value={mapForm.data.effective_start_date}
                                    onChange={(e) =>
                                        mapForm.setData('effective_start_date', e.target.value)
                                    }
                                />
                            </div>
                            <button
                                style={{ ...primaryBtn, background: C.green }}
                                disabled={mapForm.processing}
                                onClick={saveMap}
                            >
                                <AIcon name="save" size={15} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    )}

                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr>
                                    {['Status PTKP', 'Kategori TER', 'Berlaku sejak'].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {categoryMap.map((row) => (
                                    <tr key={row.ptkp_status}>
                                        <td style={{ ...td, fontWeight: 600, color: C.navy }}>
                                            {row.ptkp_status}
                                        </td>
                                        <td style={td}>{row.category}</td>
                                        <td style={{ ...td, color: C.muted }}>
                                            {row.effective_from ?? 'bawaan'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
