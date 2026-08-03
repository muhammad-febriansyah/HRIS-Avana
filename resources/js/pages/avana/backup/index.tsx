import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AIcon, btnExport, btnOut, C, card, thCell } from '@/lib/avana';
import type { BackupProps } from './types';

const numberFormat = (n: number) => n.toLocaleString('id-ID');

export default function AvanaBackup({
    tables,
    totalTables,
    totalRows,
    connection,
    error,
}: BackupProps) {
    const [selected, setSelected] = useState<string[]>([]);
    const [withData, setWithData] = useState(true);
    const [compress, setCompress] = useState(true);

    const allSelected = selected.length === 0;

    const downloadUrl = useMemo(() => {
        const params = new URLSearchParams();
        params.set('with_data', withData ? '1' : '0');
        params.set('compress', compress ? '1' : '0');
        selected.forEach((name) => params.append('tables[]', name));

        return `/avana/backup/unduh?${params.toString()}`;
    }, [selected, withData, compress]);

    const toggle = (name: string) =>
        setSelected((current) =>
            current.includes(name)
                ? current.filter((n) => n !== name)
                : [...current, name],
        );

    const selectedRows = allSelected
        ? totalRows
        : tables
              .filter((t) => selected.includes(t.name))
              .reduce((sum, t) => sum + t.rows, 0);

    return (
        <>
            <Head title="Backup Database" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ marginBottom: 22 }}>
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
                        <span>Platform</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Backup Database</span>
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
                        Backup Database
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Unduh isi database sebagai berkas SQL — koneksi{' '}
                        <strong>{connection}</strong>
                    </div>
                </div>

                {error !== null && (
                    <div
                        style={{
                            ...card,
                            padding: 16,
                            marginBottom: 18,
                            borderColor: C.red,
                            color: C.red,
                            fontSize: 13.5,
                        }}
                    >
                        {error}
                    </div>
                )}

                <div
                    style={{
                        ...card,
                        padding: 16,
                        marginBottom: 18,
                        display: 'flex',
                        gap: 10,
                        alignItems: 'flex-start',
                        background: '#FFFBEB',
                        borderColor: '#FDE68A',
                    }}
                >
                    <AIcon name="shield-alert" size={18} />
                    <div style={{ fontSize: 13.5, color: '#92400E', lineHeight: 1.55 }}>
                        Berkas ini memuat <strong>seluruh data seluruh tenant</strong> —
                        gaji, dokumen pribadi, dan kredensial ter-hash. Simpan
                        terenkripsi, jangan kirim lewat chat atau email, dan hapus
                        setelah dipakai. Setiap unduhan tercatat di Audit Trail
                        beserta nama dan alamat IP pengunduh.
                    </div>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                        gap: 12,
                        marginBottom: 18,
                    }}
                >
                    {[
                        { label: 'Tabel', value: numberFormat(totalTables) },
                        { label: 'Total Baris', value: numberFormat(totalRows) },
                        {
                            label: 'Terpilih',
                            value: allSelected
                                ? 'Semua tabel'
                                : `${selected.length} tabel · ${numberFormat(selectedRows)} baris`,
                        },
                    ].map((kpi) => (
                        <div key={kpi.label} style={{ ...card, padding: 16 }}>
                            <div style={{ fontSize: 12.5, color: C.muted }}>
                                {kpi.label}
                            </div>
                            <div
                                style={{
                                    fontSize: 20,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginTop: 6,
                                }}
                            >
                                {kpi.value}
                            </div>
                        </div>
                    ))}
                </div>

                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            gap: 14,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                        }}
                    >
                        <label
                            style={{
                                display: 'flex',
                                gap: 7,
                                alignItems: 'center',
                                fontSize: 13.5,
                                color: C.navy,
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={withData}
                                onChange={(e) => setWithData(e.target.checked)}
                            />
                            Sertakan data (bukan hanya struktur)
                        </label>
                        <label
                            style={{
                                display: 'flex',
                                gap: 7,
                                alignItems: 'center',
                                fontSize: 13.5,
                                color: C.navy,
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={compress}
                                onChange={(e) => setCompress(e.target.checked)}
                            />
                            Kompres .gz
                        </label>

                        <div style={{ flex: 1 }} />

                        {selected.length > 0 && (
                            <button
                                type="button"
                                style={btnOut}
                                onClick={() => setSelected([])}
                            >
                                Pilih semua tabel
                            </button>
                        )}

                        <a href={downloadUrl} style={{ ...btnExport, textDecoration: 'none' }}>
                            <AIcon name="download" size={15} />
                            Unduh {compress ? '.sql.gz' : '.sql'}
                        </a>
                    </div>

                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={{ ...thCell, width: 44 }} />
                                <th style={thCell}>Tabel</th>
                                <th style={{ ...thCell, textAlign: 'right' }}>Baris</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tables.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        style={{
                                            padding: 18,
                                            fontSize: 13.5,
                                            color: C.muted,
                                        }}
                                    >
                                        Tidak ada tabel yang bisa dibaca.
                                    </td>
                                </tr>
                            )}
                            {tables.map((table) => (
                                <tr
                                    key={table.name}
                                    style={{ borderTop: `1px solid ${C.border}` }}
                                >
                                    <td style={{ padding: '10px 18px' }}>
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(table.name)}
                                            onChange={() => toggle(table.name)}
                                            aria-label={table.name}
                                        />
                                    </td>
                                    <td
                                        style={{
                                            padding: '10px 18px',
                                            fontSize: 13.5,
                                            color: C.navy,
                                            fontFamily:
                                                'ui-monospace, SFMono-Regular, Menlo, monospace',
                                        }}
                                    >
                                        {table.name}
                                    </td>
                                    <td
                                        style={{
                                            padding: '10px 18px',
                                            fontSize: 13.5,
                                            color: C.muted,
                                            textAlign: 'right',
                                        }}
                                    >
                                        {numberFormat(table.rows)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
