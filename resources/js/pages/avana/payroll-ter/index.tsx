import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import Pph21TerController from '@/actions/App/Http/Controllers/Avana/Pph21TerController';
import { DatePicker } from '@/components/avana/date-picker';
import { ActionBtn, AIcon, C, card, RupiahInput } from '@/lib/avana';

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
    change_reason: string | null;
    brackets: Bracket[];
    issues: string[];
}

interface MapRow {
    ptkp_status: string;
    category: string;
    effective_from: string | null;
}

interface ImportPreview {
    token: string;
    checksum: string;
    effective_start_date: string;
    source: string;
    reason: string;
    file_name: string;
    blockers: string[];
    categories: {
        code: string;
        label: string;
        current_brackets: number;
        incoming_brackets: number;
        changed: boolean;
    }[];
    category_map: {
        ptkp_status: string;
        current: string | null;
        incoming: string | null;
        changed: boolean;
    }[];
    sheets: string[];
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
    preview: ImportPreview | null;
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

/** A change to the tariff always answers "sejak kapan" and "kenapa". */
interface ChangeIntent {
    effective_start_date: string;
    reason: string;
}

/**
 * Modal shell for the risky actions: publishing, resetting, correcting and
 * deleting all pass through one, so none of them can fire from a stray blur or
 * a single click.
 */
function Modal({
    title,
    description,
    children,
    onClose,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
    onClose: () => void;
}) {
    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(14,26,58,.45)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 60,
                padding: 20,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    ...card,
                    padding: 20,
                    width: 'min(520px, 100%)',
                    maxHeight: '86vh',
                    overflowY: 'auto',
                }}
            >
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 4,
                    }}
                >
                    {title}
                </div>
                {description && (
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.faint,
                            marginBottom: 14,
                            lineHeight: 1.55,
                        }}
                    >
                        {description}
                    </div>
                )}
                {children}
            </div>
        </div>
    );
}

export default function PayrollTer({
    asOf,
    canManage,
    categories,
    categoryMap,
    versions,
    ptkpStatuses,
    categoryOptions,
    preview,
}: Props) {
    const [active, setActive] = useState(categories[0]?.code ?? 'A');
    // "Kalkulator PPh21 Bulanan (TER)" from the workbook: pick a PTKP status
    // and a monthly gross, and the category, rate and withholding follow.
    const [calcName, setCalcName] = useState('');
    const [calcPtkp, setCalcPtkp] = useState(ptkpStatuses[0] ?? 'TK/0');
    const [calcGross, setCalcGross] = useState('');
    const fileRef = useRef<HTMLInputElement>(null);

    const [resetOpen, setResetOpen] = useState(false);
    const [editing, setEditing] = useState<Bracket | null>(null);
    const [removing, setRemoving] = useState<Bracket | null>(null);

    const importForm = useForm<{
        file: File | null;
        effective_start_date: string;
        source: string;
        reason: string;
        preview_token: string;
    }>({
        file: null,
        effective_start_date: '',
        source: '',
        reason: '',
        preview_token: '',
    });

    const resetForm = useForm<ChangeIntent>({
        effective_start_date: '',
        reason: '',
    });

    const bracketForm = useForm<
        ChangeIntent & {
            income_min: number;
            income_max: number | null;
            rate: number;
        }
    >({
        income_min: 0,
        income_max: null,
        rate: 0,
        effective_start_date: '',
        reason: '',
    });

    const removeForm = useForm<ChangeIntent>({
        effective_start_date: '',
        reason: '',
    });

    const mapForm = useForm({
        ptkp_status: ptkpStatuses[0] ?? 'TK/0',
        category: 'A',
        effective_start_date: '',
        reason: '',
    });

    const table = categories.find((c) => c.code === active);

    // INDEX/MATCH over Kategori_PTKP, then over the bracket table — the same
    // two lookups the workbook does.
    const calcCategory =
        categoryMap.find((row) => row.ptkp_status === calcPtkp)?.category ??
        'A';
    const calcGrossValue = Number(calcGross.replace(/[^\d]/g, '')) || 0;
    const calcRate =
        categories
            .find((c) => c.code === calcCategory)
            ?.brackets.find(
                (b) => b.income_max === null || calcGrossValue <= b.income_max,
            )?.rate ?? 0;
    const calcTax = Math.round(calcGrossValue * calcRate);

    const viewAt = (date: string) =>
        router.get(
            Pph21TerController.index().url,
            { as_of: date },
            { preserveScroll: true, preserveState: true },
        );

    // The preview on screen belongs to what is in the form right now; changing
    // the date, the reason or the file makes it stale, and publishing then is
    // refused server-side too.
    const previewMatches =
        preview !== null &&
        preview.effective_start_date === importForm.data.effective_start_date &&
        preview.source === importForm.data.source &&
        preview.reason === importForm.data.reason;

    const submitPreview = () => {
        if (!importForm.data.file) {
            toast.error('Pilih berkas .xlsx dulu');

            return;
        }

        importForm.post(Pph21TerController.preview().url, {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () =>
                toast.success('Berkas valid — periksa pratinjau di bawah'),
            onError: (errors) =>
                toast.error(
                    errors.file ??
                        errors.reason ??
                        errors.effective_start_date ??
                        'Pratinjau gagal',
                ),
        });
    };

    const publishImport = () => {
        if (!preview || !previewMatches) {
            return;
        }

        importForm.transform((data) => ({
            ...data,
            preview_token: preview.token,
        }));
        importForm.post(Pph21TerController.import().url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Tarif TER terbit sebagai versi baru');
                importForm.reset();

                if (fileRef.current) {
                    fileRef.current.value = '';
                }
            },
            onError: (errors: Record<string, string>) =>
                toast.error(
                    errors.preview_token ??
                        errors.file ??
                        errors.effective_start_date ??
                        'Publikasi gagal',
                ),
        });
    };

    const submitReset = () =>
        resetForm.post(Pph21TerController.reset().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    'Tarif dikembalikan ke PP 58/2023 sebagai versi baru',
                );
                setResetOpen(false);
                resetForm.reset();
            },
            onError: (errors) =>
                toast.error(
                    errors.effective_start_date ??
                        errors.reason ??
                        'Reset gagal',
                ),
        });

    const openEditor = (bracket: Bracket) => {
        bracketForm.setData({
            income_min: bracket.income_min,
            income_max: bracket.income_max,
            rate: bracket.rate,
            effective_start_date: '',
            reason: '',
        });
        setEditing(bracket);
    };

    const submitBracket = () => {
        if (!editing) {
            return;
        }

        bracketForm.put(Pph21TerController.updateBracket(editing.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    'Versi baru terbit dengan bracket yang dikoreksi',
                );
                setEditing(null);
            },
            onError: (errors) =>
                toast.error(
                    errors.rate ??
                        errors.income_max ??
                        errors.effective_start_date ??
                        errors.reason ??
                        'Koreksi gagal',
                ),
        });
    };

    const submitRemove = () => {
        if (!removing) {
            return;
        }

        removeForm.delete(Pph21TerController.destroyBracket(removing.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Versi baru terbit tanpa bracket tersebut');
                setRemoving(null);
                removeForm.reset();
            },
            onError: (errors) =>
                toast.error(
                    errors.effective_start_date ??
                        errors.reason ??
                        'Bracket tidak bisa dihapus',
                ),
        });
    };

    const saveMap = () =>
        mapForm.post(Pph21TerController.updateCategoryMap().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Kategori PTKP terbit sebagai versi baru');
                mapForm.reset('reason');
            },
            onError: (errors) =>
                toast.error(
                    errors.effective_start_date ??
                        errors.reason ??
                        'Periksa isian kategori PTKP',
                ),
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
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Tarif TER PPh 21
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Tabel Tarif Efektif Rata-rata (PP 58/2023 &amp; PMK
                    168/2023) sebagai master data. Versi yang sudah terbit tidak
                    bisa diubah: setiap koreksi terbit sebagai versi baru dengan
                    tanggal berlaku sendiri, jadi payroll bulan lama tetap
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
                            <DatePicker
                                value={asOf}
                                onChange={viewAt}
                                width="100%"
                            />
                        </div>
                        <div>
                            <span style={label}>Versi terbit</span>
                            <div
                                style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 8,
                                }}
                            >
                                {versions.length === 0 ? (
                                    <span
                                        style={{ fontSize: 13, color: C.faint }}
                                    >
                                        Belum ada versi tersimpan.
                                    </span>
                                ) : (
                                    versions.map((v) => (
                                        <button
                                            key={v.effective_start_date}
                                            onClick={() =>
                                                viewAt(v.effective_start_date)
                                            }
                                            style={{
                                                padding: '7px 12px',
                                                borderRadius: 999,
                                                border: `1px solid ${
                                                    v.effective_start_date ===
                                                    asOf
                                                        ? C.primary
                                                        : C.line
                                                }`,
                                                background: '#fff',
                                                fontSize: 12.5,
                                                color: C.text,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            {v.effective_start_date} ·{' '}
                                            {v.categories} · {v.brackets}{' '}
                                            bracket
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {canManage && (
                    <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Terbitkan Tarif Baru
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 14,
                            }}
                        >
                            Unggah workbook resmi (sheet TER_A / TER_B / TER_C /
                            TER Harian / Kategori_PTKP). Berkas divalidasi dan
                            dibandingkan dulu lewat pratinjau; publikasi baru
                            terbuka setelah pratinjau tidak bermasalah.
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    '1.6fr 1fr 1.2fr auto auto',
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
                                        importForm.setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <span style={label}>Berlaku mulai</span>
                                <DatePicker
                                    value={importForm.data.effective_start_date}
                                    onChange={(v) =>
                                        importForm.setData(
                                            'effective_start_date',
                                            v,
                                        )
                                    }
                                    placeholder="Pilih tanggal"
                                    width="100%"
                                />
                            </div>
                            <div>
                                <span style={label}>Dasar hukum</span>
                                <input
                                    style={input}
                                    placeholder="mis. PMK 168/2023"
                                    value={importForm.data.source}
                                    onChange={(e) =>
                                        importForm.setData(
                                            'source',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <button
                                style={primaryBtn}
                                disabled={importForm.processing}
                                onClick={submitPreview}
                            >
                                <AIcon name="search" size={15} color="#fff" />
                                Pratinjau
                            </button>
                            <ActionBtn
                                icon="rotate-ccw"
                                label="Reset ke PP 58/2023"
                                onClick={() => setResetOpen(true)}
                            />
                        </div>
                        <div style={{ marginTop: 12 }}>
                            <span style={label}>
                                Alasan perubahan (wajib, min. 10 karakter)
                            </span>
                            <input
                                style={input}
                                placeholder="mis. Penyesuaian tarif TER sesuai PMK terbaru per 1 Januari"
                                value={importForm.data.reason}
                                onChange={(e) =>
                                    importForm.setData('reason', e.target.value)
                                }
                            />
                        </div>
                        {(importForm.errors.file ||
                            importForm.errors.reason ||
                            importForm.errors.effective_start_date ||
                            importForm.errors.source) && (
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.red,
                                    marginTop: 10,
                                }}
                            >
                                {importForm.errors.file ??
                                    importForm.errors.reason ??
                                    importForm.errors.effective_start_date ??
                                    importForm.errors.source}
                            </div>
                        )}

                        {preview && (
                            <div
                                style={{
                                    marginTop: 16,
                                    border: `1px solid ${previewMatches ? C.line : C.amber}`,
                                    borderRadius: 10,
                                    padding: 16,
                                    background: C.surface,
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        color: C.navy,
                                        marginBottom: 8,
                                    }}
                                >
                                    Pratinjau · {preview.file_name}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        marginBottom: 12,
                                    }}
                                >
                                    Berlaku mulai {preview.effective_start_date}{' '}
                                    · {preview.source} · checksum{' '}
                                    {preview.checksum.slice(0, 12)}… · sheet
                                    terbaca: {preview.sheets.join(', ')}
                                </div>

                                {!previewMatches && (
                                    <div
                                        style={{
                                            border: `1px solid ${C.amber}`,
                                            background: '#FFFBEB',
                                            borderRadius: 8,
                                            padding: '10px 12px',
                                            marginBottom: 12,
                                            fontSize: 12.5,
                                            color: C.amber,
                                            fontWeight: 500,
                                        }}
                                    >
                                        Isian berubah setelah pratinjau.
                                        Jalankan pratinjau lagi sebelum
                                        menerbitkan.
                                    </div>
                                )}

                                {preview.blockers.length > 0 && (
                                    <div
                                        style={{
                                            border: `1px solid ${C.red}`,
                                            background: '#FEF2F2',
                                            borderRadius: 8,
                                            padding: '10px 12px',
                                            marginBottom: 12,
                                        }}
                                    >
                                        {preview.blockers.map((issue, i) => (
                                            <div
                                                key={i}
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.red,
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {issue}
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div
                                    style={{
                                        overflowX: 'auto',
                                        marginBottom: 12,
                                    }}
                                >
                                    <table
                                        style={{
                                            width: '100%',
                                            borderCollapse: 'collapse',
                                        }}
                                    >
                                        <thead>
                                            <tr>
                                                {[
                                                    'Kategori',
                                                    'Bracket sekarang',
                                                    'Bracket baru',
                                                    'Status',
                                                ].map((h, i) => (
                                                    <th key={i} style={th}>
                                                        {h}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {preview.categories.map((row) => (
                                                <tr key={row.code}>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {row.code}
                                                    </td>
                                                    <td style={td}>
                                                        {row.current_brackets}
                                                    </td>
                                                    <td style={td}>
                                                        {row.incoming_brackets}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            color: row.changed
                                                                ? C.amber
                                                                : C.muted,
                                                            fontWeight:
                                                                row.changed
                                                                    ? 600
                                                                    : 400,
                                                        }}
                                                    >
                                                        {row.changed
                                                            ? 'Berubah'
                                                            : 'Sama'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div
                                    style={{
                                        overflowX: 'auto',
                                        marginBottom: 14,
                                    }}
                                >
                                    <table
                                        style={{
                                            width: '100%',
                                            borderCollapse: 'collapse',
                                        }}
                                    >
                                        <thead>
                                            <tr>
                                                {[
                                                    'Status PTKP',
                                                    'Kategori sekarang',
                                                    'Kategori baru',
                                                ].map((h, i) => (
                                                    <th key={i} style={th}>
                                                        {h}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {preview.category_map.map((row) => (
                                                <tr key={row.ptkp_status}>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {row.ptkp_status}
                                                    </td>
                                                    <td style={td}>
                                                        {row.current ?? '—'}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            color: row.changed
                                                                ? C.amber
                                                                : C.text,
                                                            fontWeight:
                                                                row.changed
                                                                    ? 600
                                                                    : 400,
                                                        }}
                                                    >
                                                        {row.incoming ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <button
                                    style={{
                                        ...primaryBtn,
                                        background:
                                            preview.blockers.length > 0 ||
                                            !previewMatches
                                                ? C.faint
                                                : C.green,
                                        cursor:
                                            preview.blockers.length > 0 ||
                                            !previewMatches
                                                ? 'not-allowed'
                                                : 'pointer',
                                    }}
                                    disabled={
                                        importForm.processing ||
                                        preview.blockers.length > 0 ||
                                        !previewMatches
                                    }
                                    onClick={publishImport}
                                >
                                    <AIcon
                                        name="upload"
                                        size={15}
                                        color="#fff"
                                    />
                                    Terbitkan versi ini
                                </button>
                            </div>
                        )}
                    </div>
                )}

                <div style={{ ...card, padding: 18, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            gap: 8,
                            marginBottom: 14,
                            flexWrap: 'wrap',
                        }}
                    >
                        {categories.map((c) => (
                            <button
                                key={c.code}
                                onClick={() => setActive(c.code)}
                                style={{
                                    padding: '8px 14px',
                                    borderRadius: 8,
                                    border: `1px solid ${active === c.code ? C.primary : C.line}`,
                                    background:
                                        active === c.code ? C.primary : '#fff',
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
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginBottom: 4,
                                }}
                            >
                                {table.label}
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.faint,
                                    marginBottom: 12,
                                }}
                            >
                                {table.effective_from
                                    ? `Berlaku sejak ${table.effective_from}`
                                    : 'Memakai tabel bawaan PP 58/2023'}
                                {table.source ? ` · ${table.source}` : ''}
                                {table.change_reason
                                    ? ` · ${table.change_reason}`
                                    : ''}
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
                                            style={{
                                                fontSize: 12.5,
                                                color: C.amber,
                                                fontWeight: 500,
                                            }}
                                        >
                                            {issue}
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div
                                style={{
                                    overflowX: 'auto',
                                    maxHeight: 460,
                                    overflowY: 'auto',
                                }}
                            >
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                    }}
                                >
                                    <thead>
                                        <tr>
                                            {[
                                                '#',
                                                'Batas Bawah (>)',
                                                'Batas Atas (s.d.)',
                                                'Tarif',
                                                '',
                                            ].map((h, i) => (
                                                <th key={i} style={th}>
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {table.brackets.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    style={{
                                                        ...td,
                                                        textAlign: 'center',
                                                        color: C.faint,
                                                    }}
                                                >
                                                    Belum ada bracket untuk
                                                    kategori ini.
                                                </td>
                                            </tr>
                                        ) : (
                                            table.brackets.map((b, i) => (
                                                <tr key={b.id}>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            color: C.faint,
                                                            width: 44,
                                                        }}
                                                    >
                                                        {i + 1}
                                                    </td>
                                                    <td style={td}>
                                                        {rupiah(b.income_min)}
                                                    </td>
                                                    <td style={td}>
                                                        {b.income_max ===
                                                        null ? (
                                                            <span
                                                                style={{
                                                                    color: C.muted,
                                                                }}
                                                            >
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
                                                            width: 120,
                                                        }}
                                                    >
                                                        {percent(b.rate)}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...td,
                                                            textAlign: 'right',
                                                        }}
                                                    >
                                                        {canManage && (
                                                            <div
                                                                style={{
                                                                    display:
                                                                        'inline-flex',
                                                                    gap: 6,
                                                                }}
                                                            >
                                                                <ActionBtn
                                                                    icon="pencil"
                                                                    label="Koreksi"
                                                                    onClick={() =>
                                                                        openEditor(
                                                                            b,
                                                                        )
                                                                    }
                                                                />
                                                                <ActionBtn
                                                                    icon="trash-2"
                                                                    label="Hapus"
                                                                    variant="danger"
                                                                    onClick={() => {
                                                                        removeForm.setData(
                                                                            {
                                                                                effective_start_date:
                                                                                    '',
                                                                                reason: '',
                                                                            },
                                                                        );
                                                                        setRemoving(
                                                                            b,
                                                                        );
                                                                    }}
                                                                />
                                                            </div>
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
                    <div
                        style={{
                            fontSize: 14,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 4,
                        }}
                    >
                        Kategori PTKP
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.faint,
                            marginBottom: 14,
                        }}
                    >
                        Status PTKP mana membaca tabel yang mana. Status yang
                        tidak ada di mapping menghentikan payroll karyawan
                        tersebut, bukan diam-diam dihitung sebagai Kategori A.
                    </div>

                    {canManage && (
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr 1fr 1.4fr auto',
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
                                    onChange={(e) =>
                                        mapForm.setData(
                                            'ptkp_status',
                                            e.target.value,
                                        )
                                    }
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
                                    onChange={(e) =>
                                        mapForm.setData(
                                            'category',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {categoryOptions
                                        .filter((o) => o.value !== 'HARIAN')
                                        .map((o) => (
                                            <option
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </option>
                                        ))}
                                </select>
                            </div>
                            <div>
                                <span style={label}>Berlaku mulai</span>
                                <DatePicker
                                    value={mapForm.data.effective_start_date}
                                    onChange={(v) =>
                                        mapForm.setData(
                                            'effective_start_date',
                                            v,
                                        )
                                    }
                                    placeholder="Pilih tanggal"
                                    width="100%"
                                />
                            </div>
                            <div>
                                <span style={label}>Alasan perubahan</span>
                                <input
                                    style={input}
                                    placeholder="mis. Koreksi kategori sesuai lampiran PMK"
                                    value={mapForm.data.reason}
                                    onChange={(e) =>
                                        mapForm.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <button
                                style={{ ...primaryBtn, background: C.green }}
                                disabled={mapForm.processing}
                                onClick={saveMap}
                            >
                                <AIcon name="save" size={15} color="#fff" />
                                Terbitkan
                            </button>
                        </div>
                    )}

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
                                        'Status PTKP',
                                        'Kategori TER',
                                        'Berlaku sejak',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {categoryMap.map((row) => (
                                    <tr key={row.ptkp_status}>
                                        <td
                                            style={{
                                                ...td,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
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

                {/* Kalkulator PPh21 Bulanan (TER) — sheet "Kalkulator" */}
                <div style={{ ...card, padding: 18, marginTop: 18 }}>
                    <div
                        style={{
                            fontSize: 14,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 4,
                        }}
                    >
                        Kalkulator PPh 21 Bulanan (TER)
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.faint,
                            marginBottom: 14,
                        }}
                    >
                        Isi status PTKP dan penghasilan bruto bulanan —
                        kategori, tarif dan PPh 21 terhitung otomatis dari tabel
                        yang berlaku pada {asOf}.
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.4fr 1fr 1.2fr',
                            gap: 12,
                            marginBottom: 16,
                        }}
                    >
                        <div>
                            <span style={label}>Nama karyawan (opsional)</span>
                            <input
                                style={input}
                                placeholder="mis. Andi Wijaya"
                                value={calcName}
                                onChange={(e) => setCalcName(e.target.value)}
                            />
                        </div>
                        <div>
                            <span style={label}>Status PTKP</span>
                            <select
                                style={input}
                                value={calcPtkp}
                                onChange={(e) => setCalcPtkp(e.target.value)}
                            >
                                {ptkpStatuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <span style={label}>
                                Penghasilan bruto bulanan (Rp)
                            </span>
                            <RupiahInput
                                style={input}
                                placeholder="12.000.000"
                                value={calcGross}
                                onChange={setCalcGross}
                            />
                        </div>
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(3, 1fr)',
                            gap: 12,
                        }}
                    >
                        {[
                            ['Kategori TER (otomatis)', calcCategory],
                            ['Tarif TER (otomatis)', percent(calcRate)],
                            ['PPh 21 bulan berjalan', rupiah(calcTax)],
                        ].map(([caption, value], i) => (
                            <div
                                key={i}
                                style={{
                                    padding: '14px 16px',
                                    borderRadius: 8,
                                    background:
                                        i === 2
                                            ? 'rgba(47,84,201,.07)'
                                            : C.surface,
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: C.muted,
                                        marginBottom: 5,
                                    }}
                                >
                                    {caption}
                                </div>
                                <div
                                    style={{
                                        fontSize: 18,
                                        fontWeight: 600,
                                        color: i === 2 ? C.primary : C.navy,
                                    }}
                                >
                                    {value}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div
                        style={{
                            marginTop: 14,
                            padding: '11px 14px',
                            borderRadius: 8,
                            background: '#FFFBEB',
                            border: `1px solid ${C.amber}33`,
                            fontSize: 12.5,
                            color: C.amber,
                            fontWeight: 500,
                            lineHeight: 1.55,
                        }}
                    >
                        Berlaku untuk masa pajak Januari–November. Desember /
                        masa pajak terakhir memakai tarif progresif Pasal 17 UU
                        HPP (rekonsiliasi tahunan), yang dijalankan otomatis
                        oleh Payroll Run pada masa pajak terakhir. Kelebihan
                        potong sepanjang tahun dikembalikan pada slip masa pajak
                        terakhir.
                    </div>
                </div>

                {/* Sumber & Disclaimer — sheet "Sumber" */}
                <div style={{ ...card, padding: 18, marginTop: 18 }}>
                    <div
                        style={{
                            fontSize: 14,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 14,
                        }}
                    >
                        Sumber &amp; Disclaimer
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                            }}
                        >
                            <tbody>
                                {[
                                    [
                                        'Dasar hukum',
                                        'PP No. 58 Tahun 2023 & PMK No. 168/PMK.03/2023 (berlaku sejak 1 Januari 2024).',
                                    ],
                                    [
                                        'Cakupan',
                                        'TER Bulanan (masa pajak Januari–November) untuk pegawai tetap. Desember memakai tarif progresif Pasal 17.',
                                    ],
                                    [
                                        'Kompilasi angka',
                                        'Direkonstruksi dari referensi publik, bukan diketik langsung dari lampiran resmi PP 58/2023.',
                                    ],
                                    [
                                        'Wajib divalidasi',
                                        'Sebelum dipakai di payroll produksi, cocokkan seluruh baris Kategori A/B/C dengan Lampiran PP 58/2023 asli atau kalkulator.pajak.go.id.',
                                    ],
                                    [
                                        'Update',
                                        'Cek berkala ke situs resmi Direktorat Jenderal Pajak (pajak.go.id) untuk perubahan aturan, lalu terbitkan versi baru di layar ini.',
                                    ],
                                ].map(([caption, body], i) => (
                                    <tr key={i}>
                                        <td
                                            style={{
                                                ...td,
                                                width: 180,
                                                fontWeight: 600,
                                                color: C.navy,
                                                verticalAlign: 'top',
                                            }}
                                        >
                                            {caption}
                                        </td>
                                        <td
                                            style={{
                                                ...td,
                                                color: C.muted,
                                                lineHeight: 1.55,
                                            }}
                                        >
                                            {body}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {resetOpen && (
                <Modal
                    title="Reset ke tarif PP 58/2023"
                    description="Tarif statutori diterbitkan sebagai versi baru. Versi yang berlaku sekarang ditutup sehari sebelum tanggal berlaku, tidak dihapus."
                    onClose={() => setResetOpen(false)}
                >
                    <div style={{ marginBottom: 12 }}>
                        <span style={label}>Berlaku mulai</span>
                        <DatePicker
                            value={resetForm.data.effective_start_date}
                            onChange={(v) =>
                                resetForm.setData('effective_start_date', v)
                            }
                            placeholder="Pilih tanggal"
                            width="100%"
                        />
                    </div>
                    <div style={{ marginBottom: 16 }}>
                        <span style={label}>
                            Alasan perubahan (min. 10 karakter)
                        </span>
                        <input
                            style={input}
                            value={resetForm.data.reason}
                            onChange={(e) =>
                                resetForm.setData('reason', e.target.value)
                            }
                            placeholder="mis. Membatalkan impor keliru dan kembali ke tarif statutori"
                        />
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 8,
                        }}
                    >
                        <ActionBtn
                            icon="x"
                            label="Batal"
                            onClick={() => setResetOpen(false)}
                        />
                        <button
                            style={{ ...primaryBtn, background: C.amber }}
                            disabled={resetForm.processing}
                            onClick={submitReset}
                        >
                            Terbitkan reset
                        </button>
                    </div>
                </Modal>
            )}

            {editing && (
                <Modal
                    title="Koreksi bracket"
                    description="Bracket yang sudah terbit tidak diubah di tempat. Koreksi ini menerbitkan versi baru seluruh kategori dengan tanggal berlaku sendiri."
                    onClose={() => setEditing(null)}
                >
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 12,
                        }}
                    >
                        <div>
                            <span style={label}>Batas bawah (Rp)</span>
                            <input
                                style={input}
                                type="number"
                                value={bracketForm.data.income_min}
                                onChange={(e) =>
                                    bracketForm.setData(
                                        'income_min',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                        <div>
                            <span style={label}>
                                Batas atas (kosong = ke atas)
                            </span>
                            <input
                                style={input}
                                type="number"
                                value={bracketForm.data.income_max ?? ''}
                                onChange={(e) =>
                                    bracketForm.setData(
                                        'income_max',
                                        e.target.value === ''
                                            ? null
                                            : Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div style={{ marginTop: 12 }}>
                        <span style={label}>
                            Tarif desimal (0,0225 = 2,25%)
                        </span>
                        <input
                            style={input}
                            type="number"
                            step="0.000001"
                            value={bracketForm.data.rate}
                            onChange={(e) =>
                                bracketForm.setData(
                                    'rate',
                                    Number(e.target.value),
                                )
                            }
                        />
                        <div
                            style={{
                                fontSize: 11.5,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {percent(bracketForm.data.rate || 0)}
                        </div>
                    </div>
                    <div style={{ marginTop: 12 }}>
                        <span style={label}>Berlaku mulai</span>
                        <DatePicker
                            value={bracketForm.data.effective_start_date}
                            onChange={(v) =>
                                bracketForm.setData('effective_start_date', v)
                            }
                            placeholder="Pilih tanggal"
                            width="100%"
                        />
                    </div>
                    <div style={{ marginTop: 12, marginBottom: 16 }}>
                        <span style={label}>
                            Alasan perubahan (min. 10 karakter)
                        </span>
                        <input
                            style={input}
                            value={bracketForm.data.reason}
                            onChange={(e) =>
                                bracketForm.setData('reason', e.target.value)
                            }
                            placeholder="mis. Salah ketik tarif bracket ke-3 saat impor"
                        />
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 8,
                        }}
                    >
                        <ActionBtn
                            icon="x"
                            label="Batal"
                            onClick={() => setEditing(null)}
                        />
                        <button
                            style={{ ...primaryBtn, background: C.green }}
                            disabled={bracketForm.processing}
                            onClick={submitBracket}
                        >
                            Terbitkan versi baru
                        </button>
                    </div>
                </Modal>
            )}

            {removing && (
                <Modal
                    title="Hapus bracket"
                    description="Bracket dihapus dengan menerbitkan versi baru kategori ini. Kalau penghapusan meninggalkan celah pada tabel, publikasi ditolak."
                    onClose={() => setRemoving(null)}
                >
                    <div
                        style={{
                            fontSize: 13,
                            color: C.text,
                            marginBottom: 12,
                        }}
                    >
                        {rupiah(removing.income_min)} –{' '}
                        {removing.income_max === null
                            ? 'ke atas'
                            : rupiah(removing.income_max)}{' '}
                        · {percent(removing.rate)}
                    </div>
                    <div style={{ marginBottom: 12 }}>
                        <span style={label}>Berlaku mulai</span>
                        <DatePicker
                            value={removeForm.data.effective_start_date}
                            onChange={(v) =>
                                removeForm.setData('effective_start_date', v)
                            }
                            placeholder="Pilih tanggal"
                            width="100%"
                        />
                    </div>
                    <div style={{ marginBottom: 16 }}>
                        <span style={label}>
                            Alasan perubahan (min. 10 karakter)
                        </span>
                        <input
                            style={input}
                            value={removeForm.data.reason}
                            onChange={(e) =>
                                removeForm.setData('reason', e.target.value)
                            }
                            placeholder="mis. Bracket duplikat hasil impor workbook lama"
                        />
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 8,
                        }}
                    >
                        <ActionBtn
                            icon="x"
                            label="Batal"
                            onClick={() => setRemoving(null)}
                        />
                        <button
                            style={{ ...primaryBtn, background: C.red }}
                            disabled={removeForm.processing}
                            onClick={submitRemove}
                        >
                            Terbitkan tanpa bracket ini
                        </button>
                    </div>
                </Modal>
            )}
        </>
    );
}
