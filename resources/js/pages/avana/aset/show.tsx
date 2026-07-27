import { Head, Link, router } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import AssetController from '@/actions/App/Http/Controllers/Avana/AssetController';
import { AIcon, btnOut, btnP, C, card, rp } from '@/lib/avana';
import { ConditionBadge, StatusPill } from './components';
import { conditionLabel, statusLabel } from './types';
import type { AsetShowProps } from './types';

const fieldLabel: CSSProperties = { fontSize: 12, color: C.faint };
const fieldValue: CSSProperties = { fontSize: 14, color: C.text, marginTop: 3 };
const thCell: CSSProperties = {
    padding: '12px 18px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};
const tdCell: CSSProperties = {
    padding: '13px 18px',
    fontSize: 13,
    color: C.text,
};

function dash(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value : '—';
}

/** Escape a string for safe interpolation into the print window's HTML. */
function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/** A single labeled value cell inside the detail grid. */
function Cell({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <div style={fieldLabel}>{label}</div>
            <div style={fieldValue}>{value}</div>
        </div>
    );
}

export default function AsetShow({ asset, history, qrUrl }: AsetShowProps) {
    const printLabel = () => {
        const win = window.open('', '_blank', 'width=420,height=580');

        if (!win) {
            return;
        }

        win.document.write(
            `<!doctype html><html><head><title>QR ${escapeHtml(asset.code)}</title>` +
                '<style>body{font-family:system-ui,-apple-system,sans-serif;text-align:center;margin:0;padding:36px}' +
                'img{width:260px;height:260px}.code{font-size:22px;font-weight:700;margin-top:18px}' +
                '.name{font-size:14px;color:#555;margin-top:5px}</style></head><body>' +
                `<img src="${escapeHtml(qrUrl)}" alt="QR" onload="window.focus();window.print()"/>` +
                `<div class="code">${escapeHtml(asset.code)}</div>` +
                `<div class="name">${escapeHtml(asset.name)}</div></body></html>`,
        );
        win.document.close();
    };

    return (
        <>
            <Head title={`Aset · ${asset.code}`} />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <Link
                                href={AssetController.index()}
                                style={{
                                    color: C.muted,
                                    textDecoration: 'none',
                                }}
                            >
                                Aset
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>{asset.code}</span>
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            {asset.name}
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {asset.code} · {asset.category}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <Link
                            href={AssetController.index()}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="arrow-left" size={16} color={C.text} />
                            Kembali
                        </Link>
                        <button
                            type="button"
                            onClick={() =>
                                router.visit(AssetController.edit(asset.id).url)
                            }
                            style={{ ...btnP }}
                        >
                            <AIcon name="pencil" size={16} color="#fff" />
                            Edit
                        </button>
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        gap: 20,
                        flexWrap: 'wrap',
                        alignItems: 'flex-start',
                    }}
                >
                    {/* QR card */}
                    <div
                        style={{
                            ...card,
                            padding: 24,
                            width: 300,
                            flex: '0 0 300px',
                            textAlign: 'center',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 16,
                            }}
                        >
                            QR Code Aset
                        </div>
                        <img
                            src={qrUrl}
                            alt={`QR ${asset.code}`}
                            width={220}
                            height={220}
                            style={{
                                width: 220,
                                height: 220,
                                border: `1px solid ${C.border}`,
                                borderRadius: 12,
                                padding: 10,
                                background: '#fff',
                            }}
                        />
                        <div
                            style={{
                                fontSize: 12,
                                color: C.muted,
                                margin: '14px 0 16px',
                                lineHeight: 1.5,
                            }}
                        >
                            Scan untuk membuka detail aset ini di perangkat
                            lain.
                        </div>
                        <button
                            type="button"
                            onClick={printLabel}
                            style={{
                                ...btnP,
                                background: C.amber,
                                width: '100%',
                                justifyContent: 'center',
                            }}
                        >
                            <AIcon name="printer" size={16} color="#fff" />
                            Cetak Label QR
                        </button>
                    </div>

                    {/* Detail card */}
                    <div
                        style={{
                            ...card,
                            padding: 24,
                            flex: '1 1 420px',
                            minWidth: 320,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 18,
                            }}
                        >
                            Detail Aset
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(180px, 1fr))',
                                gap: 18,
                            }}
                        >
                            <Cell label="Kode" value={asset.code} />
                            <Cell label="Kategori" value={asset.category} />
                            <Cell
                                label="Kondisi"
                                value={
                                    <ConditionBadge
                                        condition={asset.condition}
                                    />
                                }
                            />
                            <Cell
                                label="Status"
                                value={<StatusPill status={asset.status} />}
                            />
                            <Cell
                                label="Tanggal Pembelian"
                                value={dash(asset.purchase_date)}
                            />
                            <Cell
                                label="Harga Beli"
                                value={rp(asset.purchase_cost)}
                            />
                            <Cell
                                label="Penyusutan"
                                value={`${asset.depreciation_years} tahun`}
                            />
                            <Cell
                                label="Nilai Buku"
                                value={
                                    <span
                                        style={{
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {rp(asset.book_value)}
                                    </span>
                                }
                            />
                        </div>
                        {asset.notes && asset.notes.trim() !== '' && (
                            <div style={{ marginTop: 18 }}>
                                <div style={fieldLabel}>Catatan</div>
                                <div
                                    style={{
                                        ...fieldValue,
                                        whiteSpace: 'pre-wrap',
                                    }}
                                >
                                    {asset.notes}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Assignment history */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '26px 0 12px',
                    }}
                >
                    Riwayat Penugasan
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 720,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Tgl Ditugaskan</th>
                                    <th style={thCell}>Tgl Dikembalikan</th>
                                    <th style={thCell}>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={4}
                                            style={{
                                                ...tdCell,
                                                textAlign: 'center',
                                                color: C.faint,
                                                padding: '26px 18px',
                                            }}
                                        >
                                            Belum ada riwayat penugasan.
                                        </td>
                                    </tr>
                                )}
                                {history.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td style={tdCell}>
                                            <div
                                                style={{
                                                    fontWeight: 600,
                                                    color: C.text,
                                                }}
                                            >
                                                {row.employee_name ?? '—'}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {row.employee_number ?? ''}
                                            </div>
                                        </td>
                                        <td style={tdCell}>
                                            {dash(row.assigned_date)}
                                        </td>
                                        <td style={tdCell}>
                                            {row.returned_date ? (
                                                dash(row.returned_date)
                                            ) : (
                                                <span
                                                    style={{
                                                        color: C.green,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    Masih dipakai
                                                </span>
                                            )}
                                        </td>
                                        <td style={tdCell}>
                                            {dash(row.condition_note)}
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
